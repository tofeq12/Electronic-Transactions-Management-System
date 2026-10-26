<?php

namespace Database\Factories;

use App\Models\TrxType;
use App\Models\TrxWorkflow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrxWorkflow>
 */
class TrxWorkflowFactory extends Factory
{
    protected $model = TrxWorkflow::class;

    /**
     * مسير موافقات مرتبط بنوع معاملة (نوع واحد لكل مسير — يفرضه السيدر).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $take = 0;
        $take++;

        return [
            'trx_type_id' => TrxType::factory(),
            'name' => fake()->randomElement([
                'مسير الموافقات الأساسي',
                'مسير الموافقات المختصر',
                'مسير الموافقات المتقدم',
            ]).($take > 3 ? ' '.$take : ''),
            'is_active' => true,
            'version' => 1,
            'created_by' => null,
        ];
    }

    /** مسير معطّل (المعاملات الجديدة تسلك سلوك الخطوة الواحدة). */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
