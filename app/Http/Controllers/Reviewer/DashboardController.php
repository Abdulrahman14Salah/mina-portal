<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\ApplicationTask;
use App\Models\VisaApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(string $tab = 'applications'): View
    {
        $validTabs = ['applications'];

        if (! in_array($tab, $validTabs, true)) {
            $tab = 'applications';
        }

        $user = Auth::user();

        $applications = $tab === 'applications'
            ? VisaApplication::with(['visaType', 'user', 'tasks' => fn ($q) => $q->whereIn('status', ['in_progress', 'pending_review'])])
                ->where('assigned_reviewer_id', $user->id)
                ->whereIn('status', ['pending_review', 'in_progress'])
                ->orderBy('created_at')
                ->get()
            : collect();

        $pendingReviewCount = ApplicationTask::whereHas('application', function ($q) use ($user) {
                $q->where('assigned_reviewer_id', $user->id);
            })
            ->where('status', 'pending_review')
            ->count();

        return view('reviewer.dashboard.index', compact('tab', 'applications', 'pendingReviewCount'));
    }
}
