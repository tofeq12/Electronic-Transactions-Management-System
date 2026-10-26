<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\TransactionLog;
use Illuminate\Database\Seeder;

/**
 * زرع سجلات داخل المعاملات (transaction_logs) — نصوص الأفعال مطابقة
 * حرفية لما يكتبه TransactionService، وتوزيع يطابق حالة كل معاملة
 * (إنشاء/إرسال/تعديل/موافقة/رفض/إعادة/إحالة) بتواريخ مرجّعة واقعية.
 * التكرار الآمن: لا نُنشئ سجلًا إن كانت للمعاملة سجلات بالفعل.
 */
class TransactionLogSeeder extends Seeder
{
    public function run(): void
    {
        $transactions = Transaction::query()
            ->whereDoesntHave('logs')
            ->with(['creator:id,name', 'assignee:id,name'])
            ->get();

        foreach ($transactions as $trx) {
            $rows = [];
            $base = $trx->created_at ?? now();

            $rows[] = ['تم إنشاء المعاملة', $trx->creator?->name, $base, null];
            if ($trx->status !== 'draft') {
                $rows[] = ['تم إرسال المعاملة', $trx->creator?->name, $base->copy()->addMinutes(5), null];
            }
            if (in_array($trx->status, ['pending', 'done', 'rejected', 'returned'], true) && fake()->boolean(60)) {
                $rows[] = ['تمت إحالة المعاملة إلى '.($trx->department?->name ?? 'قسم'), $trx->creator?->name, $base->copy()->addDay(), 'إحالة للمراجعة'];
            }
            if ($trx->status === 'done') {
                $rows[] = ['تمت الموافقة', $trx->assignee?->name, $base->copy()->addDays(3), null];
            } elseif ($trx->status === 'rejected') {
                $rows[] = ['تم رفض المعاملة', $trx->assignee?->name, $base->copy()->addDays(3), 'المبلغ غير مدعوم بمستندات كافية'];
            } elseif ($trx->status === 'returned') {
                $rows[] = ['أعاد المعاملة إلى مقدم الطلب لاستكمال النواقص', $trx->assignee?->name, $base->copy()->addDays(2), 'نواقص في المستندات — المطلوب استكماله: المستندات المالية'];
            }

            foreach ($rows as [$action, $userName, $when, $note]) {
                $log = new TransactionLog([
                    'transaction_id' => $trx->id,
                    'user_id' => $trx->created_by ?? $trx->assigned_to ?? $this->anyUserId(),
                    'action' => $action,
                    'note' => $note,
                ]);
                $log->created_at = $when;
                $log->updated_at = $when;
                $log->save();
            }
        }

        $this->command?->info('TransactionLogs: '.TransactionLog::count());
    }

    /** أي معرّف مستخدم متاح (العمود اختياري وقد يكون null إن خلا المستخدمون). */
    private function anyUserId(): ?int
    {
        return \App\Models\User::query()->value('id');
    }
}
