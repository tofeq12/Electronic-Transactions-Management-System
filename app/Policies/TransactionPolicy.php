<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

/**
 * Transaction authorization. Ability checks mirror public/js/access.js;
 * data boundaries are enforced here (managers see everything, employees see
 * their department / created / assigned transactions — provisional rule,
 * documented for approval).
 *
 * سياسة المعاملات: تجمع بين قدرة الدور (create / edit / forward / approve /
 * delete عبر Spatie $user->can) وحدود البيانات (view). الحد الفاصل للرؤية هو
 * view(): المدير يرى كل شيء، والموظف/المراجع يرون ما أنشؤوه أو أُسند إليهم
 * أو ما يخصّ قسمهم فقط. كل طرق الكتابة تستدعي view() إضافة إلى القدرة، فلا
 * يتعديل أحد سجلاً خارج نطاقه (حماية IDOR). الموظف يملك قدرات الإنشاء
 * والتحرير والإحالة، والمراجع لا يملك سوى المشاهدة فلا تظهر له هذه الطرق.
 */
class TransactionPolicy
{
    /** كل مستخدم مسجّل يمكنه رؤية شاشة المعاملات (النطاق الفعلي يُضبط في view()). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * حد الرؤية لسجل واحد: المدير يرى كل شيء؛ وإلا فمن أنشأ السجل أو أُسند
     * إليه؛ وإلا فمن كان له قسم مطابق لقسم السجل.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        if ($user->seesEverything()) {
            return true;
        }

        if ($transaction->created_by === $user->id || $transaction->assigned_to === $user->id) {
            return true;
        }

        return $user->department_id !== null && $transaction->dept_id === $user->department_id;
    }

    /** إنشاء معاملة — قدرة trx.create (الموظف والمدير، لا المراجع). */
    public function create(User $user): bool
    {
        return $user->can('trx.create');
    }

    /** تعديل — قدرة trx.edit + أن يكون السجل ضمن نطاق الرؤية. */
    public function update(User $user, Transaction $transaction): bool
    {
        return $user->can('trx.edit') && $this->view($user, $transaction);
    }

    /** إحالة — قدرة trx.forward + الحالة pending + ضمن النطاق. */
    public function forward(User $user, Transaction $transaction): bool
    {
        return $user->can('trx.forward')
            && $transaction->status === 'pending'
            && $this->view($user, $transaction);
    }

    /**
     * Approve (done) or reject — both use the trx.approve ability.
     * A creator can never decide on their own transaction, regardless of
     * role (a manager must have a second pair of eyes).
     *
     * الموافقة أو الرفض — قدرة trx.approve (المدير) + الحالة pending +
     * ألا يكون قائد المعاملة هو نفسه المقرّر (فصل المصالح) + ضمن النطاق.
     */
    public function approve(User $user, Transaction $transaction): bool
    {
        return $user->can('trx.approve')
            && $transaction->status === 'pending'
            && $transaction->created_by !== $user->id
            && $this->view($user, $transaction);
    }

    /**
     * Return a pending transaction to its submitter for completion — its
     * own granular ability (trx.return), same shape as approve (pending
     * only, never on your own transaction, inside the visibility scope).
     *
     * الإرجاع للإكمال — قدرة trx.return المستقلة عن trx.approve، بنفس
     * شروط الموافقة (pending فقط / لا على معاملتك أنت / ضمن النطاق).
     */
    public function returnToSubmitter(User $user, Transaction $transaction): bool
    {
        return $user->can('trx.return')
            && $transaction->status === 'pending'
            && $transaction->created_by !== $user->id
            && $this->view($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        // Ability alone is not enough: a department-scoped manager must not
        // delete transactions outside their visibility boundary (IDOR).
        // القدرة وحدها لا تكفي: يجب أن يكون السجل ضمن نطاق الرؤية أيضاً.
        return $user->can('trx.delete')
            && $this->view($user, $transaction);
    }

    /**
     * فتح صفحة الإحالة (GET) — قدرة trx.forward فقط؛ قواعد الاستحقاق
     * الفعلية (الحالة/النطاق) تُفرض عند الحفظ بـ forward().
     */
    public function forwardPage(User $user): bool
    {
        return $user->can('trx.forward');
    }

    /**
     * شاشة الاستعجال (GET) — قدرة urgent.view؛ البيانات المعروضة تبقى
     * داخل نطاق visibleTo() في list().
     */
    public function urgentList(User $user): bool
    {
        return $user->can('urgent.view');
    }

    /**
     * تنزيل مرفقات معاملة — يشترط فقط رؤية المعاملة نفسها (نفس حد view()).
     */
    public function download(User $user, Transaction $transaction): bool
    {
        return $this->view($user, $transaction);
    }
}
