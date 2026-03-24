<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\AdvanceTaskRequest;
use App\Http\Requests\Reviewer\ApproveTaskRequest;
use App\Http\Requests\Reviewer\RejectTaskByIdRequest;
use App\Http\Requests\Reviewer\RejectTaskRequest;
use App\Http\Requests\Reviewer\ReopenTaskRequest;
use App\Models\ApplicationTask;
use App\Models\VisaApplication;
use App\Services\Tasks\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function __construct(private WorkflowService $workflowService) {}

    public function show(VisaApplication $application): View
    {
        $this->authorize('view', $application);

        $application->loadMissing(['visaType', 'user', 'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents' => fn ($d) => $d->with('uploader')])]);
        $activeTask = $application->tasks->firstWhere('status', 'pending_review');

        return view('reviewer.applications.show', compact('application', 'activeTask'));
    }

    public function advance(AdvanceTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        abort_if($task->application_id !== $application->id, 404);
        $this->authorize('advance', $task);
        $this->workflowService->advanceTask($task, $request->input('note'));

        return redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_advanced'));
    }

    public function approve(ApproveTaskRequest $request, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('approve', $task);
        $this->workflowService->approveTask($task, $request->input('note'));

        return redirect()
            ->route('reviewer.applications.show', $task->application_id)
            ->with('success', __('tasks.task_approved'));
    }

    public function reject(RejectTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        abort_if($task->application_id !== $application->id, 404);
        $this->authorize('reject', $task);
        $this->workflowService->rejectTask($task, $request->input('note'));

        return redirect()->route('reviewer.applications.show', $application)->with('success', __('reviewer.task_rejected'));
    }

    public function rejectById(RejectTaskByIdRequest $request, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('reject', $task);
        $this->workflowService->rejectTaskWithReason($task, $request->input('rejection_reason'));

        return redirect()
            ->route('reviewer.applications.show', $task->application_id)
            ->with('success', __('tasks.task_rejected'));
    }

    public function reopen(ReopenTaskRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        abort_if($task->application_id !== $application->id, 404);
        $this->authorize('reopen', $task);

        try {
            $this->workflowService->reopenTask($task);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('reviewer.applications.show', $application)
                ->with('error', $e->getMessage());
        }

        return redirect()->route('reviewer.applications.show', $application)->with('success', __('tasks.reopen_success'));
    }
}
