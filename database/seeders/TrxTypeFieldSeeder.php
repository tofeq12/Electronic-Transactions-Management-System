<?php

namespace Database\Seeders;

use App\Models\TrxType;
use App\Models\TrxTypeField;
use Illuminate\Database\Seeder;

/**
 * زرع حقول النماذج الديناميكية لكل نوع (Phase 4) — قيم واقعية بنوع
 * مسموح (TrxTypeField::TYPES). firstOrCreate بالحقل نفسه يجعل التكرار
 * آمنًا؛ نوع بلا حقول (system دون حقول) مقصود لغطاء سيناريو "بدون حقول".
 */
class TrxTypeFieldSeeder extends Seeder
{
    public function run(): void
    {
        // [نوع, تسمية, نوع الحقل, إجباري, خيارات select]
        $plan = [
            ['purchase', 'الجهة الموردة', 'text', true, null],
            ['purchase', 'رقم العرض السعر', 'text', false, null],
            ['purchase', 'تاريخ العرض', 'date', false, null],
            ['purchase', 'طريقة الدفع', 'select', false, [
                ['ar' => 'تحويل بنكي', 'en' => 'Bank transfer'],
                ['ar' => 'شيك', 'en' => 'Cheque'],
                ['ar' => 'نقدي', 'en' => 'Cash'],
            ]],
            ['conversion', 'الجهة المرسلة', 'text', true, null],
            ['conversion', 'رقم الحساب البنكي', 'text', false, null],
            ['bills', 'رقم الفاتورة', 'text', true, null],
            ['bills', 'تاريخ الاستحقاق', 'date', false, null],
            ['bills', 'قيمة الفاتورة', 'number', false, null],
            ['contract', 'رقم العقد المرجعي', 'text', true, null],
            ['contract', 'تاريخ بدء العقد', 'date', false, null],
            ['contract', 'مدة العقد بالأشهر', 'number', false, null],
            ['travel', 'وجهة السفر', 'text', true, null],
            ['travel', 'تاريخ العودة', 'date', false, null],
            ['maintenance', 'وصف العطل', 'textarea', true, null],
            ['maintenance', 'رقم الجهاز', 'text', false, null],
        ];

        foreach ($plan as [$slug, $label, $type, $required, $options]) {
            $trxType = TrxType::where('slug', $slug)->first();
            if ($trxType === null) {
                continue; // النوع لم يُزرع — نتخطى (لا نشتق حقولًا لموجود غير موجود)
            }

            TrxTypeField::firstOrCreate(
                ['trx_type_id' => $trxType->id, 'label_ar' => $label],
                [
                    'field_type' => $type,
                    'is_required' => $required,
                    'options' => $options,
                    'sort_order' => TrxTypeField::where('trx_type_id', $trxType->id)->count() + 1,
                    'is_active' => true,
                ]
            );
        }

        $this->command?->info('TrxTypeFields: '.TrxTypeField::count());
    }
}
