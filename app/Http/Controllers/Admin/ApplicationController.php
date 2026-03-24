<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignReviewerRequest;
use App\Models\User;
use App\Models\VisaApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->input('search', '');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $statusFilter = $request->input('status', null);

        $allowedSorts = ['created_at', 'reference_number', 'status'];
        if (! in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $sortDir = in_array($sortDir, ['asc', 'desc']) ? $sortDir : 'desc';

        $query = VisaApplication::with(['user', 'visaType'])->withCount([
            'tasks',
            'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'approved'),
        ]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $query->orderBy($sortBy, $sortDir);

        $applications = $query->paginate(15)->withQueryString();

        $breadcrumbs = [
            ['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'],
            ['label' => __('admin.nav_applications'), 'route' => null],
        ];

        return view('admin.applications.index', compact('applications', 'search', 'sortBy', 'sortDir', 'breadcrumbs'));
    }

    public function show(VisaApplication $application): View
    {
        $this->authorize('view', $application);

        $application->loadMissing([
            'visaType',
            'user',
            'assignedReviewer',
            'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents']),
        ]);

        $reviewers = User::role('reviewer')->orderBy('name')->get(['id', 'name', 'email']);

        return view('admin.applications.show', compact('application', 'reviewers'));
    }

    public function assignReviewer(AssignReviewerRequest $request, VisaApplication $application): RedirectResponse
    {
        $this->authorize('assignReviewer', $application);

        $application->update(['assigned_reviewer_id' => $request->input('reviewer_id')]);

        return redirect()
            ->route('admin.applications.show', $application)
            ->with('success', __('admin.reviewer_assigned'));
    }
}
