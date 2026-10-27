<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Admin overview: headline stats, 30-day revenue/signup series, items
     * needing attention, and recent activity.
     */
    public function __invoke(): Response
    {
        $totalUsers = User::query()->count();
        $paidUsers = User::query()
            ->whereHas('payments', fn ($query) => $query->where('status', 'success'))
            ->count();

        $revenue7d = (float) Payment::query()
            ->where('status', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('amount');
        $revenuePrev7d = (float) Payment::query()
            ->where('status', 'success')
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->sum('amount');

        $newUsers7d = User::query()->where('created_at', '>=', now()->subDays(7))->count();
        $newUsersPrev7d = User::query()
            ->whereBetween('created_at', [now()->subDays(14), now()->subDays(7)])
            ->count();

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'total_users' => $totalUsers,
                'paid_users' => $paidUsers,
                'revenue_etb' => (float) Payment::query()->where('status', 'success')->sum('amount'),
                'revenue_7d' => $revenue7d,
                'revenue_prev_7d' => $revenuePrev7d,
                'new_users_7d' => $newUsers7d,
                'new_users_prev_7d' => $newUsersPrev7d,
                'conversion_pct' => $totalUsers > 0 ? round($paidUsers / $totalUsers * 100, 1) : 0.0,
            ],
            'series' => $this->series(),
            'attention' => [
                'pending_payments' => Payment::query()->where('status', 'pending')->count(),
                'failed_payments_7d' => Payment::query()
                    ->where('status', 'failed')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
            ],
            'recentPayments' => Payment::query()
                ->with('user:id,name,email')
                ->latest()
                ->limit(5)
                ->get(),
            'recentUsers' => User::query()
                ->select('id', 'name', 'email', 'created_at')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Daily signups and successful-payment revenue for the last 30 days,
     * gap-filled so the chart always renders a full series.
     *
     * @return Collection<int, array{date: string, signups: int, revenue: float}>
     */
    private function series(): Collection
    {
        $cutoff = now()->subDays(29)->startOfDay();

        $signups = User::query()
            ->where('created_at', '>=', $cutoff)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $revenue = Payment::query()
            ->where('status', 'success')
            ->where('created_at', '>=', $cutoff)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(29, 0))
            ->map(fn (int $i) => now()->subDays($i)->toDateString())
            ->map(fn (string $day) => [
                'date' => $day,
                'signups' => (int) ($signups[$day] ?? 0),
                'revenue' => (float) ($revenue[$day] ?? 0),
            ])
            ->values();
    }
}
