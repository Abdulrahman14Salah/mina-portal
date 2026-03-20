<?php

namespace App\Console\Commands;

use App\Models\VisaApplication;
use App\Services\Tasks\WorkflowService;
use Illuminate\Console\Command;

class SeedApplicationWorkflowTasks extends Command
{
    protected $signature = 'workflow:seed-tasks {--application= : Seed tasks for a specific application ID only}';

    protected $description = 'Seed workflow tasks onto existing applications that have no tasks yet';

    public function __construct(private WorkflowService $workflowService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('application')) {
            $application = VisaApplication::find($this->option('application'));

            if (! $application) {
                $this->error('Application not found.');

                return self::FAILURE;
            }

            $this->workflowService->seedTasksForApplication($application);
            $this->info("Seeded tasks for {$application->reference_number}.");

            return self::SUCCESS;
        }

        $applications = VisaApplication::whereDoesntHave('tasks')->get();
        $count = 0;

        foreach ($applications as $application) {
            try {
                $this->workflowService->seedTasksForApplication($application);
                $this->info("Seeded: {$application->reference_number}");
                $count++;
            } catch (\Throwable $e) {
                $this->warn("Skipped {$application->reference_number}: {$e->getMessage()}");
            }
        }

        $this->info("Done. {$count} applications processed.");

        return self::SUCCESS;
    }
}
