<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\TrxType;
use App\Models\TrxTypeField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4 — configurable transaction types + dynamic forms.
 *
 * Covers: configuration permissions, admin CRUD, dynamic field validation
 * (server-side), bilingual labels, file/required-documents handling and
 * backward compatibility with the four legacy types.
 */
class TypeConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'system_admin', 'status' => 'active']);
    }

    private function employee(): User
    {
        return User::factory()->create(['role' => 'employee', 'status' => 'active']);
    }

    /** @return array<string, mixed> */
    private function validTrxPayload(array $override = []): array
    {
        return $override + [
            'type' => 'تحويل مبلغ',
            'subject' => 'اختبار',
            'to' => 'الإدارة المالية',
            'amount' => '100.00',
            'date' => '2026/01/15',
            'priority' => 'normal',
            'status' => 'pending',
        ];
    }

    /* ------------------------------------------------------ seed shape */

    public function test_four_system_types_are_seeded_and_active(): void
    {
        $this->assertSame(
            ['conversion', 'purchase', 'bills', 'contract'],
            TrxType::orderBy('sort_order')->pluck('slug')->all()
        );
        $this->assertSame(4, TrxType::where('is_system', true)->where('is_active', true)->count());
    }

    /* ----------------------------------------------------- permission */

    public function test_types_page_and_writes_require_types_manage(): void
    {
        $employee = $this->employee();

        $this->actingAs($employee)->get('/types')->assertForbidden();

        $this->actingAs($employee)->postJson('/types', ['slug' => 'x', 'name_ar' => 'نوع'])->assertForbidden();
        $this->actingAs($employee)->postJson('/types/1/update', ['description' => 'x'])->assertForbidden();
        $this->actingAs($employee)->postJson('/types/1/toggle')->assertForbidden();
        $this->actingAs($employee)->postJson('/types/1/delete')->assertForbidden();
        $this->actingAs($employee)->postJson('/types/1/fields', ['label_ar' => 'حقل'])->assertForbidden();
        $this->actingAs($employee)->postJson('/fields/1/update', ['label_ar' => 'حقل'])->assertForbidden();
        $this->actingAs($employee)->postJson('/fields/1/toggle')->assertForbidden();
        $this->actingAs($employee)->postJson('/fields/1/delete')->assertForbidden();
    }

    public function test_admin_sees_types_page_and_full_list_payload(): void
    {
        $response = $this->actingAs($this->admin())->get('/types');
        $response->assertOk();
        // Custom type invisible until created; system four present.
        $this->assertDatabaseHas('trx_types', ['slug' => 'conversion', 'is_system' => 1]);
    }

    /* ---------------------------------------------------- admin CRUD */

    public function test_admin_creates_custom_type_with_ar_and_en_names(): void
    {
        $this->actingAs($this->admin())->postJson('/types', [
            'slug' => 'scholarship',
            'name_ar' => 'منح دراسية',
            'name_en' => 'Scholarship',
            'description' => 'طلب منحة',
            'required_documents' => ['خطاب القبول'],
            'sort_order' => 10,
        ])->assertOk();

        $type = TrxType::where('slug', 'scholarship')->firstOrFail();
        $this->assertSame('منح دراسية', $type->name_ar);
        $this->assertSame('Scholarship', $type->name_en);
        $this->assertSame(['خطاب القبول'], $type->required_documents);
        $this->assertTrue($type->is_active);
        $this->assertFalse($type->is_system);
    }

    public function test_type_slug_and_names_reject_collisions_with_system_types(): void
    {
        $admin = $this->admin();

        // Slug collision with a system slug.
        $this->actingAs($admin)->postJson('/types', ['slug' => 'contract', 'name_ar' => 'نوع جديد'])
            ->assertStatus(422);
        // Arabic-label collision with a system label.
        $this->actingAs($admin)->postJson('/types', ['slug' => 'other', 'name_ar' => 'تعاقد'])
            ->assertStatus(422);
        // Invalid slug characters.
        $this->actingAs($admin)->postJson('/types', ['slug' => 'نوععربي', 'name_ar' => 'نوع جديد'])
            ->assertStatus(422);
    }

    public function test_system_type_is_protected_from_delete_and_name_changes(): void
    {
        $admin = $this->admin();
        $system = TrxType::where('slug', 'contract')->firstOrFail();

        $this->actingAs($admin)->postJson('/types/'.$system->id.'/delete')->assertStatus(409);

        $this->actingAs($admin)->postJson('/types/'.$system->id.'/update', [
            'name_ar' => 'اسم جديد',
            'slug' => 'renamed',
            'description' => 'وصف محدّث',
        ])->assertOk();

        $system->refresh();
        $this->assertSame('تعاقد', $system->name_ar, 'System names must stay immutable');
        $this->assertSame('contract', $system->slug);
        $this->assertSame('وصف محدّث', $system->description);
    }

    public function test_deleting_type_with_existing_transactions_is_blocked_but_toggle_works(): void
    {
        $admin = $this->admin();
        $type = TrxType::create(['slug' => 'archive', 'name_ar' => 'أرشيف', 'name_en' => 'Archive']);

        Transaction::create([
            'transaction_number' => 'TRX-T1-0001',
            'subject' => 'مرتبطة',
            'type' => 'archive',
            'date' => '2026-01-15',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->postJson('/types/'.$type->id.'/delete')->assertStatus(409);

        $this->actingAs($admin)->postJson('/types/'.$type->id.'/toggle')->assertOk();
        $this->assertFalse($type->refresh()->is_active);
    }

    public function test_deleting_unused_custom_type_cascades_its_fields(): void
    {
        $admin = $this->admin();
        $type = TrxType::create(['slug' => 'temp', 'name_ar' => 'مؤقت']);
        $type->fields()->create(['label_ar' => 'حقل', 'field_type' => 'text']);

        $this->actingAs($admin)->postJson('/types/'.$type->id.'/delete')->assertOk();

        $this->assertDatabaseMissing('trx_types', ['id' => $type->id]);
        $this->assertDatabaseCount('trx_type_fields', 0);
    }

    /* ------------------------------------------- dynamic field storage */

    public function test_admin_adds_and_updates_fields_with_options_validation(): void
    {
        $admin = $this->admin();
        $type = TrxType::create(['slug' => 'scholarship', 'name_ar' => 'منح دراسية']);

        // Select without options is rejected.
        $this->actingAs($admin)->postJson('/types/'.$type->id.'/fields', [
            'label_ar' => 'الدرجة', 'field_type' => 'select',
        ])->assertStatus(422);

        $this->actingAs($admin)->postJson('/types/'.$type->id.'/fields', [
            'label_ar' => 'نوع المنحة', 'field_type' => 'select', 'is_required' => 1,
            'options' => ['دراسية', 'بحثية'],
        ])->assertOk();

        $field = $type->fields()->firstOrFail();
        $this->assertSame(['دراسية', 'بحثية'], $field->options);
        $this->assertTrue($field->is_required);

        $this->actingAs($admin)->postJson('/fields/'.$field->id.'/update', [
            'label_ar' => 'نوع المنحة (محدّث)', 'field_type' => 'select', 'options' => ['دراسية'],
        ])->assertOk();
        $this->assertSame('نوع المنحة (محدّث)', $field->refresh()->label_ar);
    }

    /* ------------------------------------ server-side dynamic validation */

    public function test_required_dynamic_field_is_enforced_on_the_server(): void
    {
        $user = $this->employee();
        $type = TrxType::create(['slug' => 'scholarship', 'name_ar' => 'منح دراسية']);
        $field = $type->fields()->create(['label_ar' => 'المعدل', 'field_type' => 'number', 'is_required' => 1]);

        // Missing required dynamic field → 422 even though JS could be bypassed.
        $this->actingAs($user)->postJson('/transactions', $this->validTrxPayload([
            'type' => 'منح دراسية', 'form_data' => json_encode([]),
        ]))->assertStatus(422)->assertJsonValidationErrors(['form_data.f'.$field->id]);

        // Provided and valid → stored.
        $response = $this->actingAs($user)->postJson('/transactions', $this->validTrxPayload([
            'type' => 'منح دراسية', 'form_data' => json_encode(['f'.$field->id => '3.8']),
        ]));
        $response->assertOk();

        $trx = Transaction::latest('id')->firstOrFail();
        $this->assertSame('scholarship', $trx->type);
        $this->assertSame(['f'.$field->id => '3.8'], $trx->form_data);
    }

    public function test_dynamic_field_shapes_are_validated_server_side(): void
    {
        $user = $this->employee();
        $type = TrxType::create(['slug' => 'scholarship', 'name_ar' => 'منح دراسية']);
        $num = $type->fields()->create(['label_ar' => 'المعدل', 'field_type' => 'number', 'validation' => ['min' => 0, 'max' => 4]]);
        $date = $type->fields()->create(['label_ar' => 'تاريخ البدء', 'field_type' => 'date']);
        $sel = $type->fields()->create(['label_ar' => 'المستوى', 'field_type' => 'select', 'options' => ['بكالوريوس', 'ماجستير']]);
        $text = $type->fields()->create(['label_ar' => 'ملاحظة', 'field_type' => 'text', 'validation' => ['max_length' => 5]]);

        $post = fn (array $data) => $this->actingAs($user)->postJson('/transactions', $this->validTrxPayload([
            'type' => 'منح دراسية', 'form_data' => json_encode($data),
        ]));

        $post(['f'.$num->id => '5'])->assertStatus(422);      // above max
        $post(['f'.$num->id => 'abc'])->assertStatus(422);    // not numeric
        $post(['f'.$date->id => '15/01/2026'])->assertStatus(422); // wrong date
        $post(['f'.$sel->id => 'دكتوراه'])->assertStatus(422);    // not an option
        $post(['f'.$text->id => 'أطول من الحد'])->assertStatus(422);
        $post(['f9999' => 'x'])->assertStatus(422);         // unknown key

        $post([
            'f'.$num->id => '3.5', 'f'.$date->id => '2026/02/01',
            'f'.$sel->id => 'ماجستير', 'f'.$text->id => 'قصير',
        ])->assertOk();
    }

    public function test_inactive_field_is_not_required_and_inactive_type_is_not_submittable(): void
    {
        $user = $this->employee();
        $type = TrxType::create(['slug' => 'scholarship', 'name_ar' => 'منح دراسية']);
        $field = $type->fields()->create(['label_ar' => 'المعدل', 'field_type' => 'number', 'is_required' => 1, 'is_active' => 0]);

        // Deactivated field → no requirement.
        $this->actingAs($user)->postJson('/transactions', $this->validTrxPayload([
            'type' => 'منح دراسية', 'form_data' => json_encode([]),
        ]))->assertOk();
        $field->update(['is_active' => 1]);

        // Deactivated TYPE → not selectable for new submissions.
        $type->update(['is_active' => 0]);
        $this->actingAs($user)->postJson('/transactions', $this->validTrxPayload(['type' => 'منح دراسية']))
            ->assertStatus(422);
    }

    /* ---------------------------------------------- required documents */

    public function test_required_documents_enforced_on_submit_but_not_draft(): void
    {
        $user = $this->employee();
        TrxType::create(['slug' => 'contract2', 'name_ar' => 'تعاقد جديد', 'required_documents' => ['العقد الأصلي']]);

        // Draft without files → allowed.
        $this->actingAs($user)->postJson('/transactions', $this->validTrxPayload([
            'type' => 'تعاقد جديد', 'status' => 'draft',
        ]))->assertOk();

        // Pending without files → 422 with the documents message.
        $this->actingAs($user)->postJson('/transactions', $this->validTrxPayload([
            'type' => 'تعاقد جديد', 'status' => 'pending',
        ]))->assertStatus(422)->assertJsonValidationErrors(['files']);

        // Pending with one attachment → accepted (uses the real FileService).
        $file = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');
        $this->actingAs($user)->post('/transactions', $this->validTrxPayload([
            'type' => 'تعاقد جديد', 'status' => 'pending', 'files' => [$file],
        ]), ['Accept' => 'application/json'])->assertOk();
    }

    /* ------------------------------------- backward compatibility */

    public function test_legacy_transaction_type_behaviour_is_unchanged(): void
    {
        $user = $this->employee();

        // Legacy Arabic label still works; slug stored; no form_data.
        $this->actingAs($user)->postJson('/transactions', $this->validTrxPayload())->assertOk();
        $trx = Transaction::latest('id')->firstOrFail();
        $this->assertSame('conversion', $trx->type);
        $this->assertNull($trx->form_data);

        // Editing a legacy transaction without form_data keeps working.
        $this->actingAs($user)->postJson('/transactions/'.$trx->transaction_number.'/update', [
            'type' => 'تحويل مبلغ', 'subject' => 'موضوع محدّث', 'to' => 'الإدارة المالية',
            'amount' => '250.00', 'date' => '2026/01/16', 'priority' => 'urgent',
        ])->assertOk();
        $this->assertSame('موضوع محدّث', $trx->refresh()->subject);
    }

    public function test_existing_transaction_editable_after_its_type_is_deactivated(): void
    {
        $admin = $this->admin();
        $user = $this->employee();
        $type = TrxType::create(['slug' => 'scholarship', 'name_ar' => 'منح دراسية']);
        $trx = Transaction::create([
            'transaction_number' => 'TRX-T2-0001',
            'subject' => 'منحة قديمة', 'type' => 'scholarship', 'date' => '2026-01-10',
            'status' => 'draft', 'created_by' => $user->id,
        ]);

        $type->update(['is_active' => 0]);

        // Deactivation must never break editing history.
        $this->actingAs($user)->postJson('/transactions/'.$trx->transaction_number.'/update', [
            'type' => 'منح دراسية', 'subject' => 'منحة (محدّثة)', 'to' => 'شؤون الطلاب',
            'amount' => '10.00', 'date' => '2026/01/11', 'priority' => 'normal',
        ])->assertOk();
        $this->assertSame('منحة (محدّثة)', $trx->refresh()->subject);
    }

    /* ------------------------------------------------ bilingual labels */

    public function test_payload_labels_follow_user_locale(): void
    {
        TrxType::create(['slug' => 'scholarship', 'name_ar' => 'منح دراسية', 'name_en' => 'Scholarship']);

        // @json escapes non-ASCII as \uXXXX — decode window.ITQAN_DATA and
        // assert on the actual trxTypes labels for each locale.
        $labels = function (User $u): array {
            $html = $this->actingAs($u)->get('/dashboard')->getContent();
            preg_match('/window\.ITQAN_DATA = (\{.*?\});\s*\n/', $html, $m);
            $data = json_decode($m[1] ?? '[]', true);

            return array_column($data['trxTypes'] ?? [], 'label', 'slug');
        };

        $this->assertSame('منح دراسية', $labels($this->employee())['scholarship']);

        $enUser = User::factory()->create(['role' => 'employee', 'status' => 'active', 'locale' => 'en']);
        $this->assertSame('Scholarship', $labels($enUser)['scholarship']);
    }

    public function test_type_label_and_slug_resolution_helpers(): void
    {
        TrxType::create(['slug' => 'scholarship', 'name_ar' => 'منح دراسية', 'name_en' => 'Scholarship']);

        $this->assertSame('scholarship', \App\Support\TransactionTypes::slug('منح دراسية'));
        $this->assertSame('scholarship', \App\Support\TransactionTypes::slug('scholarship'));
        $this->assertSame('scholarship', \App\Support\TransactionTypes::slug('Scholarship'));
        $this->assertSame('تحويل مبلغ', \App\Support\TransactionTypes::label('conversion'));
        $this->assertSame('منح دراسية', \App\Support\TransactionTypes::label('scholarship'));
        $this->assertNull(\App\Support\TransactionTypes::slug('غير موجود'));
    }

    /* -------------------------------------------------- data safety */

    public function test_form_data_column_is_nullable_json_and_old_rows_unaffected(): void
    {
        // Simulate a pre-Phase-4 row: created without form_data.
        $trx = Transaction::create([
            'transaction_number' => 'TRX-T3-0001',
            'subject' => 'قديمة', 'type' => 'bills', 'date' => '2025-12-01',
            'status' => 'done', 'created_by' => $this->employee()->id,
        ]);

        $this->assertNull($trx->fresh()->form_data);

        $cols = DB::select("PRAGMA table_info('transactions')");
        $fd = collect($cols)->firstWhere('name', 'form_data');
        $this->assertNotNull($fd, 'form_data column must exist after the Phase-4 migration');
        // SQLite reports NOTNULL as 0/1 — 0 means nullable (old rows safe).
        $this->assertSame(0, (int) $fd->notnull, 'form_data must stay nullable for old records');
    }
}
