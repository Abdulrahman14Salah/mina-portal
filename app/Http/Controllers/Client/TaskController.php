<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SubmitTaskForReviewRequest;
use App\Http\Requests\Client\SubmitTaskAnswersRequest;
use App\Http\Requests\Client\UploadReceiptRequest;
use App\Models\ApplicationTask;
use App\Models\VisaApplication;
use App\Services\Tasks\TaskAnswerService;
use App\Services\Tasks\WorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(
        private TaskAnswerService $taskAnswerService,
        private WorkflowService $workflowService,
    ) {}

    public function show(VisaApplication $application, ApplicationTask $task): View|RedirectResponse
    {
        if ($task->status === 'pending') {
            return redirect()->route('client.dashboard');
        }

        $this->authorize('view', $application);

        abort_if($task->application_id !== $application->id, 404);

        $task->loadMissing(['documents', 'answers', 'workflowTask.questions']);

        $answers = $task->answers->keyBy('task_question_id');

        return view('client.tasks.show', compact('application', 'task', 'answers'));
    }

    public function submitAnswers(SubmitTaskAnswersRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_if($task->application_id !== $application->id, 404);

        if ($task->status === 'pending') {
            return redirect()->back()->with('error', __('tasks.task_locked'));
        }

        $this->taskAnswerService->submitAnswers($task, $request->input('answers'), Auth::user());

        return redirect()->back()->with('success', __('tasks.answers_submitted'));
    }

    public function uploadReceipt(UploadReceiptRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_if($task->application_id !== $application->id, 404);

        if ($task->status === 'pending') {
            return redirect()->back()->with('error', __('tasks.task_locked'));
        }

        $this->taskAnswerService->uploadReceipt($task, $request->file('receipt'), Auth::user());

        return redirect()->back()->with('success', __('tasks.receipt_uploaded'));
    }

    public function submitForReview(SubmitTaskForReviewRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        $this->authorize('submitForReview', $task);
        abort_if($task->application_id !== $application->id, 404);

        try {
            $this->workflowService->submitForReview($task);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', __('tasks.submitted_for_review'));
    }
}
