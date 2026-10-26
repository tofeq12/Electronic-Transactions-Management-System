<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * زرع حسابات النظام: حسابات إدارية ثابتة (firstOrCreate — لا تمسّ حسابًا
 * موجودًا أبدًا) + موظفون ومراجعون بدرجات متفاوتة عبر UserFactory.
 *
 * الأمان:
 *  - لا يُعاد كتابة أي حساب موجود (البحث بالبريد فقط — من وُجد لا يُلمس).
 *  - كلمة مرور الحسابات الجديدة مطابقة لقيمة UserFactory الافتراضية
 *    (حسابات تطوير فقط — لا حسابات إنتاج).
 *  - الأدوار من Roles::ALL الحقيقية فقط، ومزامنتها مع Spatie تتم تلقائيًا
 *    عبر User::booted.
 *
 * الأعداد قابلة للضبط: SEED_EMPLOYEES (افتراضي 20) / SEED_REVIEWERS (8).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $departments = Department::query()->orderBy('id')->get(['id', 'college_id']);

        /* ---------------------------------------- الحسابات الإدارية الثابتة */
        $admins = [
            ['name' => 'مدير النظام', 'username' => 'sysadmin', 'email' => 'sysadmin@itqan.local', 'role' => 'system_admin', 'dept' => null],
            ['name' => 'مدير الجامعة', 'username' => 'uniadmin', 'email' => 'uniadmin@itqan.local', 'role' => 'university_admin', 'dept' => null],
            ['name' => 'مدير قسم تقنية المعلومات', 'username' => 'itmanager', 'email' => 'itmanager@itqan.local', 'role' => 'dept_manager', 'dept' => 'تقنية المعلومات'],
            ['name' => 'مدير قسم الإدارة المالية', 'username' => 'finmanager', 'email' => 'finmanager@itqan.local', 'role' => 'dept_manager', 'dept' => 'الإدارة المالية'],
        ];

        foreach ($admins as $admin) {
            $dept = $admin['dept'] ? Department::where('name', $admin['dept'])->first() : null;

            User::firstOrCreate(
                ['email' => $admin['email']],
                [
                    'name' => $admin['name'],
                    'username' => $admin['username'],
                    'phone' => '05'.substr((string) abs(crc32($admin['email'])), 0, 8),
                    'role' => $admin['role'],
                    'status' => 'active',
                    'department_id' => $dept?->id,
                    'college_id' => $dept?->college_id,
                    'password' => Hash::make('password'), // حسابات تطوير فقط
                ]
            );
        }

        /* ------------------------------------- الموظفون (يُضاف الناقص فقط) */
        $targetEmployees = max(0, (int) env('SEED_EMPLOYEES', 20));
        $have = User::where('role', 'employee')->count();
        $missingEmployees = max(0, $targetEmployees - $have);

        if ($missingEmployees > 0 && $departments->isNotEmpty()) {
            User::factory()
                ->count($missingEmployees)
                ->state(new Sequence(fn (Sequence $s) => [
                    'department_id' => $departments[($have + $s->index) % $departments->count()]->id,
                    'college_id' => $departments[($have + $s->index) % $departments->count()]->college_id,
                ]))
                ->create(['role' => 'employee', 'status' => 'active']);
        }

        /* --------------------------------------- المراجعون (يُضاف الناقص فقط) */
        $targetReviewers = max(0, (int) env('SEED_REVIEWERS', 8));
        $haveReviewers = User::where('role', 'reviewer')->count();
        $missingReviewers = max(0, $targetReviewers - $haveReviewers);

        if ($missingReviewers > 0) {
            User::factory()
                ->reviewer()
                ->count($missingReviewers)
                // المراجعون على مستوى الجامعة غالبًا: نُشرك قسمًا لبعضهم
                // للتنوع (كل ثالث مراجع يحصل على قسم).
                ->state(new Sequence(function (Sequence $s) use ($departments, $haveReviewers) {
                    $i = $haveReviewers + $s->index;
                    $dept = $departments->isNotEmpty() && $i % 3 === 0
                        ? $departments[$i % $departments->count()]
                        : null;

                    return ['department_id' => $dept?->id, 'college_id' => $dept?->college_id];
                }))
                ->create(['status' => 'active']);
        }

        $this->command?->info('Users: '.User::count());
    }
}
