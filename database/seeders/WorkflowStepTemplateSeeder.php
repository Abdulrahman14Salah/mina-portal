<?php

namespace Database\Seeders;

use App\Models\VisaType;
use App\Models\WorkflowStepTemplate;
use Illuminate\Database\Seeder;

class WorkflowStepTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $steps = [
            ['position' => 1, 'name' => 'Application Received', 'description' => 'Your application has been received and is awaiting initial review.', 'is_document_required' => false],
            ['position' => 2, 'name' => 'Initial Review', 'description' => 'Our team is reviewing your submitted application details.', 'is_document_required' => false],
            ['position' => 3, 'name' => 'Document Request', 'description' => 'We are preparing a list of required documents for your application.', 'is_document_required' => true],
            ['position' => 4, 'name' => 'Document Review', 'description' => 'Your submitted documents are under review by our team.', 'is_document_required' => true],
            ['position' => 5, 'name' => 'Assessment', 'description' => 'Your application is being assessed for a final recommendation.', 'is_document_required' => false],
            ['position' => 6, 'name' => 'Final Decision', 'description' => 'A final decision is being made on your visa application.', 'is_document_required' => false],
        ];

        foreach (VisaType::all() as $visaType) {
            foreach ($steps as $step) {
                WorkflowStepTemplate::updateOrCreate(
                    ['visa_type_id' => $visaType->id, 'position' => $step['position']],
                    ['name' => $step['name'], 'description' => $step['description'], 'is_document_required' => $step['is_document_required']]
                );
            }
        }
    }
}
