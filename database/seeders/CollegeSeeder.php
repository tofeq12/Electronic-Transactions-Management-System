<?php

namespace Database\Seeders;

use App\Models\College;
use Illuminate\Database\Seeder;

/**
 * زرع الكليات — أسماء عربية حقيقية الشكل وفريدة (firstOrCreate) ليبقى
 * السيدر قابلًا للتكرار بلا تكرار صفوف. العدد قابل للضبط عبر SEED_COLLEGES
 * (الافتراضي 5 كما في متطلبات بيانات الاختبار).
 */
class CollegeSeeder extends Seeder
{
    public function run(): void
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
        ];

        $count = max(1, min(count($pool), (int) env('SEED_COLLEGES', 5)));

        foreach (array_slice($pool, 0, $count) as $name) {
            College::firstOrCreate(['name' => $name]);
        }

        $this->command?->info("Colleges: ".College::count());
    }
}
