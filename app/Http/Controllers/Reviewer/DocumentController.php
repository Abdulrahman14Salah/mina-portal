<?php

namespace App\Http\Controllers\Reviewer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reviewer\UploadDocumentRequest;
use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $documentService)
    {
    }

    public function store(UploadDocumentRequest $request, VisaApplication $application): RedirectResponse
    {
        $this->authorize('reviewerUpload', Document::class);

        $task = ApplicationTask::findOrFail($request->integer('application_task_id'));
        abort_if($task->application_id !== $application->id, 404);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $this->documentService->upload($application, $task, $request->file('file'), $user, 'reviewer');

        return redirect()
            ->route('reviewer.applications.show', $application)
            ->with('success', __('reviewer.upload_success'));
    }
}
