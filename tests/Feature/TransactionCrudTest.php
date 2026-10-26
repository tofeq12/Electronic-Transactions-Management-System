<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransactionCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The payload shape the existing form submits (Arabic type label, Y/m/d date).
     *
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private function payload(array $over = []): array
    {
        return array_merge([
            'type' => 'تحويل مبلغ',
            'subject' => 'تحويل رواتب الموظفين',
            'from' => 'الخزينة العامة',
            'to' => 'البنك الأهلي',
            'amount' => '1500.50',
            'date' => date('Y/m/d'),
            'desc' => 'وصف مختصر',
            'priority' => 'normal',
            'status' => 'pending',
        ], $over);
    }

    public function test_admin_creates_pending_transaction_with_generated_number(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/transactions', $this->payload());

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message', 'data', 'errors']);

        $number = 'TRX-'.date('Y').'-0001';
        $this->assertSame(
            '/transactions/details?id='.$number,
            $response->json('data.redirect')
        );

        $transaction = Transaction::first();
        $this->assertNotNull($transaction);
        $this->assertSame($number, $transaction->transaction_number);
        $this->assertMatchesRegularExpression('/^TRX-\d{4}-\d{4}$/', $transaction->transaction_number);
        $this->assertSame('conversion', $transaction->type);
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('1500.50', (string) $transaction->amount);
        $this->assertSame($admin->id, $transaction->created_by);

        // Creation writes the initial log + activity entry.
        $this->assertDatabaseHas('transaction_logs', ['transaction_id' => $transaction->id, 'action' => 'تم إنشاء المعاملة']);
        $this->assertDatabaseHas('transaction_logs', ['transaction_id' => $transaction->id, 'action' => 'تم إرسال المعاملة']);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $admin->id, 'target' => $number]);
    }

    public function test_transaction_numbers_are_sequential(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/transactions', $this->payload());
        $this->actingAs($admin)->postJson('/transactions', $this->payload());

        $this->assertSame(
            ['TRX-'.date('Y').'-0001', 'TRX-'.date('Y').'-0002'],
            Transaction::orderBy('id')->pluck('transaction_number')->all()
        );
    }

    public function test_type_slug_is_accepted_and_stored(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/transactions', $this->payload(['type' => 'purchase']))
            ->assertOk();

        $this->assertSame('purchase', Transaction::first()->type);
    }

    public function test_draft_can_be_created(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/transactions', $this->payload(['status' => 'draft']))
            ->assertOk();

        $this->assertSame('draft', Transaction::first()->status);
    }

    public function test_validation_rejects_unknown_type_status_and_amount(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/transactions', $this->payload(['type' => 'غير معروف']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');

        $this->actingAs($admin)
            ->postJson('/transactions', $this->payload(['status' => 'done']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->actingAs($admin)
            ->postJson('/transactions', $this->payload(['amount' => 'abc', 'subject' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'subject']);

        // Truly malformed dates are still rejected (separator variants are
        // normalized first, then the strict Y/m/d format rule applies).
        $this->actingAs($admin)
            ->postJson('/transactions', $this->payload(['date' => '13-13-2026']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('date');

        $this->assertSame(0, Transaction::count());
    }

    public function test_iso_date_input_is_normalized_to_the_ui_format(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/transactions', $this->payload(['date' => '2026-12-31']))
            ->assertOk();

        $this->assertSame('2026-12-31', Transaction::first()->date->toDateString());
    }

    public function test_admin_updates_transaction_and_logs_the_edit(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/transactions', $this->payload());
        $number = Transaction::first()->transaction_number;

        $this->actingAs($admin)
            ->postJson('/transactions/'.$number.'/update', $this->payload([
                'subject' => 'موضوع بعد التعديل',
                'priority' => 'urgent',
            ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $transaction = Transaction::first();
        $this->assertSame('موضوع بعد التعديل', $transaction->subject);
        $this->assertSame('urgent', $transaction->priority);
        $this->assertDatabaseHas('transaction_logs', ['transaction_id' => $transaction->id, 'action' => 'تم تعديل المعاملة']);
    }

    public function test_edit_submitting_pending_promotes_a_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/transactions', $this->payload(['status' => 'draft']));
        $number = Transaction::first()->transaction_number;

        $this->actingAs($admin)
            ->postJson('/transactions/'.$number.'/update', $this->payload(['status' => 'pending']))
            ->assertOk();

        $this->assertSame('pending', Transaction::first()->status);
        $this->assertDatabaseHas('transaction_logs', ['transaction_id' => 1, 'action' => 'تم إرسال المعاملة']);
    }

    public function test_update_without_status_field_keeps_the_current_status(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/transactions', $this->payload(['status' => 'draft']));
        $number = Transaction::first()->transaction_number;

        $payload = $this->payload(['subject' => 'مسودة معدلة']);
        unset($payload['status']);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$number.'/update', $payload)
            ->assertOk();

        $this->assertSame('draft', Transaction::first()->status);
    }

    public function test_disallowed_attachment_is_rejected(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $payload = $this->payload();
        $payload['files'] = [UploadedFile::fake()->create('malware.exe', 10)];

        $this->actingAs($admin)
            ->postJson('/transactions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('files.0');

        $this->assertSame(0, Transaction::count());
    }

    public function test_reviewer_cannot_create_transactions(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)
            ->postJson('/transactions', $this->payload())
            ->assertStatus(403);

        $this->actingAs($reviewer)
            ->get('/transactions/new')
            ->assertStatus(403);

        $this->assertSame(0, Transaction::count());
    }

    public function test_employee_can_create_and_gets_own_department_attached(): void
    {
        $department = \App\Models\Department::factory()->create();
        $employee = User::factory()->create(['department_id' => $department->id]);

        $this->actingAs($employee)
            ->postJson('/transactions', $this->payload())
            ->assertOk();

        $transaction = Transaction::first();
        $this->assertSame($department->id, $transaction->dept_id);
        $this->assertSame($employee->id, $transaction->created_by);
    }
}
