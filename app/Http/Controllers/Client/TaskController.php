<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\SubmitTaskAnswersRequest;
use App\Http\Requests\Client\UploadReceiptRequest;
use App\Models\ApplicationTask;
use App\Models\VisaApplication;
use App\Services\Tasks\TaskAnswerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TaskController extends Controller
{
    public function __construct(private TaskAnswerService $taskAnswerService) {}

    public function show(VisaApplication $application, ApplicationTask $task): View
    {
        $this->authorize('view', $application);

        abort_if($task->application_id !== $application->id, 404);

        $task->loadMissing('documents');

        return view('client.tasks.show', compact('application', 'task'));
    }

    public function submitAnswers(SubmitTaskAnswersRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_if($task->application_id !== $application->id, 404);

        $this->taskAnswerService->submitAnswers($task, $request->input('answers'), Auth::user());

        return redirect()->back()->with('success', __('tasks.answers_submitted'));
    }

    public function uploadReceipt(UploadReceiptRequest $request, VisaApplication $application, ApplicationTask $task): RedirectResponse
    {
        $this->authorize('view', $application);
        abort_if($task->application_id !== $application->id, 404);

        $this->taskAnswerService->uploadReceipt($task, $request->file('receipt'), Auth::user());

        return redirect()->back()->with('success', __('tasks.receipt_uploaded'));
    }
}
