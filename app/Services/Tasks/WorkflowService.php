<?php

namespace App\Services\Tasks;

use App\Mail\TaskSubmittedForReviewMail;
use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\WorkflowSection;
use App\Models\WorkflowStepTemplate;
use App\Services\Auth\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class WorkflowService
{
    public function __construct(private AuditLogService $auditLog) {}

    public function seedTasksForApplication(VisaApplication $application): void
    {
        if ($application->tasks()->exists()) {
            return;
        }

        $hasSections = WorkflowSection::where('visa_type_id', $application->visa_type_id)->exists();

        $seeded = false;

        DB::transaction(function () use ($application, $hasSections, &$seeded): void {
            $tasks = [];
            $position = 1;

            if ($hasSections) {
                $sections = WorkflowSection::where('visa_type_id', $application->visa_type_id)
                    ->orderBy('position')
                    ->with(['tasks' => fn ($q) => $q->orderBy('position')])
                    ->get();

                foreach ($sections as $section) {
                    foreach ($section->tasks as $workflowTask) {
                        $tasks[] = ApplicationTask::create([
                            'application_id'           => $application->id,
                            'workflow_step_template_id' => null,
                            'workflow_task_id'          => $workflowTask->id,
                            'position'                 => $position++,
                            'name'                     => $workflowTask->name,
                            'description'              => $workflowTask->description,
                            'type'                     => $workflowTask->type,
                            'approval_mode'            => $workflowTask->approval_mode,
                            'status'                   => 'pending',
                        ]);
                    }
                }
            } else {
                $templates = WorkflowStepTemplate::where('visa_type_id', $application->visa_type_id)
                    ->orderBy('position')
                    ->get();

                if ($templates->isEmpty()) {
                    return;
                }

                foreach ($templates as $template) {
                    $tasks[] = ApplicationTask::create([
                        'application_id'           => $application->id,
                        'workflow_step_template_id' => $template->id,
                        'position'                 => $template->position,
                        'name'                     => $template->name,
                        'description'              => $template->description,
                        'type'                     => 'upload',
                        'status'                   => 'pending',
                    ]);
                }
            }

            if (! empty($tasks)) {
                $tasks[0]->update(['status' => 'in_progress']);
                $application->update(['status' => 'in_progress']);
                $seeded = true;
            }
        });

        if ($seeded) {
            $this->auditLog->log('workflow_started', $application->user()->first(), ['reference' => $application->reference_number]);
        }
    }

    /**
     * Client explicitly submits a task for reviewer attention.
     * Transitions: in_progress → pending_review
     * Sends email notification to assigned reviewer.
     */
    public function submitForReview(ApplicationTask $task): void
    {
        DB::transaction(function () use ($task): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be submitted for review.');
            }

            $task->update(['status' => 'pending_review']);
        });

        $this->auditLog->log('task_submitted_for_review', $this->actor(), [
            'task_id'    => $task->id,
            'task_name'  => $task->name,
            'reference'  => $task->application->reference_number,
        ]);

        $reviewer = $task->application->assignedReviewer;

        if ($reviewer) {
            Mail::to($reviewer->email)->queue(new TaskSubmittedForReviewMail($task));
        }
    }

    /**
     * Auto-completes a question task when approval_mode = 'auto'.
     * Transitions: in_progress → approved (bypasses pending_review entirely).
     * Called internally by TaskAnswerService after answer submission.
     */
    public function autoCompleteTask(ApplicationTask $task): void
    {
        $workflowComplete = false;

        DB::transaction(function () use ($task, &$workflowComplete): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be auto-completed.');
            }

            $task->update([
                'status'      => 'approved',
                'completed_at' => now(),
                'reviewed_at' => now(),
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', '>', $task->position)
                ->orderBy('position')
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            } else {
                $task->application->update(['status' => 'workflow_complete']);
                $workflowComplete = true;
            }
        });

        $this->auditLog->log('task_auto_completed', $this->actor(), [
            'task'      => $task->name,
            'reference' => $task->application->reference_number,
        ]);

        if ($workflowComplete) {
            $this->auditLog->log('workflow_tasks_complete', $this->actor(), [
                'reference' => $task->application->reference_number,
            ]);
        }
    }

    public function advanceTask(ApplicationTask $task, ?string $note): void
    {
        $workflowComplete = false;

        DB::transaction(function () use ($task, $note, &$workflowComplete): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be advanced.');
            }

            $task->update([
                'status'      => 'approved',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', '>', $task->position)
                ->orderBy('position')
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            } else {
                $task->application->update(['status' => 'workflow_complete']);
                $workflowComplete = true;
            }
        });

        $this->auditLog->log('task_completed', $this->actor(), [
            'task'      => $task->name,
            'reference' => $task->application->reference_number,
        ]);

        if ($workflowComplete) {
            $this->auditLog->log('workflow_tasks_complete', $this->actor(), [
                'reference' => $task->application->reference_number,
            ]);
        }
    }

    /**
     * Reviewer approves a task.
     * Requires: pending_review status.
     * Sets reviewed_by and reviewed_at for audit trail.
     */
    public function approveTask(ApplicationTask $task, ?string $note): void
    {
        $workflowComplete = false;
        $actor = $this->actor();

        DB::transaction(function () use ($task, $note, $actor, &$workflowComplete): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'pending_review') {
                throw new InvalidArgumentException('Only a pending_review task can be approved.');
            }

            $task->update([
                'status'        => 'approved',
                'completed_at'  => now(),
                'reviewer_note' => $note,
                'reviewed_by'   => $actor?->id,
                'reviewed_at'   => now(),
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', '>', $task->position)
                ->orderBy('position')
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            } else {
                $task->application->update(['status' => 'workflow_complete']);
                $workflowComplete = true;
            }
        });

        $this->auditLog->log('task_approved', $actor, [
            'task'      => $task->name,
            'reference' => $task->application->reference_number,
        ]);

        if ($workflowComplete) {
            $this->auditLog->log('workflow_tasks_complete', $actor, [
                'reference' => $task->application->reference_number,
            ]);
        }
    }

    public function rejectTask(ApplicationTask $task, ?string $note): void
    {
        DB::transaction(function () use ($task, $note): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be rejected.');
            }

            $task->update([
                'status'       => 'rejected',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);
        });

        $this->auditLog->log('task_rejected', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
    }

    /**
     * Reviewer rejects a task with a reason.
     * Requires: pending_review status.
     * Sets reviewed_by and reviewed_at for audit trail.
     */
    public function rejectTaskWithReason(ApplicationTask $task, string $reason): void
    {
        $actor = $this->actor();

        DB::transaction(function () use ($task, $reason, $actor): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'pending_review') {
                throw new InvalidArgumentException('Only a pending_review task can be rejected.');
            }

            $task->update([
                'status'           => 'rejected',
                'rejection_reason' => $reason,
                'completed_at'     => now(),
                'reviewed_by'      => $actor?->id,
                'reviewed_at'      => now(),
            ]);
        });

        $this->auditLog->log('task_rejected', $actor, [
            'task'      => $task->name,
            'reference' => $task->application->reference_number,
        ]);
    }

    public function reopenTask(ApplicationTask $task): void
    {
        DB::transaction(function () use ($task): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'rejected') {
                throw new InvalidArgumentException('Only a rejected task can be re-opened.');
            }

            $task->update([
                'status'           => 'in_progress',
                'reviewer_note'    => null,
                'rejection_reason' => null,
                'completed_at'     => null,
            ]);
        });

        $this->auditLog->log('task_reopened', $this->actor(), [
            'task_id'        => $task->id,
            'application_id' => $task->application_id,
            'task_name'      => $task->name,
        ]);
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
