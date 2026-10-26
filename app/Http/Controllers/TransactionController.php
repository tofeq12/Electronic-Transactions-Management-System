<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transaction\ForwardTransactionRequest;
use App\Http\Requests\Transaction\RejectTransactionRequest;
use App\Http\Requests\Transaction\ReturnTransactionRequest;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Requests\Transaction\UpdateTransactionRequest;
use App\Models\Transaction;
use App\Services\TransactionService;
use App\Support\ItqanData;
use Illuminate\Http\Request;

/**
 * معاملات: كل طرق الكتابة تستدعي $this->authorize(...) بسياسة
 * TransactionPolicy (قدرة الدور + حدود الرؤية view()). قراءة البيانات تمرّ
 * دائماً عبر Transaction::visibleTo() (ضمن list()/view() وحِمل
 * ItqanData) فلا يرى المستخدم سجلاً خارج نطاقه. شاشتا الاستعجال والإحالة
 * تمرّان عبر قدرتَي السياسة urgentList/forwardPage (توحيد الأسلوب).
 */
class TransactionController extends Controller
{
    public function __construct(private readonly TransactionService $service)
    {
    }

    /* ----------------------------------------------------------- pages */

    public function index()
    {
        return $this->page('pages.transactions');
    }

    public function create()
    {
        $this->authorize('create', Transaction::class);

        return $this->page('pages.transaction-new');
    }

    public function edit(Request $request)
    {
        if ($request->filled('id')) {
            $transaction = $this->find($request->query('id'));
            $this->authorize('update', $transaction);
        }

        return $this->page('pages.transaction-edit');
    }

    public function show(Request $request)
    {
        if ($request->filled('id')) {
            $transaction = $this->find($request->query('id'));
            $this->authorize('view', $transaction);
        }

        return $this->page('pages.transaction-details');
    }

    public function forwardPage(Request $request)
    {
        if ($request->filled('id')) {
            $transaction = $this->find($request->query('id'));
            $this->authorize('view', $transaction);
            $this->authorize('forwardPage', Transaction::class);
        }

        return $this->page('pages.transaction-forward');
    }

    public function urgent()
    {
        // Server-side gate for urgent.view — now unified through
        // TransactionPolicy::urgentList() like every other screen (the old
        // inline abort_unless removed in the Spatie unification pass).
        $this->authorize('urgentList', Transaction::class);

        return $this->page('pages.urgent');
    }

    /* --------------------------------------------------------- actions */

    public function store(StoreTransactionRequest $request)
    {
        $this->authorize('create', Transaction::class);

        $transaction = $this->service->create($request->validated(), $request->user());

        if ($request->expectsJson()) {
            return $this->json(true, 'تم حفظ المعاملة بنجاح', [
                'redirect' => '/transactions/details?id='.$transaction->transaction_number,
            ]);
        }

        return $this->redirect('/transactions', 'تم حفظ المعاملة بنجاح');
    }

    public function update(UpdateTransactionRequest $request, string $id)
    {
        $transaction = $this->find($id);
        $this->authorize('update', $transaction);

        $transaction = $this->service->update($transaction, $request->validated(), $request->user());

        if ($request->expectsJson()) {
            return $this->json(true, 'تم حفظ التعديلات', [
                'redirect' => '/transactions/details?id='.$transaction->transaction_number,
            ]);
        }

        return $this->redirect('/transactions', 'تم حفظ التعديلات');
    }

    public function forward(ForwardTransactionRequest $request, string $id)
    {
        $transaction = $this->find($id);
        $this->authorize('forward', $transaction);

        $validated = $request->validated();
        $this->service->forward(
            $transaction,
            (int) $validated['dept_id'],
            ! empty($validated['user_id']) ? (int) $validated['user_id'] : null,
            $validated['note'] ?? null,
            $request->user(),
        );

        if ($request->expectsJson()) {
            return $this->json(true, 'تمت إحالة المعاملة', [
                'redirect' => '/transactions/details?id='.$transaction->transaction_number,
            ]);
        }

        return $this->redirect('/transactions', 'تمت إحالة المعاملة');
    }

    public function approve(Request $request, string $id)
    {
        $transaction = $this->find($id);
        $this->authorize('approve', $transaction);

        $this->service->approve($transaction, $request->user());

        return $this->json(true, 'تمت الموافقة على المعاملة', [
            'status' => 'done',
        ]);
    }

    public function reject(RejectTransactionRequest $request, string $id)
    {
        $transaction = $this->find($id);
        $this->authorize('approve', $transaction);

        $this->service->reject($transaction, (string) $request->validated()['reason'], $request->user());

        return $this->json(true, 'تم رفض المعاملة', [
            'status' => 'rejected',
        ]);
    }

    public function returnToSubmitter(ReturnTransactionRequest $request, string $id)
    {
        $transaction = $this->find($id);
        $this->authorize('returnToSubmitter', $transaction);

        $validated = $request->validated();
        $this->service->returnToSubmitter(
            $transaction,
            (string) $validated['reason'],
            (string) $validated['missing'],
            $request->user(),
        );

        return $this->json(true, 'أُعيدت المعاملة إلى مقدم الطلب لاستكمال النواقص', [
            'status' => 'returned',
        ]);
    }

    /* ------------------------------------------------ Phase 7 — paging */

    /**
     * GET /transactions/list — server-side pagination for the transactions
     * table (the page payload ships the newest 500; older rows are pulled
     * from here). Same visibility boundary as the payload: visibleTo().
     */
    public function list(Request $request)
    {
        $user = $request->user();
        $perPage = 25;
        $page = max(1, (int) $request->query('page', 1));

        $rows = Transaction::visibleTo($user)
            ->with(['files', 'logs.user'])
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn (Transaction $t) => ItqanData::transaction($t))
            ->values()
            ->all();

        $total = Transaction::visibleTo($user)->count();

        return $this->json(true, 'تم جلب البيانات', [
            'rows' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ]);
    }

    /**
     * GET /transactions/view?id= — one transaction as JSON. Used by the
     * details/edit screens when the record is OLDER than the 500-row page
     * payload. The `id` is the transaction NUMBER (the frontend contract —
     * payload "id" === transaction_number). IDOR-safe: same visibleTo()
     * boundary, 404 otherwise.
     */
    public function view(Request $request)
    {
        $transaction = Transaction::visibleTo($request->user())
            ->where('transaction_number', (string) $request->query('id', ''))
            ->first();

        abort_if(! $transaction, 404);

        return $this->json(true, 'تم جلب المعاملة', ItqanData::transaction($transaction));
    }

    /* ----------------------------------------------------------- delete */

    public function destroy(Request $request, string $id)
    {
        $transaction = $this->find($id);
        $this->authorize('delete', $transaction);

        $this->service->destroy($transaction, $request->user());

        if ($request->expectsJson()) {
            return $this->json(true, 'تم حذف المعاملة');
        }

        return $this->redirect('/transactions', 'تم حذف المعاملة');
    }

    /* ---------------------------------------------------------- helpers */

    private function find(string $number): Transaction
    {
        return Transaction::where('transaction_number', $number)->firstOrFail();
    }
}
