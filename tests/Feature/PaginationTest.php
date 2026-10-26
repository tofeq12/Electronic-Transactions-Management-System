<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use App\Support\ItqanData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 7 — inbox pagination + on-demand loading for older transactions.
 *
 * - The inbox paginates server-side (25/page) instead of the old hard
 *   500-row cap; pager links keep the active filters.
 * - GET /transactions/list pages through the same visibleTo() boundary
 *   (backs the "load more" button on the transactions table).
 * - GET /transactions/view?id= returns one visible transaction as JSON
 *   (details/edit fallback for records older than the page payload).
 * - The global page payload ships the newest 500 visible rows only.
 */
class PaginationTest extends TestCase
{
    use RefreshDatabase;

    /** Fast fixture: rows written directly (no HTTP round-trips). */
    private function makeTrx(User $creator, int $i, array $over = []): Transaction
    {
        return Transaction::create(array_merge([
            'transaction_number' => 'PG-'.$i.'-'.substr(md5((string) $i), 0, 6),
            'subject' => 'معاملة '.$i,
            'type' => 'conversion',
            'date' => now()->toDateString(),
            'status' => 'pending',
            'dept_id' => $creator->department_id,
            'sender' => 'الخزينة',
            'recipient' => 'البنك',
            'amount' => '10.00',
            'priority' => 'normal',
            'created_by' => $creator->id,
        ], $over));
    }

    public function test_inbox_paginates_to_25_rows_with_correct_meta(): void
    {
        $dept = Department::factory()->create();
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $dept->id]);
        for ($i = 1; $i <= 30; $i++) {
            $this->makeTrx($employee, $i);
        }

        $response = $this->actingAs($employee)->get('/inbox');
        $response->assertOk();

        $inbox = $response->viewData('inbox');
        $this->assertCount(25, $inbox['rows'], 'page 1 ships 25 rows');
        $this->assertSame(1, $inbox['page']);
        $this->assertSame(2, $inbox['lastPage']);
        $this->assertSame(30, $inbox['total']);
        $this->assertSame(30, $inbox['waiting'], 'the waiting badge counts all pending, not just the page');
    }

    public function test_inbox_page_two_returns_remaining_rows_and_keeps_filters(): void
    {
        $dept = Department::factory()->create();
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $dept->id]);
        for ($i = 1; $i <= 30; $i++) {
            $this->makeTrx($employee, $i);
        }

        $response = $this->actingAs($employee)->get('/inbox?status=pending&page=2');
        $response->assertOk();

        $inbox = $response->viewData('inbox');
        $this->assertCount(5, $inbox['rows'], 'page 2 ships the remaining 5 rows');
        $this->assertSame(2, $inbox['page']);
        $this->assertSame('pending', $inbox['filters']['status'], 'the filter survives pagination');

        // Pager links keep every active filter (fullUrlWithQuery).
        $response->assertSee('page=1', false);
    }

    public function test_transactions_list_endpoint_paginates_with_meta(): void
    {
        $dept = Department::factory()->create();
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $dept->id]);
        for ($i = 1; $i <= 30; $i++) {
            $this->makeTrx($employee, $i);
        }

        $res = $this->actingAs($employee)->getJson('/transactions/list')->assertOk();
        $data = $res->json('data');
        $this->assertCount(25, $data['rows']);
        $this->assertSame(1, $data['meta']['page']);
        $this->assertSame(2, $data['meta']['last_page']);
        $this->assertSame(30, $data['meta']['total']);
        // Newest first (matches the page payload ordering); the payload's
        // "id" is the transaction NUMBER (frontend contract).
        $this->assertSame(
            Transaction::orderByDesc('id')->first()->transaction_number,
            $data['rows'][0]['id']
        );

        $page2 = $this->actingAs($employee)->getJson('/transactions/list?page=2')->assertOk();
        $this->assertCount(5, $page2->json('data.rows'));
        $this->assertSame(2, $page2->json('data.meta.page'));
    }

    public function test_transactions_list_and_view_respect_visibility_boundary(): void
    {
        $deptA = Department::factory()->create(['name' => 'قسم أ']);
        $deptB = Department::factory()->create(['name' => 'قسم ب']);
        $employeeA = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);
        $employeeB = User::factory()->create(['role' => 'employee', 'department_id' => $deptB->id]);
        $mine = $this->makeTrx($employeeB, 1);
        for ($i = 2; $i <= 4; $i++) {
            $this->makeTrx($employeeA, $i); // another department's rows
        }

        // B sees only their own row — never A's (IDOR-safe paging).
        $data = $this->actingAs($employeeB)->getJson('/transactions/list')->assertOk()->json('data');
        $this->assertSame(1, $data['meta']['total']);
        $this->assertCount(1, $data['rows']);
        $this->assertSame($mine->transaction_number, $data['rows'][0]['id']);

        // Single-record endpoint: visible → 200, invisible → 404. The `id`
        // parameter is the transaction NUMBER (frontend contract).
        $this->actingAs($employeeB)
            ->getJson('/transactions/view?id='.urlencode($mine->transaction_number))
            ->assertOk()
            ->assertJsonPath('data.id', $mine->transaction_number);

        $other = Transaction::where('created_by', $employeeA->id)->firstOrFail();
        $this->actingAs($employeeB)
            ->getJson('/transactions/view?id='.urlencode($other->transaction_number))
            ->assertNotFound();
    }

    public function test_global_payload_ships_newest_500_rows_only(): void
    {
        $admin = User::factory()->admin()->create();
        for ($i = 1; $i <= 503; $i++) {
            $this->makeTrx($admin, $i);
        }

        $rows = ItqanData::for($admin)['transactions'];

        $this->assertCount(500, $rows, 'the page payload is capped at the newest 500');
        $this->assertSame(
            Transaction::orderByDesc('id')->first()->transaction_number,
            $rows[0]['id'],
            'newest first'
        );
        // The 3 oldest fall back to the on-demand endpoints instead.
        $this->assertNotNull(
            Transaction::visibleTo($admin)->orderByDesc('id')->skip(500)->first()
        );
    }
}
