<?php

namespace App\Http\Controllers;

use App\Http\Requests\TypeConfig\StoreTypeRequest;
use App\Http\Requests\TypeConfig\UpdateTypeRequest;
use App\Models\TrxType;
use App\Models\TrxTypeField;
use Illuminate\Http\Request;

/**
 * Phase 4 — administrator management of transaction types and their dynamic
 * fields. List = types.view; all mutations sit behind types.manage.
 * System types (the original
 * four) are protected: no delete, slug and Arabic/English names immutable —
 * only description, required documents, fields and activation can change.
 * Deactivating a type never breaks existing transactions: it only stops the
 * type from being selected for NEW submissions (service resolves stored
 * slugs regardless of is_active).
 *
 * إدارة أنواع المعاملات وحقولها: كل طرقها تستدعي authorize('types.manage')
 * للمدير فقط، والمسارات محمية أيضاً ببوابة can:types.view (للعرض) و
 * can:types.manage (للكتابة) في routes/web.php.
 */
class TrxTypeController extends Controller
{
    /* ----------------------------------------------------------- pages */

    public function index()
    {
        $this->authorize('types.view');

        return $this->page('pages.trx-types');
    }

    /* ----------------------------------------------------------- types */

    public function store(StoreTypeRequest $request)
    {
        $this->authorize('types.manage');

        $type = TrxType::create([
            'slug' => $request->validated()['slug'],
            'name_ar' => $request->validated()['name_ar'],
            'name_en' => $request->validated()['name_en'] ?? null,
            'description' => $request->validated()['description'] ?? null,
            'required_documents' => $request->validated()['required_documents'] ?? null,
            'sort_order' => $request->validated()['sort_order'] ?? 0,
            'created_by' => auth()->id(),
        ]);

        \App\Support\Activity::log($request->user(), 'إنشاء نوع معاملة', $type->slug);

        return $this->json(true, 'تمت إضافة نوع المعاملة', ['id' => $type->id]);
    }

    public function update(UpdateTypeRequest $request, int $id)
    {
        $this->authorize('types.manage');

        $type = TrxType::findOrFail($id);

        $data = $request->validated();

        // System types: names and slug stay exactly as the legacy UI expects.
        if ($type->is_system) {
            unset($data['name_ar'], $data['name_en'], $data['slug']);
        }

        $type->update($data);

        \App\Support\Activity::log($request->user(), 'تعديل نوع معاملة', $type->slug);

        return $this->json(true, 'تم حفظ نوع المعاملة');
    }

    public function toggle(Request $request, int $id)
    {
        $this->authorize('types.manage');

        $type = TrxType::findOrFail($id);
        $type->update(['is_active' => ! $type->is_active]);

        \App\Support\Activity::log($request->user(), $type->is_active ? 'تفعيل نوع معاملة' : 'إيقاف نوع معاملة', $type->slug);

        return $this->json(true, $type->is_active ? 'تم تفعيل النوع' : 'تم إيقاف النوع');
    }

    public function destroy(Request $request, int $id)
    {
        $this->authorize('types.manage');

        $type = TrxType::findOrFail($id);

        if ($type->is_system) {
            return $this->json(false, 'لا يمكن حذف نوع معاملة نظامي', null, [], 409);
        }

        // A type in use by real transactions must stay so history resolves.
        if (\DB::table('transactions')->where('type', $type->slug)->exists()) {
            return $this->json(false, 'لا يمكن حذف نوع مرتبط بمعاملات موجودة — يمكن إيقافه بدلاً من ذلك', null, [], 409);
        }

        \App\Support\Activity::log($request->user(), 'حذف نوع معاملة', $type->slug);
        $type->delete(); // cascades field definitions only — no transaction data

        return $this->json(true, 'تم حذف النوع');
    }

    /* ---------------------------------------------------------- fields */

    public function storeField(Request $request, int $typeId)
    {
        $this->authorize('types.manage');

        $type = TrxType::findOrFail($typeId);

        $field = $type->fields()->create($this->fieldData($request));

        \App\Support\Activity::log($request->user(), 'إضافة حقل ديناميكي', $field->label_ar, $type->name_ar);

        return $this->json(true, 'تمت إضافة الحقل', ['id' => $field->id]);
    }

    public function updateField(Request $request, int $fieldId)
    {
        $this->authorize('types.manage');

        $field = TrxTypeField::findOrFail($fieldId);
        $field->update($this->fieldData($request));

        \App\Support\Activity::log($request->user(), 'تعديل حقل ديناميكي', $field->label_ar,
            $field->type?->name_ar);

        return $this->json(true, 'تم حفظ الحقل');
    }

    public function toggleField(Request $request, int $fieldId)
    {
        $this->authorize('types.manage');

        $field = TrxTypeField::findOrFail($fieldId);
        $field->update(['is_active' => ! $field->is_active]);

        \App\Support\Activity::log($request->user(), $field->is_active ? 'تفعيل حقل ديناميكي' : 'إيقاف حقل ديناميكي',
            $field->label_ar, $field->type?->name_ar);

        return $this->json(true, $field->is_active ? 'تم تفعيل الحقل' : 'تم إيقاف الحقل');
    }

    public function destroyField(Request $request, int $fieldId)
    {
        $this->authorize('types.manage');

        // Configuration only: stored form_data values stay untouched on old
        // transactions (keyed by the deleted id, simply no longer displayed).
        $field = TrxTypeField::findOrFail($fieldId);

        \App\Support\Activity::log($request->user(), 'حذف حقل ديناميكي', $field->label_ar, $field->type?->name_ar);

        $field->delete();

        return $this->json(true, 'تم حذف الحقل');
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldData(Request $request): array
    {
        $data = $request->validate([
            'label_ar' => ['required', 'string', 'max:150'],
            'label_en' => ['nullable', 'string', 'max:150'],
            'field_type' => ['required', 'string', 'in:'.implode(',', TrxTypeField::TYPES)],
            'is_required' => ['nullable', 'boolean'],
            'options' => ['nullable', 'array', 'max:50'],
            'options.*' => ['required', 'string', 'max:150'],
            'validation' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Select fields need their options; everything else stores none.
        if ($data['field_type'] === 'select' && empty($data['options'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'options' => [__('خانة الاختيار تتطلب خيارات')],
            ]);
        }

        if ($data['field_type'] !== 'select') {
            $data['options'] = null;
        }

        $data['is_required'] = $request->boolean('is_required');
        $data['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;

        return $data;
    }
}
