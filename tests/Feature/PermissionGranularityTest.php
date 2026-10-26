<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * المرحلة 3 — صلاحيات دقيقة (نقطة dot-notation لكل إجراء) بدل صلاحية
 * واحدة لكل متحكّم، على غرار الأنظمة الحقيقية:
 *
 * - users.view / create / toggle / delete تُفحص كلٌّ منها حيث يُنفَّذ.
 * - types.view (العرض) منفصلة عن workflows.manage (إدارة السير).
 * - trx.return صلاحية مستقلة عن trx.approve (سياسة + نقطة HTTP).
 * - حراسة قفل الوصول بعد الإصلاح: whereIn('name') + users.view/roles.manage
 *   (مدير النظام يستطيع الإبقاء على roles.manage عند التعديل).
 * - السيدر idempotent (تشغيل مزدوج بلا نمو) ولا يمسّ كلمات المرور.
 * - تسمية الدور العربية (roles.title) تُحفظ وتظهر في حمولة الشاشة.
 */
class PermissionGranularityTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------ helpers */

    /** دور مخصّص بمجموعة صلاحيات محدّدة + مستخدم يحمله (مع قسم اختياري). */
    private function actorWith(string $slug, array $permissions, ?int $departmentId = null): User
    {
        $role = Role::findOrCreate($slug, 'web');
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return User::factory()->create(['role' => $slug, 'department_id' => $departmentId]);
    }

    private function makeTrx(User $creator, int $deptId): Transaction
    {
        return Transaction::create([
            'transaction_number' => 'GR-'.substr(md5($creator->id.'-'.$deptId), 0, 6),
            'subject' => 'معاملة اختبار الصلاحيات',
            'type' => 'conversion',
            'date' => now()->toDateString(),
            'status' => 'pending',
            'dept_id' => $deptId,
            'sender' => 'الخزينة',
            'recipient' => 'البنك',
            'amount' => '10.00',
            'priority' => 'normal',
            'created_by' => $creator->id,
        ]);
    }

    /* -------------------------------------------------------------- users */

    public function test_users_screen_actions_are_split_by_granular_permissions(): void
    {
        $viewer = $this->actorWith('viewer_only', ['users.view']);
        $creator = $this->actorWith('creator_only', ['users.create']);
        $target = User::factory()->create();

        // users.view يفتح القائمة وحدها.
        $this->actingAs($viewer)->get('/dashboard/users')->assertOk();
        $this->actingAs($viewer)->get('/users/add')->assertForbidden();
        $this->actingAs($viewer)->post('/users/'.$target->id.'/toggle')->assertForbidden();
        $this->actingAs($viewer)->post('/users/'.$target->id.'/delete')->assertForbidden();

        // users.create يفتح نموذج الإضافة وحده.
        $this->actingAs($creator)->get('/users/add')->assertOk();
        $this->actingAs($creator)->get('/dashboard/users')->assertForbidden();
        $this->actingAs($creator)->post('/users/'.$target->id.'/toggle')->assertForbidden();
        $this->actingAs($creator)->post('/users/'.$target->id.'/delete')->assertForbidden();

        // صلاحيتا الإضافة والعرض متلازمان عبر السياسة أيضًا (فصل دقيق).
        $this->assertTrue($viewer->can('viewAny', User::class));
        $this->assertFalse($viewer->can('create', User::class));
        $this->assertFalse($viewer->can('delete', $target));
    }

    /* -------------------------------------------------- departments/colleges */

    public function test_department_abilities_are_split_view_vs_writes(): void
    {
        $viewer = $this->actorWith('dept_viewer', ['departments.view']);

        $this->assertTrue($viewer->can('viewAny', Department::class));
        $this->assertTrue($viewer->can('departments.view'));   // قدرة المسار (الشاشات/الكليات).
        $this->assertFalse($viewer->can('departments.create'));
        $this->assertFalse($viewer->can('departments.update'));
        $this->assertFalse($viewer->can('departments.delete'));

        $this->actingAs($viewer)->get('/departments')->assertOk();
    }

    /* ----------------------------------------------------- types/workflows */

    public function test_types_view_and_workflows_manage_are_separate(): void
    {
        $typeViewer = $this->actorWith('type_viewer', ['types.view']);
        $wfManager = $this->actorWith('wf_manager', ['workflows.manage']);

        // عارض الأنواع: شاشته مفتوحة، وسير الموافقات وكتابته مغلقان.
        $this->actingAs($typeViewer)->get('/types')->assertOk();
        $this->actingAs($typeViewer)->get('/workflows')->assertForbidden();
        $this->actingAs($typeViewer)->postJson('/types', [])->assertForbidden();

        // مدير السير: /workflows صفحة توافقية تعيد التوجيه إلى /types
        // (إدارة السير داخل شاشة الأنواع)، وأنواع المعاملات مغلقة لعدم
        // حمله types.view.
        $this->actingAs($wfManager)->get('/workflows')->assertRedirect('/types');
        $this->actingAs($wfManager)->get('/types')->assertForbidden();
    }

    /* ---------------------------------------------------------- trx.return */

    public function test_return_is_a_separate_ability_from_approve(): void
    {
        $dept = Department::factory()->create();
        $owner = User::factory()->create(['role' => 'employee', 'department_id' => $dept->id]);
        $trx = $this->makeTrx($owner, $dept->id);

        $approver = $this->actorWith('approver_only', ['trx.approve'], $dept->id);
        $returner = $this->actorWith('returner_only', ['trx.return'], $dept->id);

        // الموافقة والإرجاع قدرتان مستقلتان في الاتجاهين.
        $this->assertTrue($approver->can('approve', $trx));
        $this->assertFalse($approver->can('returnToSubmitter', $trx));

        $this->assertFalse($returner->can('approve', $trx));
        $this->assertTrue($returner->can('returnToSubmitter', $trx));

        // عبر HTTP: حامل trx.approve فقط يُمنع من نقطة الإرجاع (403)…
        $this->actingAs($approver)
            ->postJson('/transactions/'.$trx->transaction_number.'/return', [
                'reason' => 'نواقص تستكمالها',
                'missing' => 'المرفقات المطلوبة',
            ])
            ->assertForbidden();

        // …وحامل trx.return فقط ينجح (200) رغم غياب trx.approve.
        $this->actingAs($returner)
            ->postJson('/transactions/'.$trx->transaction_number.'/return', [
                'reason' => 'نواقص تستكمالها',
                'missing' => 'المرفقات المطلوبة',
            ])
            ->assertOk();
    }

    /* -------------------------------------------------------------- seeder */

    public function test_seeder_is_idempotent_and_preserves_user_passwords(): void
    {
        $user = User::factory()->create();
        $hash = $user->password;

        $perms = Permission::count();
        $roles = Role::count();
        $links = DB::table('model_has_roles')->count();

        (new RolesAndPermissionsSeeder())->run();
        (new RolesAndPermissionsSeeder())->run();

        // 21 صلاحية دقيقة + roles.manage = 22 — بلا نمو بعد التشغيل الأول.
        $this->assertSame(22, Permission::count());
        $this->assertSame($perms, Permission::count());
        $this->assertSame($roles, Role::count());
        $this->assertSame($links, DB::table('model_has_roles')->count());

        // السيدر لا يلمس جدول المستخدمين إطلاقًا.
        $this->assertSame($hash, User::find($user->id)->password);
    }

    /* ---------------------------------------------------------- lockout fix */

    public function test_system_admin_can_keep_roles_manage_when_updating(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::findByName('system_admin', 'web');

        // ما كان يُرفض بـ422 خطأً (whereIn('id', أسماء)) ينجح الآن مع الإبقاء.
        $this->actingAs($admin)
            ->postJson('/roles/'.$role->id.'/update', [
                'permissions' => array_merge(RolesAndPermissionsSeeder::PERMISSIONS, ['roles.manage']),
            ])
            ->assertOk();

        $this->assertTrue($role->fresh()->hasPermissionTo('roles.manage', 'web'));

        // وسحبها فعلًا ما يزال ممنوعًا من آخر دور يحملها (422).
        $this->actingAs($admin)
            ->postJson('/roles/'.$role->id.'/update', ['permissions' => RolesAndPermissionsSeeder::PERMISSIONS])
            ->assertStatus(422);
    }

    /* ---------------------------------------------------------- role title */

    public function test_role_title_is_persisted_and_shown_in_payload(): void
    {
        $admin = User::factory()->admin()->create();

        // النموذج يعرض حقل التسمية العربية (مدخل الاسم المطلوب).
        $this->actingAs($admin)->get('/roles/form')->assertSee('role-title-input');

        $this->actingAs($admin)->postJson('/roles', [
            'name' => 'auditor',
            'title' => 'مراجع تجاري',
            'permissions' => ['reports.view'],
        ])->assertOk();

        $this->assertDatabaseHas('roles', ['name' => 'auditor', 'title' => 'مراجع تجاري']);

        // التسمية تظهر كـ label في حمولة شاشة الأدوار (الـ JSON يُهرِّب
        // العربية كـ \uXXXX عبر @json).
        $this->assertStringContainsString(
            json_encode('مراجع تجاري'),
            $this->actingAs($admin)->get('/roles')->getContent()
        );

        // تعديل التسمية عبر نقطة التحديث.
        $id = Role::findByName('auditor', 'web')->id;
        $this->actingAs($admin)
            ->postJson('/roles/'.$id.'/update', ['title' => 'مدقق داخلي', 'permissions' => ['reports.view']])
            ->assertOk();

        $this->assertDatabaseHas('roles', ['name' => 'auditor', 'title' => 'مدقق داخلي']);
    }
}
