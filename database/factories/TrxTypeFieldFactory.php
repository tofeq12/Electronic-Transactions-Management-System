<?php

namespace Database\Factories;

use App\Models\TrxType;
use App\Models\TrxTypeField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrxTypeField>
 */
class TrxTypeFieldFactory extends Factory
{
    protected $model = TrxTypeField::class;

    /**
     * حقل نموذج ديناميكي (Phase 4) — تسمية عربية ونوع من TYPES المسموح.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $take = 0;
        $take++;

        $labels = [
            'الجهة الموردة',
            'رقم العرض السعر',
            'تاريخ العرض',
            'طريقة الدفع',
            'اسم الموظف المكلّف',
            'مدة التنفيذ',
            'القيمة التقديرية',
            'رقم العقد المرجعي',
            'تاريخ بدء العقد',
            'الجهة المستفيدة',
            'ملاحظات إضافية',
            'رقم الطلب الداخلي',
        ];

        $label = $labels[($take - 1) % count($labels)];
        if ($take > count($labels)) {
            $label .= ' ('.$take.')'; // لتجاوز التكرار إن زاد العدد عن القائمة
        }

        return [
            'trx_type_id' => TrxType::factory(),
            'label_ar' => $label,
            'label_en' => null,
            'field_type' => fake()->randomElement(['text', 'number', 'date', 'textarea']),
            'is_required' => false,
            'options' => null,
            'validation' => null,
            'sort_order' => $take,
            'is_active' => true,
        ];
    }

    /** حقل إجباري. */
    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    /** حقل قائمة (select) مع خيارات ثنائية اللغة بنفس شكل definitionErrors. */
    public function select(): static
    {
        return $this->state(fn () => [
            'field_type' => 'select',
            'options' => [
                ['ar' => 'تحويل بنكي', 'en' => 'Bank transfer'],
                ['ar' => 'شيك', 'en' => 'Cheque'],
                ['ar' => 'نقدي', 'en' => 'Cash'],
            ],
        ]);
    }

    /** حقل معطّل (لا يظهر في النموذج الجديد). */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
