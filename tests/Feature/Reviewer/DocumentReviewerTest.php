<?php

namespace Tests\Feature\Reviewer;

use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Services\Documents\DocumentService;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Database\Seeders\WorkflowStepTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentReviewerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
        $this->seed(WorkflowStepTemplateSeeder::class);
    }

    protected function makeReviewer(): User
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        return $reviewer;
    }

    protected function makeApplicationWithDocument(?User $assignedReviewer = null): array
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $application = VisaApplication::create([
            'user_id' => $client->id,
            'visa_type_id' => VisaType::first()->id,
            'status' => 'pending_review',
            'full_name' => $client->name,
            'email' => $client->email,
            'phone' => '+1555000123',
            'nationality' => 'Jordanian',
            'country_of_residence' => 'UAE',
            'job_title' => 'Engineer',
            'employment_type' => 'employed',
            'monthly_income' => 5000,
            'adults_count' => 1,
            'children_count' => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'notes' => 'Needs documents',
            'agreed_to_terms' => true,
        ]);

        app(WorkflowService::class)->seedTasksForApplication($application);

        ApplicationTask::where('application_id', $application->id)->where('position', 1)->update(['status' => 'completed', 'completed_at' => now()]);
        ApplicationTask::where('application_id', $application->id)->where('position', 2)->update(['status' => 'completed', 'completed_at' => now()]);
        ApplicationTask::where('application_id', $application->id)->where('position', 3)->update(['status' => 'in_progress']);
        ApplicationTask::where('application_id', $application->id)->whereIn('position', [4, 5, 6])->update(['status' => 'pending']);
        $application->update(['status' => 'in_progress']);

        if ($assignedReviewer !== null) {
            $application->update(['assigned_reviewer_id' => $assignedReviewer->id]);
        }

        $task = $application->fresh('tasks')->tasks->firstWhere('position', 3);
        $document = app(DocumentService::class)->upload($application->fresh(), $task, UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'), $client);

        return ['client' => $client, 'application' => $application->fresh(['tasks.template', 'tasks.documents.uploader']), 'document' => $document->fresh(['task', 'uploader', 'application'])];
    }

    public function test_reviewer_sees_documents_on_application_detail(): void
    {
        $reviewer = $this->makeReviewer();
        $data = $this->makeApplicationWithDocument($reviewer);

        $this->actingAs($reviewer)->get(route('reviewer.applications.show', $data['application']))->assertOk()->assertSee($data['document']->original_filename);
    }

    public function test_reviewer_can_download_document(): void
    {
        $reviewer = $this->makeReviewer();
        $data = $this->makeApplicationWithDocument($reviewer);

        $this->actingAs($reviewer)->get(route('documents.download', $data['document']))->assertOk();
    }

    public function test_reviewer_download_is_audit_logged(): void
    {
        $reviewer = $this->makeReviewer();
        $data = $this->makeApplicationWithDocument($reviewer);

        $this->actingAs($reviewer)->get(route('documents.download', $data['document']));

        $this->assertTrue(DB::table('audit_logs')->where('event', 'document_downloaded')->exists());
    }

    public function test_client_cannot_access_another_clients_document_via_reviewer_url(): void
    {
        $dataA = $this->makeApplicationWithDocument();
        $dataB = $this->makeApplicationWithDocument();

        $this->actingAs($dataB['client'])->get(route('documents.download', $dataA['document']))->assertForbidden();
    }

    public function test_reviewer_can_upload_document(): void
    {
        $reviewer = $this->makeReviewer();
        $data = $this->makeApplicationWithDocument($reviewer);
        $task = $data['application']->tasks->first();

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.documents.store', $data['application']), [
                'file' => UploadedFile::fake()->create('decision_letter.pdf', 100, 'application/pdf'),
                'application_task_id' => $task->id,
            ])
            ->assertRedirect(route('reviewer.applications.show', $data['application']));

        $this->assertDatabaseHas('documents', [
            'original_filename' => 'decision_letter.pdf',
            'source_type' => 'reviewer',
        ]);
    }

    public function test_reviewer_upload_attributed_to_reviewer(): void
    {
        $reviewer = $this->makeReviewer();
        $data = $this->makeApplicationWithDocument($reviewer);
        $task = $data['application']->tasks->first();

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.documents.store', $data['application']), [
                'file' => UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf'),
                'application_task_id' => $task->id,
            ]);

        $doc = \App\Models\Document::where('original_filename', 'letter.pdf')->first();
        $this->assertNotNull($doc);
        $this->assertSame('reviewer', $doc->source_type);
        $this->assertSame($reviewer->id, $doc->uploaded_by);
    }

    public function test_invalid_file_type_rejected_for_reviewer_upload(): void
    {
        $reviewer = $this->makeReviewer();
        $data = $this->makeApplicationWithDocument($reviewer);
        $task = $data['application']->tasks->first();

        $this->actingAs($reviewer)
            ->post(route('reviewer.applications.documents.store', $data['application']), [
                'file' => UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
                'application_task_id' => $task->id,
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_client_cannot_upload_via_reviewer_route(): void
    {
        $data = $this->makeApplicationWithDocument();
        $task = $data['application']->tasks->first();

        $this->actingAs($data['client'])
            ->post(route('reviewer.applications.documents.store', $data['application']), [
                'file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
                'application_task_id' => $task->id,
            ])
            ->assertForbidden();
    }
}
