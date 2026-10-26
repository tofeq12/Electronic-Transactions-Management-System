<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * زراعة الإعداد الأساسي للنظام + (اختياريًا) بيانات التطوير.
     *
     * ===================== السلوك الآمن للإنتاج =====================
     * الافتراضي: أدوار وصلاحيات Spatie فقط (RolesAndPermissionsSeeder) —
     * لا حسابات وهمية ولا معاملات تجريبية تُزرع تلقائيًا أبدًا.
     *
     * بيانات التطوير/الاختبار تُزرع فقط عند التفعيل الصريح:
     *   SEED_DEV_DATA=true php artisan db:seed
     * (PowerShell:  $env:SEED_DEV_DATA="true"; php artisan db:seed)
     *
     * ===================== ترتيب الاعتماد =====================
     * الأب أولًا: أدوار/صلاحيات ← كليات ← أقسام ← مستخدمون (syncRoles
     * يحتاج الأدوار) ← أنواع ← حقول ← مسارات ← معاملات ← سجلات/إحالات
     * ← ملفات فعلية ← إشعارات ← تدقيق. كل سيدر قابل للتكرار بلا تكرار.
     *
     * ===================== الأعداد القابلة للضبط =====================
     *   SEED_COLLEGES=5  SEED_DEPARTMENTS=12
     *   SEED_EMPLOYEES=20  SEED_REVIEWERS=8  SEED_TRANSACTIONS=200
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (! filter_var(env('SEED_DEV_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->info('الأدوار والصلاحيات فقط — لزراعة بيانات التطوير فعّل SEED_DEV_DATA=true');

            return;
        }

        $this->call([
            CollegeSeeder::class,
            DepartmentSeeder::class,
            UserSeeder::class,
            TrxTypeSeeder::class,
            TrxTypeFieldSeeder::class,
            TrxWorkflowSeeder::class,
            TransactionSeeder::class,
            TransactionLogSeeder::class,
            TransactionTransferSeeder::class,
            TransactionFileSeeder::class,
            NotificationSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}
