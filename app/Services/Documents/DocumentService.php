<?php

namespace App\Services\Documents;

use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\User;
use App\Models\VisaApplication;
use App\Services\Auth\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class DocumentService
{
    public function __construct(private AuditLogService $auditLog) {}

    public function upload(VisaApplication $application, ?ApplicationTask $task, UploadedFile $file, User $uploader, string $sourceType = 'client'): Document
    {
        $storedFilename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $disk = config('filesystems.default', 'local');
        $path = $file->storeAs('documents/'.$application->id, $storedFilename, $disk);

        $document = Document::create([
            'application_id'      => $application->id,
            'application_task_id' => $task?->id,
            'uploaded_by'         => $uploader->id,
            'source_type'         => $sourceType,
            'original_filename'   => $file->getClientOriginalName(),
            'stored_filename'     => $storedFilename,
            'disk'                => $disk,
            'path'                => $path,
            'mime_type'           => $file->getMimeType() ?: 'application/octet-stream',
            'size'                => $file->getSize(),
        ]);

        $this->auditLog->log('document_uploaded', $uploader, ['document_id' => $document->id, 'reference' => $application->reference_number]);

        // Auto-reopen a rejected task when the client re-uploads
        if ($task !== null && $task->status === 'rejected' && $sourceType === 'client') {
            $task->update([
                'status'           => 'in_progress',
                'rejection_reason' => null,
            ]);
        }

        // Transition application to awaiting_documents when a client uploads
        if ($sourceType === 'client' && $application->status === 'in_progress') {
            $application->update(['status' => 'awaiting_documents']);
        }

        return $document;
    }

    public function delete(Document $document, User $actor): void
    {
        Storage::disk($document->disk)->delete($document->path);
        $document->delete();

        $this->auditLog->log('document_deleted', $actor, [
            'document_id' => $document->id,
            'filename'    => $document->original_filename,
        ]);
    }

    public function serve(Document $document, User $actor): StreamedResponse|RedirectResponse
    {
        $this->auditLog->log('document_downloaded', $actor, ['document_id' => $document->id, 'reference' => $document->application->reference_number]);

        if ($document->disk === 's3') {
            return Redirect::to(Storage::disk('s3')->temporaryUrl($document->path, now()->addMinutes(5)));
        }

        return Storage::disk($document->disk)->download($document->path, $document->original_filename);
    }

    /**
     * Archive all documents for a given application.
     * Sets archived_at = now() and expires_at = now() + 24 months on all documents.
     * Called when an application is closed/approved/rejected.
     */
    public function archiveDocumentsForApplication(VisaApplication $application): void
    {
        $application->documents()->whereNull('archived_at')->update([
            'archived_at' => now(),
            'expires_at'  => now()->addMonths(24),
        ]);

        // Also archive task-level documents
        $taskIds = $application->tasks()->pluck('id');
        if ($taskIds->isNotEmpty()) {
            Document::whereIn('application_task_id', $taskIds)
                ->whereNull('archived_at')
                ->update([
                    'archived_at' => now(),
                    'expires_at'  => now()->addMonths(24),
                ]);
        }
    }
}
