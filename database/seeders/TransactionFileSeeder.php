<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\TransactionFile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * زرع ملفات مرفقة فعلية على القرص + صفوفها في transaction_files.
 *
 * الأمان:
 *  - الملفات الاختبارية PDF صغيرة صالحة فعليًا (ليست فارغة).
 *  - المسار بنفس تخطيط FileService: storage/app/transactions/{رقم}/...
 *  - لا نكتب فوق ملف موجود: نتخطى المعاملة إن كان لها ملفات.
 *  - التكرار الآمن عبر فحص وجود ملفات للمعاملة أولًا.
 */
class TransactionFileSeeder extends Seeder
{
    public function run(): void
    {
        // حماية التكرار: إن وُجد أي ملف سابق نتوقف كاملين (وإلا تراكمت
        // نسب 30% مع كل تشغيل).
        if (TransactionFile::query()->exists()) {
            $this->command?->info('TransactionFiles already exist — skipped.');
            return;
        }

        $ratio = 0.3; // نحو 30% من المعاملات تحمل مرفقًا
        $total = Transaction::query()->whereDoesntHave('files')->count();

        if ($total === 0) {
            $this->command?->info('TransactionFiles: no transactions — skipped.');
            return;
        }

        $transactions = Transaction::query()
            ->whereDoesntHave('files')
            ->inRandomOrder()
            ->limit((int) ceil($total * $ratio))
            ->get(['id', 'transaction_number', 'created_by', 'created_at']);

        $names = ['عقد-الصيانة.pdf', 'عرض-الأسعار.pdf', 'فاتورة-الشهر.pdf', 'خطاب-رسمي.pdf', 'محضر-الاجتماع.pdf', 'إيصال-السداد.pdf'];
        $created = 0;

        foreach ($transactions as $trx) {
            $directory = 'transactions/'.$trx->transaction_number;
            File::ensureDirectoryExists(storage_path('app/'.$directory));

            $filename = Str::random(40).'.pdf';
            $bytes = $this->minimalPdf($trx->transaction_number);

            File::put(storage_path('app/'.$directory.'/'.$filename), $bytes);

            $file = new TransactionFile([
                'transaction_id' => $trx->id,
                'original_name' => fake()->randomElement($names),
                'stored_path' => $directory.'/'.$filename,
                'mime' => 'application/pdf',
                'size' => strlen($bytes),
                'uploaded_by' => $trx->created_by,
            ]);
            $file->created_at = $trx->created_at ?? now();
            $file->updated_at = $file->created_at;
            $file->save();
            $created++;
        }

        $this->command?->info("TransactionFiles (real PDFs): {$created}");
    }

    /**
     * PDF أصغر صالح يُفتح فعليًا (بنية كتالوج/صفحات صحيحة + %%EOF).
     */
    private function minimalPdf(string $label): string
    {
        $text = 'ITQAN test attachment — '.$label;

        return "%PDF-1.4\n".
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n".
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n".
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >> endobj\n".
            "4 0 obj << /Length ".(strlen($text) + 40)." >> stream\n".
            "BT /F1 12 Tf 72 720 Td ({$text}) Tj ET\n".
            "endstream endobj\n".
            "trailer << /Root 1 0 R >>\n".
            "%%EOF\n";
    }
}
