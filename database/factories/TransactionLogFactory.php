<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransactionLog>
 */
class TransactionLogFactory extends Factory
{
    protected $model = TransactionLog::class;

    /**
     * سجل داخل معاملة — الأفعال عربية ومطابقة لما يكتبه TransactionService
     * فعليًا (نفس نصوص السجلات الحقيقية).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                'تم إنشاء المعاملة',
                'تم إرسال المعاملة',
                'تم تعديل المعاملة',
                'تمت الموافقة',
                'تم رفض المعاملة',
            ]),
            'note' => fake()->optional(0.4)->sentence(6),
        ];
    }

    /** سجل إحالة (بنص الإحالة الحقيقي). */
    public function forward(): static
    {
        return $this->state(fn () => [
            'action' => 'تمت إحالة المعاملة إلى '.fake()->randomElement(['الإدارة المالية', 'تقنية المعلومات']),
        ]);
    }
}
