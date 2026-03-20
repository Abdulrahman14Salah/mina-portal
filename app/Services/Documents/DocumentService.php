<?php

namespace App\Services\Documents;

use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\Auth\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentService
{
    public function __construct(private AuditLogService $auditLog)
    {
    }

    public function upload(VisaApplication $application, ApplicationTask $task, UploadedFile $file, User $uploader, string $sourceType = 'client'): Document
    {
        $storedFilename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $disk = config('filesystems.default', 'local');
        $path = $file->storeAs('documents/' . $application->id, $storedFilename, $disk);

        $document = Document::create([
            'application_id' => $application->id,
            'application_task_id' => $task->id,
            'uploaded_by' => $uploader->id,
            'source_type' => $sourceType,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
        ]);

        VisaApplication::whereKey($application->id)->where('status', 'in_progress')->update(['status' => 'awaiting_documents']);

        $this->auditLog->log('document_uploaded', $uploader, ['document_id' => $document->id, 'reference' => $application->reference_number]);

        return $document;
    }

    public function serve(Document $document, User $actor): StreamedResponse|RedirectResponse
    {
        $this->auditLog->log('document_downloaded', $actor, ['document_id' => $document->id, 'reference' => $document->application->reference_number]);

        if ($document->disk === 's3') {
            return Redirect::to(Storage::disk('s3')->temporaryUrl($document->path, now()->addMinutes(5)));
        }

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }
}
