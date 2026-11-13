<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\ProgramLead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The trainer's lead inbox: prospective clients captured through free
 * program questionnaires, with conversion stats (a lead "converts" once
 * it registers an account).
 */
class LeadsController extends Controller
{
    /**
     * List leads newest-first plus headline stats.
     */
    public function index(Request $request): Response
    {
        $trainer = $request->user()->ensureTrainer();

        $leads = $trainer->leads()
            ->with('program:id,name')
            ->latest()
            ->limit(100)
            ->get();

        $weekAgo = now()->subWeek();
        $total = $trainer->leads()->count();
        $converted = $trainer->leads()->whereNotNull('user_id')->count();

        return Inertia::render('trainer/leads', [
            'stats' => [
                'new_this_week' => $leads->where('created_at', '>=', $weekAgo)->count(),
                'total' => $total,
                'converted' => $converted,
                'conversion_rate' => $total === 0 ? 0 : round($converted / $total * 100),
            ],
            'leads' => $leads->map(fn (ProgramLead $lead): array => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'program_name' => $lead->program?->name,
                'answers' => $lead->answers,
                'converted' => $lead->isConverted(),
                'created_at' => $lead->created_at,
            ]),
        ]);
    }
}
