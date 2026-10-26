<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 10 — server-computed reports + CSV export.
 *
 * - GET /reports/data aggregates the caller's VISIBLE transactions inside
 *   the date range (stats + the five chart bars) — the figures no longer
 *   depend on the newest-500-row page payload.
 * - GET /reports/export streams those exact rows as a UTF-8 CSV (BOM for
 *   Excel, formula-injection-safe cells).
 * - Both endpoints sit behind reports.view and the visibleTo() boundary;
 *   a reversed range is a validation error.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    /** Fast fixture: rows written directly (no HTTP round-trips). */
    private function makeTrx(User $creator, int $i, array $over = []): Transaction
    {
        return Transaction::create(array_merge([
            'transaction_number' => 'RP-'.$i.'-'.substr(md5((string) $i), 0, 6),
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

    private function range(): string
    {
        return 'from='.now()->subDays(10)->format('Y-m-d').'&to='.now()->format('Y-m-d');
    }

    public function test_report_data_aggregates_visible_rows_in_range(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makeTrx($admin, 1, ['status' => 'pending', 'type' => 'conversion']);
        $this->makeTrx($admin, 2, ['status' => 'pending', 'type' => 'purchase']);
        $this->makeTrx($admin, 3, ['status' => 'done', 'type' => 'bills']);
        $this->makeTrx($admin, 4, ['status' => 'rejected', 'type' => 'contract']);
        $this->makeTrx($admin, 5, ['status' => 'pending', 'type' => 'custom-type']);
        // الحالتان المكملتان للعرض الكامل — لا يجوز أن تختفيان من التقرير:
        $this->makeTrx($admin, 7, ['status' => 'draft']);
        $this->makeTrx($admin, 8, ['status' => 'returned']);
        // Out of range — must not count.
        $this->makeTrx($admin, 6, ['date' => now()->subMonths(3)->toDateString()]);

        $res = $this->actingAs($admin)->getJson('/reports/data?'.$this->range())->assertOk();
        $data = $res->json('data');

        $this->assertSame(7, $data['total']);
        $this->assertSame(3, $data['stats']['pending'], 'rows 1, 2 and 5');
        $this->assertSame(1, $data['stats']['approved'], 'done → approved');
        $this->assertSame(1, $data['stats']['rejected']);
        $this->assertSame(1, $data['stats']['draft']);
        $this->assertSame(1, $data['stats']['returned']);
        // ضمانة «النظام حقيقي»: مجموع الحالات الخمس = الإجمالي بالضبط.
        $this->assertSame(
            $data['total'],
            $data['stats']['approved'] + $data['stats']['pending'] + $data['stats']['rejected']
                + $data['stats']['draft'] + $data['stats']['returned'],
            'الإجمالي = مجموع الحالات الخمس'
        );
        // conversion (rows 1, 7, 8) | purchase | bills | contract | أخرى(admin-defined)
        $this->assertSame([3, 1, 1, 1, 1], $data['bars']);
    }

    public function test_report_data_without_range_counts_everything_visible(): void
    {
        $admin = User::factory()->admin()->create();
        $this->makeTrx($admin, 1);
        $this->makeTrx($admin, 2, ['date' => now()->subYear()->toDateString()]);

        $data = $this->actingAs($admin)->getJson('/reports/data')->assertOk()->json('data');

        $this->assertSame(2, $data['total']);
    }

    public function test_reversed_range_is_a_validation_error(): void
    {
        $admin = User::factory()->admin()->create();

        $res = $this->actingAs($admin)
            ->getJson('/reports/data?from='.now()->format('Y-m-d').'&to='.now()->subDay()->format('Y-m-d'))
            ->assertStatus(422);

        $this->assertStringContainsString('تاريخ البداية', (string) $res->json('errors.to.0'));
    }

    public function test_report_endpoints_require_reports_view(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)->getJson('/reports/data')->assertForbidden();
        $this->actingAs($employee)->get('/reports/export')->assertForbidden();
    }

    public function test_report_scopes_to_the_viewer_visibility(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'department_id' => $deptA->id]);
        $mine = $this->makeTrx($reviewer, 1, ['status' => 'done']);
        $foreign = $this->makeTrx(
            User::factory()->create(['role' => 'employee', 'department_id' => $deptB->id]),
            2
        );

        $data = $this->actingAs($reviewer)->getJson('/reports/data?'.$this->range())->assertOk()->json('data');

        $this->assertSame(1, $data['total'], "the other department's row never counts");
        $this->assertSame(1, $data['stats']['approved']);
        $this->assertNotSame($mine->id, $foreign->id);
    }

    public function test_csv_export_streams_visible_in_range_rows_only(): void
    {
        $admin = User::factory()->admin()->create();
        $mine = $this->makeTrx($admin, 1, ['subject' => '=1+1 دفع فواتير']);
        $outOfRange = $this->makeTrx($admin, 2, ['date' => now()->subMonths(3)->toDateString()]);

        $res = $this->actingAs($admin)->get('/reports/export?'.$this->range());
        $res->assertOk();
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('itqan-transactions-', (string) $res->headers->get('Content-Disposition'));

        $csv = $res->streamedContent();
        $this->assertTrue(str_starts_with($csv, "\xEF\xBB\xBF"), 'UTF-8 BOM so Excel renders Arabic');
        $this->assertStringContainsString('رقم المعاملة', $csv);
        $this->assertStringContainsString($mine->transaction_number, $csv);
        $this->assertStringContainsString("'=1+1", $csv, 'formula injection is neutralized');
        $this->assertStringNotContainsString($outOfRange->transaction_number, $csv, 'out-of-range rows stay out');
    }

    public function test_csv_export_respects_a_non_admin_viewer_scope(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer', 'department_id' => $deptA->id]);
        $mine = $this->makeTrx($reviewer, 1);
        $foreign = $this->makeTrx(
            User::factory()->create(['role' => 'employee', 'department_id' => $deptB->id]),
            2
        );

        $csv = $this->actingAs($reviewer)->get('/reports/export?'.$this->range())
            ->assertOk()->streamedContent();

        $this->assertStringContainsString($mine->transaction_number, $csv);
        $this->assertStringNotContainsString($foreign->transaction_number, $csv,
            "another department's row never leaves the server");
    }
}
