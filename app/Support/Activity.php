<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\User;

/**
 * Single write-path helper for the activity audit trail (activity_logs).
 *
 * Every administrative action funnels through log() so the audit table is
 * written consistently (user_id, action, target, note). The actor may be
 * null for pre-authentication events (e.g. a password-reset request), which
 * are recorded with a null user_id. TransactionService writes its own richer
 * per-transaction trail; this helper covers the global admin actions.
 *
 * مساعد موحّد لكتابة سجل النشاط: كل إجراء إداري يمرّ من هنا لضمان توحيد
 * صفوف activity_logs. المُستخدم قد يكون null لأحداث ما بعد المصادقة.
 */
class Activity
{
    public static function log(?User $actor, string $action, string $target, ?string $note = null): void
    {
        ActivityLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'target' => $target,
            'note' => $note,
        ]);
    }
}
