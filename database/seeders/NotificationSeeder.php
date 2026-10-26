<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * زرع الإشعارات — عناوين مطابقة لعناوين TransactionService الحقيقية
 * ومرتبطة بحالة كل معاملة (موافقة/رفض/إعادة/بانتظار) ورابط صفحة
 * التفاصيل الفعلي. نصف مقروء ونصف غير مقروء (تنوّع الواجهة).
 * التكرار الآمن: لا إشعارات إن وجدت بالفعل.
 */
class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        if (Notification::query()->exists()) {
            $this->command?->info('Notifications already exist — skipped.');
            return;
        }

        $transactions = Transaction::query()
            ->where('status', '!=', 'draft')
            ->where('created_by', '!=', null)
            ->get(['id', 'transaction_number', 'subject', 'status', 'assigned_to', 'created_by', 'created_at']);

        $admins = User::whereIn('role', ['system_admin', 'university_admin', 'dept_manager'])
            ->pluck('id');

        $created = 0;

        foreach ($transactions as $trx) {
            [$title, $body] = match ($trx->status) {
                'done' => ['تمت الموافقة على معاملتك', 'تمت الموافقة على معاملة: '.$trx->subject],
                'rejected' => ['تم رفض معاملتك', 'تم رفض معاملة: '.$trx->subject.' — السبب: المبلغ غير مدعوم بمستندات كافية'],
                'returned' => ['المعاملة تحتاج استكمالاً', 'أعاد المراجع معاملتك للاستكمال: '.$trx->subject.' — السبب: نواقص في المستندات'],
                default => ['معاملة جديدة بانتظار المراجعة', 'أُرسلت معاملة: '.$trx->subject],
            };

            // المستلم: المعين إن وجد، وإلا مديرو النظام؛ لا نُرسل للمنشئ نفسه.
            $recipient = $trx->assigned_to;
            if ($recipient === null || $recipient === $trx->created_by) {
                $recipient = $admins->isNotEmpty() ? $admins->random() : $trx->created_by;
            }
            if ($recipient === null || $recipient === $trx->created_by) {
                continue; // لا مستلم صالح
            }

            $n = new Notification([
                'user_id' => $recipient,
                'transaction_id' => $trx->id,
                'title' => $title,
                'body' => $body,
                'link' => '/transactions/details?id='.$trx->transaction_number,
                'read_at' => fake()->boolean(45) ? now()->subDays(fake()->numberBetween(0, 10)) : null,
            ]);
            $when = ($trx->created_at ?? now())->copy()->addMinutes(fake()->numberBetween(5, 2880));
            $n->created_at = $when;
            $n->updated_at = $when;
            $n->save();
            $created++;
        }

        $this->command?->info("Notifications: {$created}");
    }
}
