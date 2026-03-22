<?php

namespace Tests\Feature\Client;

use App\Models\ApplicationTask;
use App\Models\Document;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Database\Seeders\WorkflowStepTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
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

    protected function makeClientWithApplication(): array
    {
        $user = User::factory()->create();
        $user->assignRole('client');

        $application = VisaApplication::create([
            'user_id' => $user->id,
            'visa_type_id' => VisaType::first()->id,
            'status' => 'pending_review',
            'full_name' => $user->name,
            'email' => $user->email,
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

        return ['user' => $user, 'application' => $application->fresh(['tasks.template', 'tasks.documents'])];
    }

    public function test_client_can_upload_valid_pdf(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])
            ->post(route('client.documents.store'), [
                'application_task_id' => $task->id,
                'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('client.dashboard', ['tab' => 'documents']));

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])
            ->from(route('client.dashboard', ['tab' => 'documents']))
            ->post(route('client.documents.store'), [
                'application_task_id' => $task->id,
                'file' => UploadedFile::fake()->create('virus.exe', 50, 'application/octet-stream'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_file_over_10mb_is_rejected(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])
            ->from(route('client.dashboard', ['tab' => 'documents']))
            ->post(route('client.documents.store'), [
                'application_task_id' => $task->id,
                'file' => UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_upload_transitions_application_to_awaiting_documents(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        $this->assertSame('awaiting_documents', $data['application']->fresh()->status);
    }

    public function test_second_upload_does_not_double_transition(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('bank-statement.pdf', 100, 'application/pdf'),
        ]);

        $this->assertSame('awaiting_documents', $data['application']->fresh()->status);
        $this->assertDatabaseCount('documents', 2);
    }

    public function test_cross_client_cannot_download_document(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        $otherClient = User::factory()->create();
        $otherClient->assignRole('client');

        $this->actingAs($otherClient)->get(route('documents.download', Document::first()))->assertForbidden();
    }

    public function test_unauthenticated_cannot_download_document(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        Auth::logout();

        $this->get(route('documents.download', Document::first()))->assertRedirect(route('login'));
    }

    public function test_client_can_download_own_document(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($data['user'])->get(route('documents.download', Document::first()))->assertOk();
    }

    public function test_upload_is_audit_logged(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        $this->assertTrue(DB::table('audit_logs')->where('event', 'document_uploaded')->exists());
    }

    public function test_document_remains_downloadable_after_application_approved(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        $data['application']->fresh()->update(['status' => 'approved']);

        $this->actingAs($data['user'])->get(route('documents.download', Document::first()))->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function client_can_upload_application_level_document_without_task(): void
    {
        $data = $this->makeClientWithApplication();

        $this->actingAs($data['user'])
            ->post(route('client.documents.store'), [
                'application_id' => $data['application']->id,
                'file' => UploadedFile::fake()->create('general.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect(route('client.dashboard', ['tab' => 'documents']));

        $this->assertDatabaseCount('documents', 1);
        $this->assertNull(Document::first()->application_task_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function client_upload_to_closed_task_is_rejected(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 1);

        $this->actingAs($data['user'])
            ->post(route('client.documents.store'), [
                'application_task_id' => $task->id,
                'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('documents', 0);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function client_cannot_upload_more_than_10_documents_to_same_task(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        for ($i = 0; $i < 10; $i++) {
            Document::create([
                'application_id' => $data['application']->id,
                'application_task_id' => $task->id,
                'uploaded_by' => $data['user']->id,
                'source_type' => 'client',
                'original_filename' => "doc{$i}.pdf",
                'stored_filename' => "doc{$i}.pdf",
                'disk' => 'local',
                'path' => "documents/{$data['application']->id}/doc{$i}.pdf",
                'mime_type' => 'application/pdf',
                'size' => 100,
            ]);
        }

        $this->actingAs($data['user'])
            ->post(route('client.documents.store'), [
                'application_task_id' => $task->id,
                'file' => UploadedFile::fake()->create('11th.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('documents', 10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function client_cannot_upload_more_than_10_application_level_documents(): void
    {
        $data = $this->makeClientWithApplication();

        for ($i = 0; $i < 10; $i++) {
            Document::create([
                'application_id' => $data['application']->id,
                'application_task_id' => null,
                'uploaded_by' => $data['user']->id,
                'source_type' => 'client',
                'original_filename' => "doc{$i}.pdf",
                'stored_filename' => "doc{$i}.pdf",
                'disk' => 'local',
                'path' => "documents/{$data['application']->id}/doc{$i}.pdf",
                'mime_type' => 'application/pdf',
                'size' => 100,
            ]);
        }

        $this->actingAs($data['user'])
            ->post(route('client.documents.store'), [
                'application_id' => $data['application']->id,
                'file' => UploadedFile::fake()->create('11th.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);

        $this->assertDatabaseCount('documents', 10);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function client_can_delete_own_document_on_open_task(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        $document = Document::first();

        $this->actingAs($data['user'])
            ->delete(route('client.documents.destroy', $document))
            ->assertRedirect(route('client.dashboard', ['tab' => 'documents']));

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function client_cannot_delete_own_document_on_closed_task(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])->post(route('client.documents.store'), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf'),
        ]);

        $document = Document::first();
        $task->update(['status' => 'completed']);

        $this->actingAs($data['user'])
            ->delete(route('client.documents.destroy', $document))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function client_cannot_delete_reviewer_uploaded_document(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');

        Document::create([
            'application_id' => $data['application']->id,
            'application_task_id' => $task->id,
            'uploaded_by' => $reviewer->id,
            'source_type' => 'reviewer',
            'original_filename' => 'reviewer_doc.pdf',
            'stored_filename' => 'reviewer_doc.pdf',
            'disk' => 'local',
            'path' => "documents/{$data['application']->id}/reviewer_doc.pdf",
            'mime_type' => 'application/pdf',
            'size' => 100,
        ]);

        $document = Document::first();

        $this->actingAs($data['user'])
            ->delete(route('client.documents.destroy', $document))
            ->assertForbidden();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function client_can_upload_docx_file(): void
    {
        $data = $this->makeClientWithApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($data['user'])
            ->post(route('client.documents.store'), [
                'application_task_id' => $task->id,
                'file' => UploadedFile::fake()->create('test.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            ])
            ->assertRedirect(route('client.dashboard', ['tab' => 'documents']));

        $this->assertDatabaseCount('documents', 1);
    }
}
