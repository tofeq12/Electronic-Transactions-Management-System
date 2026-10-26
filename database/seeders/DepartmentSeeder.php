<?php

namespace Database\Seeders;

use App\Models\College;
use App\Models\Department;
use Illuminate\Database\Seeder;

/**
 * زرع الأقسام — توزيع الأقسام على الكليات بالتناوب (كل قسم يرتبط بكلية
 * حقيقية عبر FK). firstOrCreate بالاسم لضمان التكرار الآمن.
 * العدد قابل للضبط عبر SEED_DEPARTMENTS (الافتراضي 12).
 */
class DepartmentSeeder extends Seeder
{
    public function run(): void
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

        $count = max(1, min(count($pool), (int) env('SEED_DEPARTMENTS', 12)));

        // الكليات تُزرع أولًا (ترتيب الاعتماد)؛ إن لم توجد نكمل بلا كلية.
        $colleges = College::query()->orderBy('id')->get(['id']);
        $index = 0;

        foreach (array_slice($pool, 0, $count) as $name) {
            Department::firstOrCreate(
                ['name' => $name],
                [
                    'description' => 'مسؤوليات وأعمال '.mb_substr($name, 0, 30),
                    'college_id' => $colleges->isEmpty() ? null : $colleges[$index % $colleges->count()]->id,
                ]
            );
            $index++;
        }

        $this->command?->info("Departments: ".Department::count());
    }
}
