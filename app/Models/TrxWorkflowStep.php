<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 5 — one approval step. Target is exactly one of: a department, a
 * role, or a specifically assigned employee (existing permission rules still
 * apply on top: trx.approve + visibility + never your own transaction).
 */
class TrxWorkflowStep extends Model
{
    use HasFactory;

    public const TARGET_TYPES = ['department', 'role', 'user'];

    protected $table = 'trx_workflow_steps';

    protected $fillable = [
        'workflow_id',
        'step_order',
        'name_ar',
        'name_en',
        'target_type',
        'target_dept_id',
        'target_role',
        'target_user_id',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(TrxWorkflow::class, 'workflow_id');
    }

    public function dept(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_dept_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /** Does this user match the step's target (dept / role / user)? */
    public function matches(User $user): bool
    {
        return match ($this->target_type) {
            'department' => $user->department_id !== null && $user->department_id === $this->target_dept_id,
            'role' => $user->role === $this->target_role,
            'user' => $user->id === $this->target_user_id,
            default => false,
        };
    }

    /** Human label for logs/notifications. */
    public function targetLabel(): string
    {
        return match ($this->target_type) {
            'department' => $this->dept?->name ?? 'قسم',
            'role' => \App\Support\Roles::label($this->target_role),
            'user' => $this->user?->name ?? 'موظف',
            default => '—',
        };
    }
}
