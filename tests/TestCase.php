<?php

namespace Tests;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * زراعة الأدوار والصلاحيات (Spatie) لكل الاختبارات.
     *
     * نموذج User يزامن دور Spatie تلقائيًا عند الحفظ (انظر User::booted)،
     * وده يتطلّب وجود جداول roles/permissions. نزرعها هنا لكل اختبار يستخدم
     * RefreshDatabase. الاختبارات التي لا تُعيد بناء القاعدة (مثل ExampleTest
     * الذي يختبر الصفحة الرئيسية بلا DB) تتخطّى الزراعة لأن الجداول غير موجودة.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (Schema::hasTable('permissions') && Schema::hasTable('roles')) {
            $this->seed(RolesAndPermissionsSeeder::class);
        }
    }
}
