<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\TrxType;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * زرع المعاملات التجريبية (100-300 — الافتراضي 200 عبر SEED_TRANSACTIONS).
 *
 * حسب نمط Laravel: كل الوعاء يعيش في الفاكتور — النصوص الواقعية لكل نوع،
 * الحالات المرجّحة، form_data بمفاتيح حقول حقيقية، لقطة سير الموافقات،
 * والتواريخ المتدرجة في TransactionFactory::seeded(). وهذا يضبط العدد
 * ويكفّل التكرار الآمن: أي تشغيل جديد يضيف الناقص فقط ولا يمسّ الموجود.
 */
class TransactionSeeder extends Seeder
{
    public function run(): void
    {
        $target = max(1, (int) env('SEED_TRANSACTIONS', 200));
        $existing = Transaction::count();

        if ($existing >= $target) {
            $this->command?->info("Transactions already {$existing} >= {$target} — skipped.");

            return;
        }

        if (! TrxType::where('is_active', true)->exists()) {
            $this->command?->warn('No active trx_types — run TrxTypeSeeder first. Skipped.');

            return;
        }

        // المستخدمون الحقيقيون شرط لمنشئ/معين واقعي (لا يُنشأ حساب أبدًا هنا).
        if (! User::whereIn('role', ['employee', 'reviewer'])->exists()) {
            $this->command?->warn('No employees/reviewers — run UserSeeder first. Skipped.');

            return;
        }

        Transaction::factory()
            ->count($target - $existing)
            ->seeded()
            ->create();

        $this->command?->info('Transactions: '.Transaction::count());
    }
}
