<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

/**
 * زرع سجل التدقيق (activity_logs) — أفعال حقيقية من نصوص النظام
 * الفعلية (إنشاء/تعديل/إحالة/موافقة/رفض/إعادة/مسودة) ومرتبطة بأرقام
 * معاملات حقيقية بتواريخ مرجّعة. التكرار الآمن: لا ننشئ إن وجد سجلات.
 */
class ActivityLogSeeder extends Seeder
{
    public function run(): void
    {
        if (ActivityLog::query()->exists()) {
            $this->command?->info('ActivityLogs already exist — skipped.');
            return;
        }

        $transactions = Transaction::query()
            ->with(['creator:id,name', 'assignee:id,name'])
            ->get(['id', 'transaction_number', 'status', 'created_by', 'assigned_to', 'created_at']);

        $created = 0;

        foreach ($transactions as $trx) {
            $rows = [];
            $base = $trx->created_at ?? now();

            $rows[] = [
                $trx->status === 'draft' ? 'حفظ معاملة كمسودة' : 'إنشاء وإرسال معاملة',
                $trx->creator?->name,
                $trx->created_by,
                $base,
            ];

            if ($trx->status === 'done') {
                $rows[] = ['موافقة على معاملة', $trx->assignee?->name, $trx->assigned_to, $base->copy()->addDays(3)];
            } elseif ($trx->status === 'rejected') {
                $rows[] = ['رفض معاملة', $trx->assignee?->name, $trx->assigned_to, $base->copy()->addDays(3)];
            } elseif ($trx->status === 'returned') {
                $rows[] = ['إعادة معاملة لاستكمال', $trx->assignee?->name, $trx->assigned_to, $base->copy()->addDays(2)];
            } elseif ($trx->status === 'pending' && fake()->boolean(50)) {
                $rows[] = ['إحالة معاملة', $trx->creator?->name, $trx->created_by, $base->copy()->addDay()];
            }

            foreach ($rows as [$action, $by, $userId, $when]) {
                $log = new ActivityLog([
                    'user_id' => $userId,
                    'action' => $action,
                    'target' => $trx->transaction_number,
                    'note' => null,
                ]);
                $log->created_at = $when;
                $log->updated_at = $when;
                $log->save();
                $created++;
            }
        }

        $this->command?->info("ActivityLogs: {$created}");
    }
}
