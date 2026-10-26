<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Models\User;

/**
 * Builds window.ITQAN_DATA — the exact shape the existing frontend expects
 * (see public/js/data.js, tables.js, departments.js, activity-page.js).
 *
 * Type values are translated to the Arabic labels the UI renders and
 * submits; statuses stay the English keys used by data.js STATUS.
 * Users are reduced to {id,name,status} for non-managers (forward screen
 * needs only that); full records are injected on user-management pages.
 */
class ItqanData
{
    public static function for(User $user, bool $withFullUsers = false): array
    {
        return [
            'currentUser' => self::currentUser($user),
            // Phase 7: the page payload ships the NEWEST 500 visible rows
            // (fast first paint on every page). Older rows are pulled on
            // demand: the transactions table "load more" button uses
            // GET /transactions/list, and details/edit of an old record
            // falls back to GET /transactions/view?id=.
            'transactions' => Transaction::visibleTo($user)
                ->with(['files', 'logs.user'])
                ->orderByDesc('id')
                ->limit(500)
                ->get()
                ->map(fn (Transaction $t) => self::transaction($t))
                ->values()
                ->all(),
            'users' => self::users($withFullUsers),
            'colleges' => \App\Models\College::orderBy('name')
                ->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
                ->values()
                ->all(),
            'departments' => \App\Models\Department::orderBy('name')
                ->get()
                ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name, 'desc' => $d->description, 'collegeId' => $d->college_id])
                ->values()
                ->all(),
            // Data boundary: the activity stream names actions on other
            // people's transactions — only injected for roles allowed to
            // open the activity log page (see can:activity.view route gate).
            'activity' => $user->can('activity.view') ? self::activity() : [],
            'unread' => \App\Models\Notification::where('user_id', $user->id)->whereNull('read_at')->count(),
            // Phase 4: active types + their active fields so the create/edit
            // forms can render dynamic inputs (server still revalidates).
            'trxTypes' => (new \App\Services\TypeConfigService)->activeTypes()
                ->map(fn ($t) => [
                    'slug' => $t->slug,
                    'label' => app()->isLocale('en') ? ($t->name_en ?: $t->name_ar) : $t->name_ar,
                    'desc' => $t->description,
                    'requiredDocs' => (array) ($t->required_documents ?? []),
                    'fields' => $t->activeFields->map(fn ($f) => [
                        'id' => $f->id,
                        'label' => app()->isLocale('en') ? ($f->label_en ?: $f->label_ar) : $f->label_ar,
                        'type' => $f->field_type,
                        'required' => $f->is_required,
                        'options' => array_map(fn ($o) => is_array($o) ? ($o['ar'] ?? reset($o)) : $o, (array) ($f->options ?? [])),
                        'min' => $f->validation['min'] ?? null,
                        'max' => $f->validation['max'] ?? null,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            // Phase 4 admin page: the FULL list (system + custom, active +
            // inactive) with counts and flags — only for type managers.
            'allTrxTypes' => $user->can('types.manage')
                ? \App\Models\TrxType::query()->withCount('fields')->orderBy('sort_order')->orderBy('id')
                    ->get()
                    ->map(fn ($t) => [                        'id' => $t->id,
                        'slug' => $t->slug,
                        'nameAr' => $t->name_ar,
                        'nameEn' => $t->name_en,
                        'desc' => $t->description,
                        'requiredDocs' => (array) ($t->required_documents ?? []),
                        'active' => (bool) $t->is_active,
                        'system' => (bool) $t->is_system,
                        'sort' => $t->sort_order,
                        'fieldsCount' => (int) $t->fields_count,
                        'fields' => $t->fields()->orderBy('sort_order')->orderBy('id')
                            ->get()
                            ->map(fn ($f) => [
                                'id' => $f->id,
                                'label' => $f->label_ar,
                                'labelEn' => $f->label_en,
                                'type' => $f->field_type,
                                'required' => (bool) $f->is_required,
                                'active' => (bool) $f->is_active,
                                'options' => array_map(fn ($o) => is_array($o) ? ($o['ar'] ?? reset($o)) : $o, (array) ($f->options ?? [])),
                                'validation' => (array) ($f->validation ?? []),
                                'sort' => $f->sort_order,
                            ])->values()->all(),
                    ])->values()->all()
                : [],
            // Phase 5 admin page: workflow config (types.manage only).
            'workflows' => $user->can('types.manage')
                ? \App\Models\TrxWorkflow::query()->with(['steps.dept', 'steps.user', 'type'])->orderBy('id')
                    ->get()
                    ->map(fn ($w) => [
                        'id' => $w->id,
                        'typeId' => $w->trx_type_id,
                        'typeName' => $w->type?->name_ar ?? '—',
                        'name' => $w->name,
                        'active' => (bool) $w->is_active,
                        'version' => (int) $w->version,
                        'steps' => $w->steps->map(fn ($s) => [
                            'id' => $s->id,
                            'order' => (int) $s->step_order,
                            'nameAr' => $s->name_ar,
                            'nameEn' => $s->name_en,
                            'targetType' => $s->target_type,
                            'targetDeptId' => $s->target_dept_id,
                            'targetDept' => $s->dept?->name,
                            'targetRole' => $s->target_role,
                            'targetRoleLabel' => $s->target_role ? Roles::label($s->target_role) : null,
                            'targetUserId' => $s->target_user_id,
                            'targetUser' => $s->user?->name,
                        ])->values()->all(),
                    ])->values()->all()
                : [],
            // Phase 8 — شاشة الأدوار والصلاحيات: تُحقن فقط لمن يملك
            // roles.manage (مدير النظام). الواجهة تعرض كل الأدوار وعدد
            // المستخدمين وصلاحياتها، وتدرّج الصلاحيات حسب المجموعة.
            'roles' => $user->can('roles.manage') ? self::roles() : [],
            'permissions' => $user->can('roles.manage') ? self::permissions() : [],
        ];
    }

    public static function currentUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username ?? '',
            'email' => $user->email,
            'phone' => $user->phone ?? '',
            'role' => Roles::label($user->role),
            'roleSlug' => $user->role,
            'locale' => $user->locale ?? 'ar',
            'avatar' => $user->avatar_path ?: null,
        ];
    }

    public static function transaction(Transaction $t): array
    {
        return [
            'id' => $t->transaction_number,
            'subject' => $t->subject,
            'type' => TransactionTypes::label($t->type),
            'typeSlug' => $t->type,
            'formData' => $t->form_data ?? [],
            'date' => $t->date?->format('Y/m/d') ?? '',
            'time' => $t->created_at?->format('H:i') ?? '',
            'status' => $t->status,
            'priority' => $t->priority ?? 'normal',
            'from' => $t->sender ?? '',
            'to' => $t->recipient ?? '',
            'amount' => number_format((float) $t->amount, 2, '.', ','),
            'desc' => $t->description ?? '',
            'deptId' => $t->dept_id,
            'assignedTo' => $t->assigned_to,
            'files' => $t->files->pluck('original_name')->values()->all(),
            'fileMeta' => $t->files->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->original_name,
                'url' => '/files/'.$f->id.'/download',
            ])->values()->all(),
            'log' => $t->logs->map(fn ($l) => [
                't' => $l->action,
                'by' => $l->user?->name ?? '—',
                'at' => $l->created_at?->format('Y/m/d - H:i') ?? '',
                'n' => $l->note,
            ])->values()->all(),
            // Phase 5: workflow progress (immutable snapshot; null = the
            // legacy single-step path — approve/reject as before).
            'workflow' => (new \App\Services\WorkflowService)->progressFor($t),
        ];
    }

    public static function users(bool $full): array
    {
        $query = User::orderBy('id');

        return $query->get()->map(function (User $u) use ($full) {
            if (! $full) {
                return ['id' => $u->id, 'name' => $u->name, 'status' => $u->status];
            }

            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username ?? '',
                'email' => $u->email,
                'phone' => $u->phone ?? '',
                'status' => $u->status,
                'role' => Roles::label($u->role),
                'roleSlug' => $u->role,
                'collegeId' => $u->college_id,
                'departmentId' => $u->department_id,
            ];
        })->values()->all();
    }

    public static function activity(): array
    {
        return ActivityLog::with('user')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn ($a) => [
                'at' => $a->created_at?->format('Y/m/d - H:i') ?? '',
                'by' => $a->user?->name ?? '—',
                'action' => $a->action,
                'target' => $a->target ?? '—',
                'note' => $a->note,
            ])
            ->values()
            ->all();
    }

    /**
     * كل الأدوار (الأساسية + المخصّصة) مع عدد المستخدمين وقائمة صلاحياتها.
     * مُستخدَمة في شاشة الأدوار (/roles) ونموذج إضافة/تعديل دور.
     */
    public static function roles(): array
    {
        return \Spatie\Permission\Models\Role::withCount('users')->orderBy('name')->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'title' => $r->title,
                'label' => $r->title ?: Roles::label($r->name),
                'usersCount' => (int) $r->users_count,
                'permissions' => $r->permissions()->pluck('name')->values()->all(),
                'system' => in_array($r->name, Roles::ALL, true),
            ])
            ->values()
            ->all();
    }

    /**
     * كل الصلاحيات مع عدد الأدوار التي تستخدمها ومجموعتها (لعرض متدرّج).
     * مُستخدَمة في شاشة الصلاحيات (/permissions).
     */
    public static function permissions(): array
    {
        // التجميع حسب البادئة (dot notation) — صلاحية لكل إجراء، والمجموعة
        // تُشتق تلقائيًا فلا تُهمل صلاحية جديدة.
        $prefixes = [
            'users' => 'المستخدمين',
            'roles' => 'الأدوار والصلاحيات',
            'departments' => 'الأقسام والكليات',
            'types' => 'أنواع المعاملات',
            'workflows' => 'سير الموافقات',
            'reports' => 'التقارير',
            'activity' => 'سجل النشاطات',
            'urgent' => 'العاجلة والمتأخرة',
            'trx' => 'المعاملات',
        ];

        return \Spatie\Permission\Models\Permission::withCount('roles')->orderBy('name')->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'group' => $prefixes[explode('.', $p->name)[0]] ?? 'أخرى',
                'rolesCount' => (int) $p->roles_count,
                'system' => in_array($p->name, array_merge(
                    \Database\Seeders\RolesAndPermissionsSeeder::PERMISSIONS,
                    [\Database\Seeders\RolesAndPermissionsSeeder::ROLES_MANAGE],
                ), true),
            ])
            ->values()
            ->all();
    }
}
