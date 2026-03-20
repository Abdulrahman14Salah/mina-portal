<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminUploadDocumentRequest;
use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DocumentController extends Controller
{
    public function __construct(private DocumentService $documentService)
    {
    }

    public function index(VisaApplication $application): View
    {
        $this->authorize('adminUpload', Document::class);

        $application->loadMissing(['user', 'visaType', 'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents' => fn ($d) => $d->with('uploader')])]);

        return view('admin.applications.documents', compact('application'));
    }

    public function store(AdminUploadDocumentRequest $request, VisaApplication $application): RedirectResponse
    {
        $this->authorize('adminUpload', Document::class);

        $task = ApplicationTask::findOrFail($request->integer('application_task_id'));
        abort_if($task->application_id !== $application->id, 404);

        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $this->documentService->upload($application, $task, $request->file('file'), $user);

        return redirect()->route('admin.applications.documents.index', $application)->with('success', __('documents.admin_upload_success'));
    }
}
