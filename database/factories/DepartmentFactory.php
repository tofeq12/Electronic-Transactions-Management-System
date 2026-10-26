<?php

namespace Database\Factories;

use App\Models\College;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    /**
     * تعريف الحالة الافتراضية — أقسام جامعية عربية مرتبطة بكلية حقيقية
     * عبر FK (college_id اختياري في القاعدة لكننا نربطه افتراضيًا لتجسيد
     * العلاقة الفعلية).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pool = [
            'شئون الطلاب',
            'الإدارة المالية',
            'الموارد البشرية',
            'تقنية المعلومات',
            'الشؤون القانونية',
            'المشتريات والمخازن',
            'العلاقات العامة',
            'التخطيط والمتابعة',
            'الامتحانات',
            'التسجيل والقبول',
            'الصيانة والتشغيل',
            'الدعم الفني',
            'الأبحاث والدراسات',
            'التطوير التعليمي',
            'الشؤون الصحية',
        ];

        $name = fake()->unique()->randomElement($pool) ?? ('قسم بديل '.fake()->unique()->numberBetween(100, 999));

        return [
            'name' => $name,
            'description' => fake()->optional(0.7)->sentence(6),
            'college_id' => College::factory(),
        ];
    }

    /**
     * قسم بلا كليه (العمود اختياري — يغطي مسار الاختبار الفارغ).
     */
    public function withoutCollege(): static
    {
        return $this->state(fn () => ['college_id' => null]);
    }
}
