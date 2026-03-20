<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Models\VisaApplication;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function show(string $tab = 'applications'): View
    {
        $validTabs = ['applications'];

        if (! in_array($tab, $validTabs, true)) {
            $tab = 'applications';
        }

        $applications = $tab === 'applications'
            ? VisaApplication::with(['visaType', 'user', 'tasks' => fn ($q) => $q->where('status', 'in_progress')])
                ->whereIn('status', ['pending_review', 'in_progress'])
                ->orderBy('created_at')
                ->get()
            : collect();

        return view('reviewer.dashboard.index', compact('tab', 'applications'));
    }
}
