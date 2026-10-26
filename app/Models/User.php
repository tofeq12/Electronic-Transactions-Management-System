<?php

namespace App\Models;

use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            // wasRecentlyCreated تغطّي الإنشاء، وwasChanged تغطّي تعديل role.
            // syncRoles يستبدل الدور السابق (لا تراكم) لضمان اتساق الجداول.
            if ($user->role !== null
                && ($user->wasRecentlyCreated || $user->wasChanged('role'))) {
                // دور غير مسجّل (مثل دور مجهول/مستقبلي) ⇒ بلا أي دور.
                // النتيجة fail-secure: المستخدم بلا صلاحيات فيُرفض بـ403.
                $role = \Spatie\Permission\Models\Role::where('name', $user->role)
                    ->where('guard_name', 'web')
                    ->first();

                $user->syncRoles($role ? [$role] : []);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'phone',
        'role',
        'status',
        'college_id',
        'department_id',
        'avatar_path',
        'locale',
        'remember_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'avatar_data',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /* ---------------------------------------------------- relations */

    public function college(): BelongsTo
    {
        return $this->belongsTo(College::class);
    }


    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'created_by');
    }

    public function assignedTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'assigned_to');
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /* ---------------------------------------------------- helpers */

    /**
     * هل الحساب مفعّل؟ (status === 'active'). تُستدعى عند تسجيل الدخول
     * لرفض الحسابات غير المفعّلة، وسيط 'active' يتحقّق منها لكل طلب محمي.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * هل الدور من أدوار المدير؟ (system_admin / university_admin /
     * dept_manager) — تُستخدم لحماية "آخر مسؤول" من الحذف/التخفيض
     * داخل UserController.
     *
     * المرحلة 3: الفحص عبر أدوار Spatie (hasAnyRole) لا عمود role — نفس
     * المصدر الذي تفحصه صلاحيات المسارات، ويُطابقه User::booted() عند كل
     * حفظ للنموذج.
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole([
            Roles::SYSTEM_ADMIN,
            Roles::UNIVERSITY_ADMIN,
            Roles::DEPT_MANAGER,
        ]);
    }

    /**
     * Full data scope: system/university admins, plus a department manager
     * who is not assigned to any department (acts at university level).
     * A department manager WITH a department is scoped to that department.
     *
     * هل يرى المستخدم كل بيانات المعاملات؟ (المدير أو مدير القسم بلا قسم) —
     * يُستخدم داخل Transaction::visibleTo() لتحديد نطاق الرؤية.
     */
    public function seesEverything(): bool
    {
        return Roles::seesEverything($this);
    }

    /**
     * Authenticated avatar URL served from private storage (cache-busted).
     */
    public function getAvatarUrlAttribute(): string
    {
        return '/profile/avatar?v='.($this->updated_at?->getTimestamp() ?? 0);
    }

    /**
     * Arabic label used by the existing UI (sidebar, tables, role selects).
     * ترجمة slug الدور إلى العربية (مدير / موظف / مراجع) عبر Roles::label().
     */
    public function getRoleLabelAttribute(): string
    {
        return Roles::label($this->role);
    }
}
