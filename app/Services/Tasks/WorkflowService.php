<?php

namespace App\Services\Tasks;

use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\WorkflowSection;
use App\Models\WorkflowStepTemplate;
use App\Services\Auth\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkflowService
{
    public function __construct(private AuditLogService $auditLog) {}

    public function seedTasksForApplication(VisaApplication $application): void
    {
        if ($application->tasks()->exists()) {
            return;
        }

        // Prefer new section-based structure if present
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
                            'application_id' => $application->id,
                            'workflow_step_template_id' => null,
                            'workflow_task_id' => $workflowTask->id,
                            'position' => $position++,
                            'name' => $workflowTask->name,
                            'description' => $workflowTask->description,
                            'type' => $workflowTask->type,
                            'status' => 'pending',
                        ]);
                    }
                }
            } else {
                // Fallback: legacy flat templates
                $templates = WorkflowStepTemplate::where('visa_type_id', $application->visa_type_id)
                    ->orderBy('position')
                    ->get();

                if ($templates->isEmpty()) {
                    return;
                }

                foreach ($templates as $template) {
                    $tasks[] = ApplicationTask::create([
                        'application_id' => $application->id,
                        'workflow_step_template_id' => $template->id,
                        'position' => $template->position,
                        'name' => $template->name,
                        'description' => $template->description,
                        'type' => 'upload',
                        'status' => 'pending',
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

    public function advanceTask(ApplicationTask $task, ?string $note): void
    {
        DB::transaction(function () use ($task, $note): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be advanced.');
            }

            $task->update([
                'status' => 'approved',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', '>', $task->position)
                ->orderBy('position')
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            }
        });

        $this->auditLog->log('task_completed', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
    }

    public function approveTask(ApplicationTask $task, ?string $note): void
    {
        DB::transaction(function () use ($task, $note): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be approved.');
            }

            $task->update([
                'status' => 'approved',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', '>', $task->position)
                ->orderBy('position')
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            }
        });

        $this->auditLog->log('task_approved', $this->actor(), [
            'task' => $task->name,
            'reference' => $task->application->reference_number,
        ]);
    }

    public function rejectTask(ApplicationTask $task, ?string $note): void
    {
        DB::transaction(function () use ($task, $note): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be rejected.');
            }

            $task->update([
                'status' => 'rejected',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);
        });

        $this->auditLog->log('task_rejected', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
    }

    public function rejectTaskWithReason(ApplicationTask $task, string $reason): void
    {
        DB::transaction(function () use ($task, $reason): void {
            $task = ApplicationTask::lockForUpdate()->findOrFail($task->id);

            if ($task->status !== 'in_progress') {
                throw new InvalidArgumentException('Only an in_progress task can be rejected.');
            }

            $task->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'completed_at' => now(),
            ]);
        });

        $this->auditLog->log('task_rejected', $this->actor(), [
            'task' => $task->name,
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
                'status' => 'in_progress',
                'reviewer_note' => null,
                'rejection_reason' => null,
                'completed_at' => null,
            ]);
        });

        $this->auditLog->log('task_reopened', $this->actor(), [
            'task_id' => $task->id,
            'application_id' => $task->application_id,
            'task_name' => $task->name,
        ]);
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
