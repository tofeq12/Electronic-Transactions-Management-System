<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * إشعار داخل النظام — العناوين مطابقة لعناوين TransactionService
     * الحقيقية، والرابط يشير إلى صفحة تفاصيل معاملة فعلية.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $titles = [
            'معاملة جديدة بانتظار المراجعة',
            'تمت الموافقة على معاملتك',
            'تم رفض معاملتك',
            'إحالة معاملة',
            'المعاملة تحتاج استكمالاً',
            'معاملة بانتظار اعتمادك في خطوة السير',
        ];

        return [
            'user_id' => User::factory(),
            'transaction_id' => Transaction::factory(),
            'title' => fake()->randomElement($titles),
            'body' => fake()->sentence(8),
            'link' => function (array $attributes): ?string {
                $trx = Transaction::query()->find($attributes['transaction_id'] ?? null);

                return $trx ? '/transactions/details?id='.$trx->transaction_number : null;
            },
            'read_at' => null, // افتراضيًا غير مقروء
        ];
    }

    /** إشعار مقروء. */
    public function read(): static
    {
        return $this->state(fn () => ['read_at' => fake()->dateTimeBetween('-30 days', 'now')]);
    }

    /** إشعار عام بلا معاملة مرتبطة (العمود اختياري). */
    public function withoutTransaction(): static
    {
        return $this->state(fn () => ['transaction_id' => null, 'link' => '/inbox']);
    }
}
