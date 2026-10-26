<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\TransactionTransfer;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * زرع الإحالات (transaction_transfers) — سجل تحويل كل معاملة المُحالَة
 * من قسم/مستخدم إلى قسم/مستخدم آخرين. يلتقط المعاملات "قيد الانتظار"
 * والمعتمدة/المرفوضة المُحالَة سابقًا (قسمها مختلف عن قسم منشئها).
 * التكرار الآمن: لا ننشئ إن وجدت إحالات مسبقًا.
 */
class TransactionTransferSeeder extends Seeder
{
    public function run(): void
    {
        if (TransactionTransfer::query()->exists()) {
            $this->command?->info('TransactionTransfers already exist — skipped.');
            return;
        }

        $departments = Department::query()->get(['id']);
        if ($departments->isEmpty()) {
            $this->command?->warn('No departments — skipped transfers.');
            return;
        }

        $users = User::query()->get(['id', 'department_id']);
        $created = 0;

        // المعاملات غير المسودة قابلة للإحالة — نأخذ عيّنة متنوعة.
        $transactions = Transaction::query()
            ->whereIn('status', ['pending', 'done', 'rejected'])
            ->where('created_by', '!=', null)
            ->inRandomOrder()
            ->limit((int) ceil(Transaction::whereIn('status', ['pending', 'done', 'rejected'])->count() * 0.4))
            ->get(['id', 'dept_id', 'assigned_to', 'created_by', 'created_at']);

        foreach ($transactions as $trx) {
            $toDept = $departments->random();
            if ($toDept->id === $trx->dept_id) {
                continue; // إحالة لازمة لازم تغيّر القسم
            }

            $candidates = $users->where('department_id', $toDept->id);
            $toUser = $candidates->isNotEmpty() ? $candidates->random() : null;

            $transfer = new TransactionTransfer([
                'transaction_id' => $trx->id,
                'from_user_id' => $trx->assigned_to,
                'to_user_id' => $toUser?->id,
                'from_dept_id' => $trx->dept_id,
                'to_dept_id' => $toDept->id,
                'note' => fake()->randomElement([
                    'إحالة للمراجعة المتخصصة',
                    'طلب استكمال الملف من القسم المختص',
                    'تحويل وفق صلاحيات التوزيع',
                ]),
            ]);
            $when = ($trx->created_at ?? now())->copy()->addDays(fake()->numberBetween(1, 5));
            $transfer->created_at = $when;
            $transfer->updated_at = $when;
            $transfer->save();
            $created++;
        }

        $this->command?->info("TransactionTransfers: {$created}");
    }
}
