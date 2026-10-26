<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Notification;
use App\Models\TransactionFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $over = []): array
    {
        return array_merge([
            'type' => 'تعاقد',
            'subject' => 'مرفق معاملة',
            'from' => 'الإدارة',
            'to' => 'المورد',
            'amount' => '250.00',
            'date' => date('Y/m/d'),
            'desc' => '',
            'priority' => 'normal',
            'status' => 'pending',
        ], $over);
    }

    private function createWithPdf(User $actor): array
    {
        $payload = $this->payload();
        $payload['files'] = [UploadedFile::fake()->createWithContent('contract.pdf', '%PDF-1.4'.str_repeat('x', 400))];

        $this->actingAs($actor)->postJson('/transactions', $payload)->assertOk();

        return [TransactionFile::firstOrFail(), \App\Models\Transaction::latest('id')->firstOrFail()];
    }

    public function test_attachment_is_stored_privately_and_downloadable_by_owner(): void
    {
        $admin = User::factory()->admin()->create();
        [$file] = $this->createWithPdf($admin);

        $this->assertSame('contract.pdf', $file->original_name);
        $this->assertStringStartsWith('transactions/', $file->stored_path);
        Storage::disk('local')->assertExists($file->stored_path);

        $response = $this->actingAs($admin)->get('/files/'.$file->id.'/download');
        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('contract.pdf', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('%PDF-1.4', $response->streamedContent());
    }

    public function test_oversized_attachment_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = $this->payload();
        $payload['files'] = [UploadedFile::fake()->create('big.pdf', 6000)]; // 6 MB > 5 MB

        $this->actingAs($admin)
            ->postJson('/transactions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('files.0');

        $this->assertSame(0, TransactionFile::count());
    }

    public function test_download_is_denied_outside_the_visibility_boundary(): void
    {
        $admin = User::factory()->admin()->create();
        [$file] = $this->createWithPdf($admin);

        $outsider = User::factory()->create([
            'department_id' => Department::factory()->create()->id,
        ]);

        $this->actingAs($outsider)->get('/files/'.$file->id.'/download')->assertStatus(403);

        // Unknown id → 404 through the JSON contract.
        $this->actingAs($admin)->get('/files/99999/download')->assertStatus(404);
    }

    public function test_notifications_page_shows_only_my_notifications(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        Notification::create([
            'user_id' => $admin->id,
            'title' => 'معاملة جديدة بانتظار المراجعة',
            'body' => 'عنوان خاص بي',
            'link' => '/transactions/details?id=TRX-1',
        ]);
        Notification::create([
            'user_id' => $other->id,
            'title' => 'إشعار لا يخصني',
            'body' => 'عنوان المستخدم الآخر',
        ]);

        $this->actingAs($admin)
            ->get('/notifications')
            ->assertOk()
            ->assertSee('معاملة جديدة بانتظار المراجعة')
            ->assertDontSee('إشعار لا يخصني');
    }

    public function test_mark_read_only_works_for_my_notifications(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create();

        $mine = Notification::create(['user_id' => $admin->id, 'title' => 'لي']);
        $theirs = Notification::create(['user_id' => $other->id, 'title' => 'لغيري']);

        $this->actingAs($admin)
            ->postJson('/notifications/'.$mine->id.'/read')
            ->assertOk();

        $this->assertNotNull($mine->refresh()->read_at);

        // Someone else's notification is not even reachable.
        $this->actingAs($admin)
            ->postJson('/notifications/'.$theirs->id.'/read')
            ->assertStatus(404);

        $this->assertNull($theirs->refresh()->read_at);
    }

    public function test_new_transaction_pends_and_notifies_admins(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();

        $this->actingAs($employee)->postJson('/transactions', $this->payload())->assertOk();

        $transaction = \App\Models\Transaction::latest('id')->firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'transaction_id' => $transaction->id,
        ]);
        // The actor never notifies themselves.
        $this->assertSame(0, Notification::where('user_id', $employee->id)->count());
    }
}
