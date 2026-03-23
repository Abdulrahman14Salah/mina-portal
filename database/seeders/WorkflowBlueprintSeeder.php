<?php

namespace Database\Seeders;

use App\Models\VisaType;
use App\Models\WorkflowSection;
use App\Models\WorkflowTask;
use Illuminate\Database\Seeder;

class WorkflowBlueprintSeeder extends Seeder
{
    public function run(): void
    {
        $touristVisa = VisaType::where('name', 'Tourist Visa')->first();

        if (! $touristVisa) {
            return;
        }

        $blueprint = [
            [
                'name'     => 'Personal Information',
                'position' => 1,
                'tasks'    => [
                    ['name' => 'Complete Personal Details',  'type' => 'question', 'position' => 1, 'description' => 'Fill in all required personal information for your visa application.'],
                    ['name' => 'Identity Verification Info', 'type' => 'info',     'position' => 2, 'description' => 'Review the identity documents required for your application.'],
                ],
            ],
            [
                'name'     => 'Documentation',
                'position' => 2,
                'tasks'    => [
                    ['name' => 'Application Fee Payment',           'type' => 'payment', 'position' => 1, 'description' => 'Pay the application processing fee and upload your payment receipt.'],
                    ['name' => 'Review Documentation Requirements', 'type' => 'info',    'position' => 2, 'description' => 'Review the list of supporting documents you must prepare.'],
                ],
            ],
            [
                'name'     => 'Interview Preparation',
                'position' => 3,
                'tasks'    => [
                    ['name' => 'Pre-Interview Questionnaire', 'type' => 'question', 'position' => 1, 'description' => 'Answer pre-interview questions to prepare your application for review.'],
                    ['name' => 'Interview Instructions',      'type' => 'info',     'position' => 2, 'description' => 'Read your interview guidelines and scheduled instructions.'],
                ],
            ],
            [
                'name'     => 'Final Submission',
                'position' => 4,
                'tasks'    => [
                    ['name' => 'Final Payment',           'type' => 'payment', 'position' => 1, 'description' => 'Pay the final visa issuance fee and upload your payment receipt.'],
                    ['name' => 'Submission Confirmation', 'type' => 'info',    'position' => 2, 'description' => 'Confirm your application is complete and ready for final processing.'],
                ],
            ],
        ];

        foreach ($blueprint as $sectionData) {
            $section = WorkflowSection::firstOrCreate(
                ['visa_type_id' => $touristVisa->id, 'position' => $sectionData['position']],
                ['name' => $sectionData['name']]
            );

            foreach ($sectionData['tasks'] as $taskData) {
                WorkflowTask::firstOrCreate(
                    ['workflow_section_id' => $section->id, 'position' => $taskData['position']],
                    [
                        'name'        => $taskData['name'],
                        'type'        => $taskData['type'],
                        'description' => $taskData['description'],
                    ]
                );
            }
        }
    }
}
