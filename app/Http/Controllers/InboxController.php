<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Support\ItqanData;
use App\Support\TransactionTypes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Real inbox: the personal action queue — transactions assigned to me or
 * forwarded to my department (pending) — filtered on the server.
 *
 * Every filter is ANDed with the personal scope, so search terms or id
 * manipulations can never widen visibility (IDOR-safe). Managers keep
 * their full view on the transactions page; their inbox stays a personal
 * queue (documented decision — one line to change).
 */
class InboxController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $this->filters($request);

        // Waiting indicator: pending items in scope, unaffected by filters.
        $waiting = $this->scope($user)->where('status', 'pending')->count();

        $query = $this->scope($user);

        // Status: '' (no param) = pending (the action queue), 'all' = every
        // status inside the scope, otherwise the exact status.
        $status = $filters['status'];
        if ($status === 'all') {
            // no status constraint
        } elseif ($status !== '') {
            $query->where('status', $status);
        } else {
            $query->where('status', 'pending');
        }

        if ($filters['type'] !== null) {
            $query->where('type', $filters['type']);
        }

        if ($filters['from'] !== null) {
            $query->whereDate('date', '>=', $filters['from']);
        }

        if ($filters['to'] !== null) {
            $query->whereDate('date', '<=', $filters['to']);
        }

        if ($filters['q'] !== null) {
            $like = '%'.addcslashes($filters['q'], '%_\\').'%';
            $query->where(function ($w) use ($like) {
                $w->where('transaction_number', 'like', $like)
                    ->orWhere('subject', 'like', $like)
                    ->orWhere('recipient', 'like', $like)
                    ->orWhere('sender', 'like', $like);
            });
        }

        // Phase 7: real pagination (25/page) replaces the old hard 500-row
        // cap; filters stay in the query string so the pager links keep them.
        $paginator = $query->with(['files', 'logs.user'])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $rows = $paginator->getCollection()
            ->map(fn (Transaction $t) => ItqanData::transaction($t))
            ->values()
            ->all();

        return $this->page('pages.inbox', [
            'inbox' => [
                'rows' => $rows,
                'waiting' => $waiting,
                'filters' => $filters['raw'],
                'page' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * Personal scope, always intersected with the visibility boundary.
     */
    private function scope(User $user): Builder
    {
        return Transaction::query()->visibleTo($user)->where(function ($w) use ($user) {
            $w->where('assigned_to', $user->id)
                ->orWhere(function ($d) use ($user) {
                    $d->where('dept_id', $user->department_id)
                        ->where('status', 'pending');
                });
        });
    }

    /**
     * Server-side filters: raw input is normalized; invalid values are
     * ignored (fall back to "no filter" / default pending) and can never
     * bypass the personal scope.
     *
     * @return array{q: ?string, type: ?string, from: ?string, to: ?string, status: string, raw: array<string, string>}
     */
    private function filters(Request $request): array
    {
        $raw = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'type' => (string) $request->query('type', ''),
            'from' => trim((string) $request->query('from', '')),
            'to' => trim((string) $request->query('to', '')),
        ];

        $status = in_array($raw['status'], ['pending', 'draft', 'returned', 'done', 'rejected', 'all'], true)
            ? $raw['status']
            : '';

        return [
            'q' => $raw['q'] !== '' ? mb_substr($raw['q'], 0, 100) : null,
            'type' => $raw['type'] !== '' ? TransactionTypes::slug($raw['type']) : null,
            'from' => $this->date($raw['from']),
            'to' => $this->date($raw['to']),
            'status' => $status,
            'raw' => $raw,
        ];
    }

    private function date(string $value): ?string
    {
        $normalized = str_replace(['-', '.'], '/', $value);

        if (! Carbon::hasFormat($normalized, 'Y/m/d')) {
            return null;
        }

        $date = Carbon::createFromFormat('Y/m/d', $normalized);

        // Reject overflow dates (e.g. 2026/13/40) that DateTime normalizes.
        return $date->format('Y/m/d') === $normalized ? $date->toDateString() : null;
    }
}
