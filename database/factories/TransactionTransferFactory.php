<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransactionTransfer>
 */
class TransactionTransferFactory extends Factory
{
    protected $model = TransactionTransfer::class;

    /**
     * إحالة معاملة — من موظف إلى موظف (الأقسام تُمرَّر صراحة من السيدر
     * لأنها قابلة للفارغ وليست شرط سلامة).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'from_user_id' => User::factory(),
            'to_user_id' => User::factory(),
            'from_dept_id' => null,
            'to_dept_id' => null,
            'note' => fake()->sentence(6),
        ];
    }

    /** إحالة بين قسمين (مع تعبئة الأقسام). */
    public function betweenDepartments(): static
    {
        return $this->state(fn () => [
            'from_dept_id' => \App\Models\Department::factory(),
            'to_dept_id' => \App\Models\Department::factory(),
        ]);
    }
}
