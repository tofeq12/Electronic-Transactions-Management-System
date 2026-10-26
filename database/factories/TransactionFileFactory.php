<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransactionFile>
 */
class TransactionFileFactory extends Factory
{
    protected $model = TransactionFile::class;

    /**
     * صف ملف مرفق — المسار المخزَّن يُبنى من رقم معاملته الفعلية بنفس
     * تخطيط FileService: transactions/{رقم_المعاملة}/{اسم_عشوائي}.pdf
     *
     * ملاحظة: الفاكتور ينشئ سطر القاعدة فقط؛ السيدر هو من يكتب ملفات
     * الاختبار الفعلية على القرص.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'original_name' => fake()->randomElement([
                'عقد-الصيانة.pdf',
                'عرض-الأسعار.pdf',
                'فاتورة-الشهر.pdf',
                'خطاب-رسمي.pdf',
                'محضر-الاجتماع.pdf',
                'إيصال-السداد.pdf',
            ]),
            'stored_path' => function (array $attributes): string {
                $trx = Transaction::query()->find($attributes['transaction_id'] ?? null);
                $number = $trx?->transaction_number ?? ('TRX-'.date('Y').'-0000');

                return 'transactions/'.$number.'/'.Str::random(40).'.pdf';
            },
            'mime' => 'application/pdf',
            'size' => fake()->numberBetween(10_240, 2_000_000),
            'uploaded_by' => User::factory(),
        ];
    }

    /** ملف Word بدل PDF. */
    public function docx(): static
    {
        return $this->state(fn () => [
            'original_name' => 'تقرير-مرفق.docx',
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }
}
