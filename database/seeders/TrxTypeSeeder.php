<?php

namespace Database\Seeders;

use App\Models\TrxType;
use Illuminate\Database\Seeder;

/**
 * زرع أنواع المعاملات:
 *  - الأربعة النظامية (conversion/purchase/bills/contract) بنفس محتوى هجرة
 *    الزرع 000203 — firstOrCreate فلا تُكرَّر ولا تتغير.
 *  - نوعان إداريان (is_system=false) يجسّدان ميزة "أنواع يديرها المدير"
 *    (Phase 4) لغطاء تنوّع 5-6 أنواع في بيانات الاختبار.
 */
class TrxTypeSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // الأنواع النظامية — القيم حرفيًا من هجرة الزرع.
        $system = [
            ['slug' => 'conversion', 'name_ar' => 'تحويل مبلغ', 'name_en' => 'Money transfer', 'sort_order' => 1],
            ['slug' => 'purchase', 'name_ar' => 'شراء أجهزة', 'name_en' => 'Equipment purchase', 'sort_order' => 2],
            ['slug' => 'bills', 'name_ar' => 'دفع فواتير', 'name_en' => 'Bill payment', 'sort_order' => 3],
            ['slug' => 'contract', 'name_ar' => 'تعاقد', 'name_en' => 'Contract', 'sort_order' => 4],
        ];

        foreach ($system as $type) {
            TrxType::firstOrCreate(
                ['slug' => $type['slug']],
                [
                    'name_ar' => $type['name_ar'],
                    'name_en' => $type['name_en'],
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $type['sort_order'],
                ]
            );
        }

        // أنواع إدارية (غير نظامية) — مسموح بها بنقاط Phase 4.
        $adminDefined = [
            ['slug' => 'travel', 'name_ar' => 'سفر ومهمة', 'name_en' => 'Travel', 'sort_order' => 5],
            ['slug' => 'maintenance', 'name_ar' => 'صيانة أجهزة', 'name_en' => 'Maintenance', 'sort_order' => 6],
        ];

        foreach ($adminDefined as $type) {
            TrxType::firstOrCreate(
                ['slug' => $type['slug']],
                [
                    'name_ar' => $type['name_ar'],
                    'name_en' => $type['name_en'],
                    'description' => 'نوع مُضاف من الإدارة (يظهر في القائمة المنسدلة).',
                    'is_active' => true,
                    'is_system' => false,
                    'sort_order' => $type['sort_order'],
                ]
            );
        }

        $this->command?->info('TrxTypes: '.TrxType::count());
    }
}
