<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Username must stay lowercase + [a-z0-9_.] (login matches case-insensitively).
        $username = strtolower(preg_replace('/[^a-z0-9_.]/', '', (string) fake()->unique()->userName())) ?: 'user';
        if (strlen($username) < 3) {
            $username .= '123';
        }

        // أسماء عربية واقعية (اسم + عائلة) — متنوعة بلا تكرار ملحوظ.
        $firstName = fake()->randomElement([
            'أحمد', 'محمد', 'علي', 'فاطمة', 'عائشة', 'خالد', 'سارة', 'نورة',
            'عبدالله', 'ريم', 'يوسف', 'هدى', 'عمر', 'لينا', 'إبراهيم', 'مريم',
            'حسن', 'زينب', 'طارق', 'سلمى',
        ]);
        $lastName = fake()->randomElement([
            'العتيبي', 'الحربي', 'القحطاني', 'الشمري', 'الدوسري', 'المطيري',
            'العنزي', 'الغامدي', 'الزهراني', 'الخالدي', 'بن صالح', 'آل سعيد',
        ]);

        return [
            'name' => $firstName.' '.$lastName,
            'username' => $username,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => '05'.fake()->unique()->numerify('########'),
            'role' => 'employee',
            'status' => 'active',
            // Mirror the DB column default (users.locale = 'ar'): without it,
            // in-memory models (actingAs) would carry NULL and diverge from
            // what a DB-loaded user looks like.
            'locale' => 'ar',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Full-access administrator (المدير).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'system_admin',
        ]);
    }

    /**
     * Disabled account (غير نشط).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Reviewer role (مراجع).
     */
    public function reviewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'reviewer',
        ]);
    }

    /**
     * University-level administrator (مدير جامعة) — دور إداري حقيقي
     * من Roles::ALL غير قابل للاختيار من الواجهة.
     */
    public function universityAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'university_admin',
        ]);
    }

    /**
     * Department manager (مدير قسم) — بقسم أو بدونه حسب سياق الاختبار.
     */
    public function deptManager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'dept_manager',
        ]);
    }
}
