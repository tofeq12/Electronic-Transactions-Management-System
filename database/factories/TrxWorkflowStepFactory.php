<?php

namespace Database\Factories;

use App\Models\TrxWorkflow;
use App\Models\TrxWorkflowStep;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrxWorkflowStep>
 */
class TrxWorkflowStepFactory extends Factory
{
    protected $model = TrxWorkflowStep::class;

    /**
     * عدّاد بديل لضمان تفرد step_order داخل التشغيل الواحد — السيدر
     * يمرّر ترتيبًا صريحًا (1..N) لكل مسير، والفكتور يُستخدم في الاختبارات.
     */
    protected static int $order = 0;

    /**
     * خطوة سير موافقات — الهدف الافتراضي دور حقيقي (department|role|user).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => TrxWorkflow::factory(),
            'step_order' => ++static::$order,
            'name_ar' => fake()->randomElement([
                'مراجعة أولى',
                'اعتماد المدير',
                'مراجعة مالية',
                'اعتماد نهائي',
                'أرشفة الخطوة',
            ]),
            'name_en' => null,
            'target_type' => 'role',
            'target_dept_id' => null,
            'target_role' => Roles::REVIEWER,
            'target_user_id' => null,
        ];
    }

    /** الهدف قسم معيّن. */
    public function departmentTarget(): static
    {
        return $this->state(fn () => [
            'target_type' => 'department',
            'target_dept_id' => \App\Models\Department::factory(),
            'target_role' => null,
            'target_user_id' => null,
        ]);
    }

    /** الهدف موظف معيّن. */
    public function userTarget(): static
    {
        return $this->state(fn () => [
            'target_type' => 'user',
            'target_dept_id' => null,
            'target_role' => null,
            'target_user_id' => \App\Models\User::factory(),
        ]);
    }

    /** الهدف دور معيّن (يمرّره السيدر/الاختبار). */
    public function roleTarget(string $role): static
    {
        return $this->state(fn () => [
            'target_type' => 'role',
            'target_dept_id' => null,
            'target_role' => $role,
            'target_user_id' => null,
        ]);
    }
}
