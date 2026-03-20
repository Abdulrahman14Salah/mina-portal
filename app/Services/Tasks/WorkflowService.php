<?php

namespace App\Services\Tasks;

use App\Models\ApplicationTask;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\WorkflowStepTemplate;
use App\Services\Auth\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkflowService
{
    public function __construct(private AuditLogService $auditLog)
    {
    }

    public function seedTasksForApplication(VisaApplication $application): void
    {
        if ($application->tasks()->exists()) {
            return;
        }

        $templates = WorkflowStepTemplate::where('visa_type_id', $application->visa_type_id)->orderBy('position')->get();

        if ($templates->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($application, $templates): void {
            $tasks = [];

            foreach ($templates as $template) {
                $tasks[] = ApplicationTask::create([
                    'application_id' => $application->id,
                    'workflow_step_template_id' => $template->id,
                    'position' => $template->position,
                    'name' => $template->name,
                    'description' => $template->description,
                    'status' => 'pending',
                ]);
            }

            $tasks[0]->update(['status' => 'in_progress']);
            $application->update(['status' => 'in_progress']);
        });

        $this->auditLog->log('workflow_started', $application->user()->first(), ['reference' => $application->reference_number]);
    }

    public function advanceTask(ApplicationTask $task, ?string $note): void
    {
        if ($task->status !== 'in_progress') {
            throw new InvalidArgumentException('Only an in_progress task can be advanced.');
        }

        DB::transaction(function () use ($task, $note): void {
            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);

            $nextTask = ApplicationTask::where('application_id', $task->application_id)
                ->where('position', $task->position + 1)
                ->first();

            if ($nextTask) {
                $nextTask->update(['status' => 'in_progress']);
            } else {
                $task->application->update(['status' => 'approved']);
                $this->auditLog->log('application_approved', $this->actor(), ['reference' => $task->application->reference_number]);
            }
        });

        $this->auditLog->log('task_completed', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
    }

    public function rejectTask(ApplicationTask $task, ?string $note): void
    {
        if ($task->status !== 'in_progress') {
            throw new InvalidArgumentException('Only an in_progress task can be rejected.');
        }

        DB::transaction(function () use ($task, $note): void {
            $task->update([
                'status' => 'rejected',
                'completed_at' => now(),
                'reviewer_note' => $note,
            ]);

            $task->application->update(['status' => 'rejected']);
        });

        $this->auditLog->log('task_rejected', $this->actor(), ['task' => $task->name, 'reference' => $task->application->reference_number]);
        $this->auditLog->log('application_rejected', $this->actor(), ['reference' => $task->application->reference_number]);
    }

    private function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
