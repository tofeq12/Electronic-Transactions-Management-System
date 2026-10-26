<?php

namespace Database\Factories;

use App\Models\College;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\College>
 */
class CollegeFactory extends Factory
{
    protected $model = College::class;

    /**
     * تعريف الحالة الافتراضية — أسماء كليات جامعية عربية حقيقية الشكل.
     * عمود name فريد في القاعدة، لذا نستخدم unique() (الفكر يُعاد تعيينه
     * لكل اختبار) ونضيف بديلاً مرقّماً عند نفاد القائمة.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pool = [
            'كلية الهندسة',
            'كلية الطب',
            'كلية العلوم',
            'كلية إدارة الأعمال',
            'كلية الآداب',
            'كلية التربية',
            'كلية الحاسبات والمعلومات',
            'كلية الطيران',
            'كلية الشريعة',
            'كلية التمريض',
            'كلية العمارة والتخطيط',
            'كلية اللغات والترجمة',
        ];

        $name = fake()->unique()->randomElement($pool);

        // ضمان التفرد حتى لو نفدت القائمة (لا استثناء في منتصف الاختبار).
        if ($name === null) {
            $name = 'كلية بديلة '.fake()->unique()->numberBetween(100, 999);
        }

        return ['name' => $name];
    }
}
