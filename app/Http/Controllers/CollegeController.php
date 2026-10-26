<?php

namespace App\Http\Controllers;

use App\Models\College;
use Illuminate\Http\Request;

/**
 * Phase 11 — colleges (top level of the organization structure). Same
 * gate as department management (departments.*); deletes are blocked
 * while anything still references the college (departments or users), so
 * no assignment is ever silently orphaned. Every action is audited.
 *
 * إدارة الكليات: نفس مجموعة صلاحيات الأقسام (departments.*)؛ كل طرقها
 * تستدعي authorize('departments.create/update/delete') بحسب الإجراء.
 */
class CollegeController extends Controller
{
    public function store(Request $request)
    {
        $this->authorize('departments.create');

        $request->validate($this->rules());
        $name = $this->normalize($request->input('name'));

        $college = College::create(['name' => $name]);

        \App\Support\Activity::log($request->user(), 'إنشاء كلية', $college->name);

        return $this->json(true, 'تمت إضافة الكلية', ['id' => $college->id]);
    }

    public function update(Request $request, int $id)
    {
        $this->authorize('departments.update');

        $college = College::findOrFail($id);

        $request->validate($this->rules($college));
        $name = $this->normalize($request->input('name'));

        $college->update(['name' => $name]);

        \App\Support\Activity::log($request->user(), 'تعديل كلية', $college->name);

        return $this->json(true, 'تم حفظ الكلية');
    }

    public function delete(Request $request, int $id)
    {
        $this->authorize('departments.delete');

        $college = College::withCount(['departments', 'users'])->findOrFail($id);

        // Mirror the department/transaction rules: references stay intact.
        if ($college->departments_count > 0 || $college->users_count > 0) {
            return $this->json(false, 'لا يمكن حذف كلية ترتبط بها أقسام أو مستخدمون', null, [], 409);
        }

        \App\Support\Activity::log($request->user(), 'حذف كلية', $college->name);

        $college->delete();

        return $this->json(true, 'تم حذف الكلية');
    }

    /**
     * Case-insensitive unique name (excluding the college being edited);
     * the standard validation contract answers 422 on failure.
     *
     * @return array<string, mixed>
     */
    private function rules(?College $existing = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
                function (string $attribute, mixed $value, $fail) use ($existing) {
                    $taken = College::whereRaw('LOWER(name) = ?', [mb_strtolower($this->normalize($value))])
                        ->when($existing, fn ($q) => $q->where('id', '!=', $existing->id))
                        ->exists();

                    if ($taken) {
                        $fail('اسم الكلية مستخدم مسبقاً');
                    }
                },
            ],
        ];
    }

    private function normalize(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value));
    }
}
