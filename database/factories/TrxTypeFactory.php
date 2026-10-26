<?php

namespace Database\Factories;

use App\Models\TrxType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrxType>
 */
class TrxTypeFactory extends Factory
{
    protected $model = TrxType::class;

    /**
     * نوع معاملة يُنشئه المدير (is_system = false) — الأنواع الأربعة
     * النظامية تأتي من هجرة الزرع ولا تُنشأ بالفاكتور أبدًا.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pool = [
            ['slug' => 'travel', 'ar' => 'سفر ومهمة', 'en' => 'Travel'],
            ['slug' => 'maintenance', 'ar' => 'صيانة أجهزة', 'en' => 'Maintenance'],
            ['slug' => 'training', 'ar' => 'دورة تدريبية', 'en' => 'Training'],
            ['slug' => 'budget', 'ar' => 'اعتماد ميزانية', 'en' => 'Budget'],
            ['slug' => 'hr_request', 'ar' => 'طلب موارد بشرية', 'en' => 'HR request'],
            ['slug' => 'procurement', 'ar' => 'مشتريات لوازم', 'en' => 'Procurement'],
        ];

        // عدّاد بسيط: يعطي عنصرًا مختلفًا لكل إنشاء ثم يكمل بأرقام فريدة —
        // لا يعتمد Faker unique() لتفادي نفاد القائمة في اختبار واحد.
        static $take = 0;
        $item = $pool[$take] ?? null;
        $take++;

        if ($item === null) {
            $n = $take - count($pool);
            $item = ['slug' => 'type'.$n, 'ar' => 'نوع إداري رقم '.$n, 'en' => 'Admin type '.$n];
        }

        return [
            'slug' => $item['slug'],
            'name_ar' => $item['ar'],
            'name_en' => fake()->optional(0.8)->passthrough($item['en']),
            'description' => fake()->optional(0.5)->sentence(6),
            'required_documents' => null,
            'is_active' => true,
            'is_system' => false,
            'sort_order' => 10 + $take,
            'created_by' => null,
        ];
    }

    /** نوع معطّل (لا يظهر للإرسال الجديد — التاريخ يبقى صالحًا). */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
