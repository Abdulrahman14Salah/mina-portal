<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UploadDocumentRequest;
use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $documentService)
    {
    }

    public function store(UploadDocumentRequest $request): RedirectResponse
    {
        $this->authorize('upload', Document::class);

        $task = ApplicationTask::with('application')->findOrFail($request->integer('application_task_id'));
        abort_if($task->application->user_id !== Auth::id(), 403);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $this->documentService->upload($task->application, $task, $request->file('file'), $user);

        return redirect()->route('client.dashboard', ['tab' => 'documents'])->with('success', __('documents.upload_success'));
    }
}
