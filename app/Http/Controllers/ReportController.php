<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionTypes;
use Illuminate\Http\Request;

/**
 * Phase 10 — reports are computed on the SERVER over the caller's own
 * visibleTo() scope (the same boundary as every other screen), so the
 * figures no longer depend on the newest-500-row page payload. The CSV
 * export streams the exact row set behind those figures.
 *
 * التقارير: المسارات محمية ببوابة can:reports.view (مدير/مراجع)، والبيانات
 * تُحسب دائماً ضمن نطاق visibleTo() الخاص بالمُستدعي.
 */
class ReportController extends Controller
{
    /**
     * Reports page — gated by reports.view (مدير / مراجع). The page asks
     * the two endpoints below for its figures.
     */
    public function index()
    {
        return $this->page('pages.reports');
    }

    /**
     * GET /reports/data?from=YYYY-MM-DD&to=YYYY-MM-DD — aggregated report
     * figures for the caller's visible transactions inside the range
     * (same shapes reports.js already renders: stats + the five bars).
     */
    public function data(Request $request)
    {
        [$from, $to] = $this->range($request);

        $rows = Transaction::visibleTo($request->user())
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->get(['type', 'status']);

        // كل الحالات الخمس تُعرض حتى يتحقق: مكتملة+منتظارة+معادة+مسودة+
        // مرفوضة = الإجمالي (أرقام حقيقية من الجدول نفسه بلا استثناء).
        $stats = ['rejected' => 0, 'pending' => 0, 'approved' => 0, 'returned' => 0, 'draft' => 0, 'total' => $rows->count()];
        $bars = [0, 0, 0, 0, 0]; // conversion | purchase | bills | contract | أخرى

        foreach ($rows as $row) {
            if ($row->status === 'rejected') {
                $stats['rejected']++;
            } elseif ($row->status === 'pending') {
                $stats['pending']++;
            } elseif ($row->status === 'done') {
                $stats['approved']++;
            } elseif ($row->status === 'returned') {
                $stats['returned']++;
            } elseif ($row->status === 'draft') {
                $stats['draft']++;
            }

            $bars[TransactionTypes::CATEGORY[$row->type] ?? 4]++;
        }

        return $this->json(true, 'تم جلب بيانات التقرير', [
            'stats' => $stats,
            'bars' => $bars,
            'total' => $stats['total'],
        ]);
    }

    /**
     * GET /reports/export?from=&to= — CSV download of the caller's visible
     * transactions in the range (UTF-8 BOM so Excel renders the Arabic;
     * formula-injection-safe cells). Nothing is fabricated: same scope,
     * same range, same labels as the figures above.
     */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);

        $transactions = Transaction::visibleTo($request->user())
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->orderByDesc('id')
            ->get();

        $usernames = User::pluck('name', 'id');
        $departments = Department::pluck('name', 'id');

        // Excel treats a leading = + - @ (and tab) as a formula start —
        // prefix such cells with an apostrophe so they stay plain text.
        $safe = function (mixed $value): string {
            $value = (string) ($value ?? '');

            return preg_match('/^[=+\-@\t]/', $value) ? "'".$value : $value;
        };

        $file = 'itqan-transactions-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($transactions, $usernames, $departments, $safe) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM → Excel shows Arabic correctly
            fputcsv($out, [
                'رقم المعاملة', 'الموضوع', 'نوع المعاملة', 'الحالة', 'الأولوية',
                'تاريخ المعاملة', 'المُرسل', 'المُستلم', 'المبلغ',
                'القسم', 'المُنشئ', 'تاريخ الإنشاء',
            ]);

            foreach ($transactions as $t) {
                fputcsv($out, [
                    $safe($t->transaction_number),
                    $safe($t->subject),
                    $safe(TransactionTypes::label($t->type)),
                    TransactionTypes::statusLabel($t->status),
                    $safe($t->priority),
                    $t->date?->format('Y-m-d'),
                    $safe($t->sender),
                    $safe($t->recipient),
                    $safe($t->amount),
                    $safe($t->dept_id ? ($departments[$t->dept_id] ?? '') : ''),
                    $safe($t->created_by ? ($usernames[$t->created_by] ?? '') : ''),
                    $t->created_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Shared range parsing: both filters optional; reversed range is a
     * validation error through the standard JSON contract (422).
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', function ($attribute, $value, $fail) {
                if (request()->filled('from') && request()->input('from') > $value) {
                    $fail('تاريخ البداية يجب أن يكون قبل تاريخ النهاية.');
                }
            }],
        ]);

        return [$data['from'] ?? null, $data['to'] ?? null];
    }
}
