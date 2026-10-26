<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

class PageController extends Controller
{
    /**
     * Show the dashboard home page with real statistics and the latest
     * transactions (visible-scope aware).
     */
    public function dashboard()
    {
        $user = auth()->user();
        $visible = Transaction::visibleTo($user);

        $stats = [
            'total' => (clone $visible)->count(),
            'done' => (clone $visible)->where('status', 'done')->count(),
            'pending' => (clone $visible)->where('status', 'pending')->count(),
            'rejected' => (clone $visible)->where('status', 'rejected')->count(),
        ];

        $recent = (clone $visible)->latest('id')->limit(6)->get();

        // أعمدة رسم «إحصائيات المعاملات»: آخر 6 أشهر فعلية بترتيب زمني
        // (الأقدم يسارًا) وعدّاد معاملات كل شهر حسب تاريخها (عمود date).
        $chart = [];
        for ($back = 5; $back >= 0; $back--) {
            $month = now()->subMonths($back);
            $chart[] = [
                'month' => (int) $month->month,
                'count' => (clone $visible)
                    ->whereBetween('date', [
                        $month->copy()->startOfMonth()->format('Y-m-d'),
                        $month->copy()->endOfMonth()->format('Y-m-d'),
                    ])
                    ->count(),
            ];
        }

        return $this->page('pages.dashboard-home', [
            'stats' => $stats,
            'recent' => $recent,
            'chart' => $chart,
        ]);
    }

    /**
     * Show the settings page (preferences stay client-side; password
     * changes are wired to SettingsController@password).
     */
    public function settings()
    {
        return $this->page('pages.settings');
    }
}
