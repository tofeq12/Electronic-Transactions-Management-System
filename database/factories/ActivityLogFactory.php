<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    /**
     * سجل تدقيق — الأفعال عربية ومطابقة لنصوص Activity::log/TransactionService
     * الحقيقية (لا أفعال مخترعة).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                'إنشاء وإرسال معاملة',
                'حفظ معاملة كمسودة',
                'تعديل معاملة',
                'إحالة معاملة',
                'موافقة على معاملة',
                'رفض معاملة',
                'إعادة معاملة لاستكمال',
            ]),
            'target' => 'TRX-'.date('Y').'-'.fake()->numerify('####'),
            'note' => fake()->optional(0.3)->sentence(5),
        ];
    }
}
