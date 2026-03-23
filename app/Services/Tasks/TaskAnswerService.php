<?php

namespace App\Services\Tasks;

use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\TaskAnswer;
use App\Models\User;
use App\Services\Auth\AuditLogService;
use App\Services\Documents\DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TaskAnswerService
{
    public function __construct(
        private AuditLogService $auditLog,
        private DocumentService $documentService,
    ) {}

    public function submitAnswers(ApplicationTask $task, array $answers, User $client): void
    {
        if ($task->status !== 'in_progress') {
            throw new InvalidArgumentException('Only an in_progress task can accept answer submissions.');
        }

        DB::transaction(function () use ($task, $answers): void {
            foreach ($answers as $questionId => $answerText) {
                TaskAnswer::updateOrCreate(
                    ['application_task_id' => $task->id, 'task_question_id' => (int) $questionId],
                    ['answer' => $answerText]
                );
            }
        });

        $this->auditLog->log('task_answers_submitted', $client, [
            'task_id'   => $task->id,
            'reference' => $task->application->reference_number,
        ]);
    }

    public function uploadReceipt(ApplicationTask $task, UploadedFile $file, User $client): Document
    {
        if ($task->status !== 'in_progress') {
            throw new InvalidArgumentException('Only an in_progress task can receive a receipt upload.');
        }

        $existing = $task->documents()
            ->where('source_type', 'client')
            ->whereNull('archived_at')
            ->first();

        if ($existing) {
            $this->documentService->delete($existing, $client);
        }

        return $this->documentService->upload(
            $task->application,
            $task,
            $file,
            $client,
            'client'
        );
    }
}
