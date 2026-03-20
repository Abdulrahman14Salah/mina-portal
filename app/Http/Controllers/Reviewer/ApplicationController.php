<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\AdvanceTaskRequest;
use App\Http\Requests\Reviewer\RejectTaskRequest;
use App\Models\ApplicationTask;
use App\Models\VisaApplication;
use App\Services\Tasks\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function __construct(private WorkflowService $workflowService)
    {
    }

    public function show(VisaApplication $application): View
    {
        $application->loadMissing(['visaType', 'user', 'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents' => fn ($d) => $d->with('uploader')])]);
        $activeTask = $application->tasks->firstWhere('status', 'in_progress');

        return view('reviewer.applications.show', compact('application', 'activeTask'));
    }

    public function advance(AdvanceTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        abort_if($task->application_id !== $application->id, 404);
        $this->authorize('advance', $task);
        $this->workflowService->advanceTask($task, $request->input('note'));

        $successMsg = $application->fresh()->status === 'approved'
            ? __('reviewer.application_approved')
            : __('reviewer.task_advanced');

        return redirect()->route('reviewer.applications.show', $application)->with('success', $successMsg);
    }

    public function reject(RejectTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        abort_if($task->application_id !== $application->id, 404);
        $this->authorize('reject', $task);
        $this->workflowService->rejectTask($task, $request->input('note'));

        return redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_rejected'));
    }
}
