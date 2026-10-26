<?php
/*
3. تجهيز خدمات التطبيق
1. ربط سياسات الأدوار والصلاحيات

*/
namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\TrxType;
use App\Models\User;
use App\Support\TransactionTypes;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * All transaction state changes. Every operation runs inside one database
 * transaction (files are tracked so a rollback also cleans the disk) and
 * writes the forwarding history (transaction_transfers), the per-transaction
 * log (transaction_logs), notifications and the activity log.
 *
 * Workflow (matches the existing UI exactly — 4 statuses only):
 *   create  -> draft | pending
 *   edit    -> may promote draft -> pending ("حفظ وإرسال"); other statuses unchanged
 *   forward -> pending only
 *   approve -> pending -> done     reject -> pending -> rejected
 * Ambiguity documented for review: done/rejected stay editable because the UI
 * keeps the edit button visible for every status (same as the old demo).
 */
class TransactionService
{
    public function __construct(
        private readonly FileService $files,
        private readonly TypeConfigService $types,
        private readonly WorkflowService $workflows,
    ) {
    }

    /* ---------------------------------------------------------- create */

    /**
     * @param  array{type: string, subject: string, from: ?string, to: string, amount: float|string, date: string, desc: ?string, priority: string, status: string, files?: array<int, UploadedFile>, form_data?: string}  $data
     */
    public function create(array $data, User $actor): Transaction
    {
        $written = [];

        // Phase 4: resolve the (possibly admin-defined) type and validate its
        // dynamic form data ON THE SERVER before anything is written.
        $typeSlug = TransactionTypes::slug($data['type']);
        $trxType = $typeSlug ? \App\Models\TrxType::query()->where('slug', $typeSlug)->first() : null;
        $formData = TypeConfigService::decode($data['form_data'] ?? null);
        $uploadedNames = array_map(fn (UploadedFile $f) => $f->getClientOriginalName(), $data['files'] ?? []);

        $this->types->validateFormData($trxType, $formData, $uploadedNames, []);
        $this->checkRequiredDocuments($trxType, (string) ($data['status'] ?? ''), $uploadedNames, []);

        try {
            return DB::transaction(function () use ($data, $actor, $typeSlug, $formData, &$written) {
                $transaction = $this->createWithRetry(fn () => Transaction::create([
                    'transaction_number' => TransactionNumber::next(),
                    'subject' => $data['subject'],
                    'type' => $typeSlug,
                    'date' => \Illuminate\Support\Carbon::createFromFormat('Y/m/d', $data['date'])->toDateString(),
                    'status' => $data['status'],
                    'dept_id' => $actor->department_id,
                    'sender' => $data['from'] ?? null,
                    'recipient' => $data['to'],
                    'amount' => $data['amount'],
                    'description' => $data['desc'] ?? null,
                    'priority' => $data['priority'],
                    'created_by' => $actor->id,
                    'assigned_to' => null,
                    'form_data' => $formData === [] ? null : $formData,
                ]));

                $this->log($transaction, $actor, 'تم إنشاء المعاملة');
                if ($data['status'] === 'pending') {
                    $this->log($transaction, $actor, 'تم إرسال المعاملة');

                    // Phase 5: attach the workflow snapshot + route to step 1.
                    $routed = $this->workflows->attachOnSubmission($transaction, $actor);
                    $this->notifySubmitTargets($transaction, $actor, $routed);

                    if (! $routed) {
                        $this->notifyAdmins($transaction, 'معاملة جديدة بانتظار المراجعة',
                            ':actor أنشأ معاملة: :subject',
                            ['actor' => $actor->name, 'subject' => $transaction->subject], $actor->id);
                    }
                }

                foreach (($data['files'] ?? []) as $file) {
                    [$path] = $this->files->store($transaction, $file, $actor->id);
                    $written[] = $path;
                }

                $this->activity($actor,
                    $data['status'] === 'draft' ? 'حفظ معاملة كمسودة' : 'إنشاء وإرسال معاملة',
                    $transaction->transaction_number);

                return $transaction;
            });
        } catch (\Throwable $e) {
            $this->cleanup($written);
            throw $e;
        }
    }

    /* ---------------------------------------------------------- update */

    /**
     * @param  array{type: string, subject: string, from: ?string, to: string, amount: float|string, date: string, desc: ?string, priority: string, status?: string, files?: array<int, UploadedFile>, removed_files?: array<int, string>, form_data?: string}  $data
     */
    public function update(Transaction $transaction, array $data, User $actor): Transaction
    {
        $written = [];

        // Phase 4: same server-side dynamic validation on edit. Deactivated
        // types still resolve here so editing history never breaks.
        $typeSlug = TransactionTypes::slug($data['type']);
        $trxType = $typeSlug ? \App\Models\TrxType::query()->where('slug', $typeSlug)->first() : null;
        $formData = array_key_exists('form_data', $data)
            ? TypeConfigService::decode($data['form_data'])
            : $transaction->form_data ?? [];
        $uploadedNames = array_map(fn (UploadedFile $f) => $f->getClientOriginalName(), $data['files'] ?? []);
        $existingNames = $transaction->files()->pluck('original_name')->all();

        $this->types->validateFormData($trxType, $formData, $uploadedNames, $existingNames);

        // Required documents apply on (re)submission, not on plain edits.
        $resubmitting = in_array($transaction->status, ['draft', 'returned'], true)
            && ($data['status'] ?? null) === 'pending';
        $effectiveStatus = $resubmitting ? 'pending' : $transaction->status;
        $this->checkRequiredDocuments($trxType, $effectiveStatus, $uploadedNames, $existingNames);

        try {
            return DB::transaction(function () use ($transaction, $data, $actor, $typeSlug, $formData, &$written) {
                $transaction->update([
                    'subject' => $data['subject'],
                    'type' => $typeSlug,
                    'date' => \Illuminate\Support\Carbon::createFromFormat('Y/m/d', $data['date'])->toDateString(),
                    'sender' => $data['from'] ?? null,
                    'recipient' => $data['to'],
                    'amount' => $data['amount'],
                    'description' => $data['desc'] ?? null,
                    'priority' => $data['priority'],
                    'form_data' => $formData === [] ? null : $formData,
                ]);

                // draft/returned -> pending only when the edit form submits
                // "pending" (Phase 6: returned transactions resume the same
                // way — same workflow step, same notifications).
                if (in_array($transaction->status, ['draft', 'returned'], true) && ($data['status'] ?? null) === 'pending') {
                    $wasReturned = $transaction->status === 'returned';
                    $transaction->update(['status' => 'pending']);
                    $this->log($transaction, $actor, $wasReturned ? 'أعاد إرسال المعاملة بعد الاستكمال' : 'تم إرسال المعاملة');

                    // Phase 5: resume at the SAME snapshot step (or attach a
                    // fresh one when the draft predates any workflow).
                    $routed = $this->workflows->attachOnSubmission($transaction, $actor);
                    $this->notifySubmitTargets($transaction, $actor, $routed);

                    if (! $routed) {
                        $this->notifyAdmins($transaction,
                            $wasReturned ? 'معادة للاكمال أُرسلت بانتظار المراجعة' : 'مسودة أُرسلت بانتظار المراجعة',
                            $wasReturned ? ':actor أعاد إرسال معاملة بعد استكمال النواقص: :subject' : ':actor أرسل مسودة: :subject',
                            ['actor' => $actor->name, 'subject' => $transaction->subject], $actor->id);
                    }
                }

                foreach (($data['removed_files'] ?? []) as $name) {
                    foreach ($transaction->files()->where('original_name', $name)->get() as $file) {
                        $this->files->deleteFromDisk($file->stored_path);
                        $file->delete();
                    }
                }

                foreach (($data['files'] ?? []) as $file) {
                    [$path] = $this->files->store($transaction, $file, $actor->id);
                    $written[] = $path;
                }

                $this->log($transaction, $actor, 'تم تعديل المعاملة');
                $this->activity($actor, 'تعديل معاملة', $transaction->transaction_number);

                return $transaction;
            });
        } catch (\Throwable $e) {
            $this->cleanup($written);
            throw $e;
        }
    }

    /* --------------------------------------------------------- forward */

    public function forward(Transaction $transaction, int $deptId, ?int $userId, ?string $note, User $actor): Transaction
    {
        if ($transaction->status !== 'pending') {
            abort(409, 'الإحالة متاحة للمعاملات قيد الانتظار فقط');
        }

        return DB::transaction(function () use ($transaction, $deptId, $userId, $note, $actor) {
            $fromDept = $transaction->dept_id;
            $fromUser = $transaction->assigned_to;

            $transaction->update(['dept_id' => $deptId, 'assigned_to' => $userId]);

            $transaction->transfers()->create([
                'from_user_id' => $fromUser,
                'to_user_id' => $userId,
                'from_dept_id' => $fromDept,
                'to_dept_id' => $deptId,
                'note' => $note,
            ]);

            $deptName = $transaction->department?->name ?? 'قسم';
            $userName = $userId ? optional($transaction->assignee)->name : null;
            $action = 'تمت إحالة المعاملة إلى '.$deptName.($userName ? ' — '.$userName : '');
            $this->log($transaction, $actor, $action, $note);

            $link = '/transactions/details?id='.$transaction->transaction_number;
            $targets = $userId ? [$userId] : \App\Models\User::where('status', 'active')
                ->where('department_id', $deptId)->pluck('id')->all();
            $this->notify($targets, 'إحالة معاملة', 'تمت إحالة المعاملة إلى :dept',
                ['dept' => $deptName.($userName ? ' — '.$userName : '')], $link, $transaction, $actor->id);

            $this->activity($actor, 'إحالة معاملة', $transaction->transaction_number);

            return $transaction;
        });
    }

    /* ---------------------------------------------------- approve/reject */

    public function approve(Transaction $transaction, User $actor): Transaction
    {
        return $this->decide($transaction, $actor, 'done', 'تمت الموافقة', 'موافقة على معاملة', null);
    }

    public function reject(Transaction $transaction, string $reason, User $actor): Transaction
    {
        return $this->decide($transaction, $actor, 'rejected', 'تم رفض المعاملة', 'رفض معاملة', $reason);
    }

    /**
     * Phase 5 — decisions respect the workflow snapshot:
     * - approve on a NON-final step records the decision, advances
     *   current_step, re-routes to the next target and STAYS pending;
     *   only the final step sets status=done.
     * - reject is a final decision at any step (existing semantics kept).
     * - the step target is re-checked UNDER the row lock, so a concurrent
     *   decision can never slip past the step that changed meanwhile.
     */
    private function decide(Transaction $transaction, User $actor, string $status, string $logAction, string $activityAction, ?string $reason): Transaction
    {
        if ($transaction->status !== 'pending') {
            abort(409, 'هذه الإجراءات متاحة للمعاملات قيد الانتظار فقط');
        }

        return DB::transaction(function () use ($transaction, $actor, $status, $logAction, $activityAction, $reason) {
            // Phase 5 — non-final workflow approval (stays pending).
            if ($status === 'done' && $this->workflows->hasWorkflow($transaction)) {
                $outcome = $this->workflows->advance($transaction, $actor, 'approve', null);

                if ($outcome === 'advanced') {
                    $d = $transaction->workflow_data;
                    $label = $this->workflows->currentStepLabel($transaction);

                    $this->log($transaction, $actor,
                        'اعتماد خطوة '.($d['current_step'] - 1).'/'.$d['total'].' — انتقلت إلى: '.$label);

                    $this->notifyWorkflowActors($transaction, $actor,
                        'معاملة بانتظار اعتمادك في خطوة السير',
                        'اعتُمدت خطوة سابقة في المعاملة: :subject — الخطوة الحالية: :step',
                        ['subject' => $transaction->subject, 'step' => $label]);

                    $this->activity($actor, 'اعتماد خطوة سير الموافقات', $transaction->transaction_number);

                    return $transaction;
                }
                // 'final' → fall through to the normal done path below.
            }

            // Phase 5 — record final/reject decisions in the workflow audit.
            if ($status === 'rejected') {
                $this->workflows->record($transaction, $actor, 'reject', $reason);
            }

            $transaction->update(['status' => $status]);
            $this->log($transaction, $actor, $logAction, $reason);

            if ($transaction->created_by) {
                $this->notify([$transaction->created_by],
                    $status === 'done' ? 'تمت الموافقة على معاملتك' : 'تم رفض معاملتك',
                    $status === 'done' ? 'تمت الموافقة على معاملة: :subject' : 'تم رفض معاملة: :subject — :reason',
                    ['subject' => $transaction->subject, 'reason' => $reason ?? ''],
                    '/transactions/details?id='.$transaction->transaction_number,
                    $transaction, $actor->id);
            }

            $this->activity($actor, $activityAction, $transaction->transaction_number);

            return $transaction;
        });
    }

    /* --------------------------------------------- return for completion */

    /**
     * Return a pending transaction to its submitter so they can complete
     * the missing items. Phase 6: the transaction gets its DEDICATED
     * "returned" status (not "draft") so the submitter — and everyone
     * watching — can tell a fresh draft apart from one that was sent back
     * with missing items. Approvals/forwarding stay blocked because every
     * decision path requires status === "pending"; the submitter edits and
     * re-sends (returned -> pending in update()), which resumes the SAME
     * workflow step. Full history stays in transaction_logs.
     */
    public function returnToSubmitter(Transaction $transaction, string $reason, string $missing, User $actor): Transaction
    {
        if ($transaction->status !== 'pending') {
            abort(409, 'إعادة المعاملة لاستكمال النواقص متاحة للمعاملات قيد الانتظار فقط');
        }

        return DB::transaction(function () use ($transaction, $reason, $missing, $actor) {
            // Phase 5: keep the snapshot, record the return decision (the
            // submitter resumes at the SAME step — never re-pathed).
            $this->workflows->record($transaction, $actor, 'return', $reason);

            $transaction->update(['status' => 'returned']);

            $this->log(
                $transaction,
                $actor,
                'أعاد المعاملة إلى مقدم الطلب لاستكمال النواقص',
                $reason.' — '.__('المطلوب استكماله').': '.$missing
            );

            if ($transaction->created_by) {
                $this->notify([$transaction->created_by],
                    'المعاملة تحتاج استكمالاً',
                    'أعاد :actor معاملتك للاستكمال: :subject — السبب: :reason',
                    ['actor' => $actor->name, 'subject' => $transaction->subject, 'reason' => $reason],
                    '/transactions/details?id='.$transaction->transaction_number,
                    $transaction, $actor->id);
            }

            $this->activity($actor, 'إعادة معاملة لاستكمال', $transaction->transaction_number);

            return $transaction;
        });
    }

    /* ----------------------------------------------------------- delete */

    public function destroy(Transaction $transaction, User $actor): void
    {
        DB::transaction(function () use ($transaction, $actor) {
            foreach ($transaction->files as $file) {
                $this->files->deleteFromDisk($file->stored_path);
            }

            $number = $transaction->transaction_number;
            $transaction->delete(); // cascades logs, transfers, files, notifications

            $this->activity($actor, 'حذف معاملة', $number);
        });
    }

    /* ---------------------------------------------------------- helpers */

    /**
     * Phase 4 — when a type lists required documents, submission (pending)
     * must carry at least one attachment; drafts are exempt: documents may
     * follow before the submitter sends the transaction.
     *
     * @param  array<int, string>  $uploadedNames
     * @param  array<int, string>  $existingNames
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function checkRequiredDocuments(?TrxType $type, string $status, array $uploadedNames, array $existingNames): void
    {
        if ($type === null || empty($type->required_documents) || $status !== 'pending') {
            return;
        }

        if ($uploadedNames === [] && $existingNames === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'files' => __('هذه المعاملة تتطلب إرفاق: :docs', [
                    'docs' => implode('، ', (array) $type->required_documents),
                ]),
            ]);
        }
    }

    private function createWithRetry(callable $factory, int $attempts = 5): Transaction
    {
        for ($i = 0; $i < $attempts; $i++) {
            try {
                return $factory();
            } catch (QueryException $e) {
                if ($i === $attempts - 1) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('تعذر توليد رقم المعاملة');
    }

    private function log(Transaction $transaction, User $actor, string $action, ?string $note = null): void
    {
        $transaction->logs()->create([
            'user_id' => $actor->id,
            'action' => $action,
            'note' => $note,
        ]);
    }

    private function activity(User $actor, string $action, string $target): void
    {
        ActivityLog::create(['user_id' => $actor->id, 'action' => $action, 'target' => $target]);
    }

    /**
     * Insert notifications in each recipient's own language.
     *
     * @param  array<int, int>  $userIds
     * @param  array<string, string>  $replaces
     */
    private function notify(array $userIds, string $titleKey, string $bodyKey, array $replaces, string $link, Transaction $transaction, int $actorId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0 && $id !== $actorId)));

        if ($ids === []) {
            return;
        }

        $locales = User::whereIn('id', $ids)->pluck('locale', 'id');

        foreach ($ids as $userId) {
            $locale = $locales->get($userId) ?? 'ar';

            Notification::create([
                'user_id' => $userId,
                'transaction_id' => $transaction->id,
                'title' => __($titleKey, [], $locale),
                'body' => __($bodyKey, $replaces, $locale),
                'link' => $link,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $replaces
     */
    private function notifyAdmins(Transaction $transaction, string $titleKey, string $bodyKey, array $replaces, int $actorId): void
    {
        $admins = User::where('status', 'active')
            ->whereIn('role', [\App\Support\Roles::SYSTEM_ADMIN, \App\Support\Roles::UNIVERSITY_ADMIN, \App\Support\Roles::DEPT_MANAGER])
            ->pluck('id')->all();

        $this->notify($admins, $titleKey, $bodyKey, $replaces,
            '/transactions/details?id='.$transaction->transaction_number,
            $transaction, $actorId);
    }

    /**
     * Phase 5 — notify the users who must act on the transaction's CURRENT
     * workflow step (falls back to admins when no workflow applies).
     *
     * @param  array<string, string>  $replaces
     */
    private function notifyWorkflowActors(Transaction $transaction, User $actor, string $titleKey, string $bodyKey, array $replaces): void
    {
        $this->notify($this->workflows->stepApproverIds($transaction), $titleKey, $bodyKey, $replaces,
            '/transactions/details?id='.$transaction->transaction_number,
            $transaction, $actor->id);
    }

    /**
     * Phase 5 — on submission: when a workflow snapshot attached, the first
     * step's actors are the real inbox owners; otherwise keep the legacy
     * admin broadcast exactly as before.
     */
    private function notifySubmitTargets(Transaction $transaction, User $actor, bool $routed): void
    {
        if (! $routed) {
            return; // caller falls back to notifyAdmins()
        }

        $d = $transaction->workflow_data;
        $label = $this->workflows->currentStepLabel($transaction);

        $this->log($transaction, $actor,
            'بدأ سير الموافقات — الخطوة 1/'.$d['total'].': '.$label);

        $this->notifyWorkflowActors($transaction, $actor,
            'معاملة جديدة بانتظار اعتمادك',
            'أُرسلت معاملة: :subject — أنت على الخطوة: :step',
            ['subject' => $transaction->subject, 'step' => $label]);
    }

    /**
     * Best-effort disk cleanup when the surrounding DB transaction rolls back.
     *
     * @param  array<int, string>  $paths
     */
    private function cleanup(array $paths): void
    {
        foreach ($paths as $path) {
            $this->files->deleteFromDisk($path);
        }
    }
}
