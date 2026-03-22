<?php

namespace Tests\Feature\Admin;

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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentAdminTest extends TestCase
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

    protected function makeAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    protected function makeApplication(): array
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

        return ['client' => $client, 'application' => $application->fresh('tasks')];
    }

    public function test_admin_can_view_application_list(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->makeApplication();

        $this->actingAs($admin)->get('/admin/applications')->assertOk()->assertSee($data['application']->reference_number);
    }

    public function test_admin_can_view_application_documents_page(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->makeApplication();

        $this->actingAs($admin)->get(route('admin.applications.documents.index', $data['application']))->assertOk();
    }

    public function test_admin_can_upload_document_on_behalf(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->makeApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($admin)->post(route('admin.applications.documents.store', $data['application']), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('visa.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertDatabaseHas('documents', ['uploaded_by' => $admin->id]);
    }

    public function test_admin_upload_appears_on_client_documents_tab(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->makeApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($admin)->post(route('admin.applications.documents.store', $data['application']), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('visa.pdf', 100, 'application/pdf'),
        ]);

        $this->actingAs($data['client'])->get(route('client.dashboard', ['tab' => 'documents']))->assertSee('visa.pdf');
    }

    public function test_reviewer_cannot_upload_admin_document(): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole('reviewer');
        $data = $this->makeApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($reviewer)->post(route('admin.applications.documents.store', $data['application']), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('visa.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_client_cannot_access_admin_application_list(): void
    {
        $client = User::factory()->create();
        $client->assignRole('client');

        $this->actingAs($client)->get('/admin/applications')->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_uploaded_documents_have_source_type_admin(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->makeApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($admin)->post(route('admin.applications.documents.store', $data['application']), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('visa.pdf', 100, 'application/pdf'),
        ]);

        $this->assertDatabaseHas('documents', [
            'uploaded_by' => $admin->id,
            'source_type' => 'admin',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_upload_application_level_document_without_task(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->makeApplication();

        $this->actingAs($admin)
            ->post(route('admin.applications.documents.store', $data['application']), [
                'file' => UploadedFile::fake()->create('general.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('documents', 1);
        $this->assertNull(Document::first()->application_task_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_delete_any_document(): void
    {
        $admin = $this->makeAdmin();
        $data = $this->makeApplication();
        $task = $data['application']->tasks->firstWhere('position', 3);

        $this->actingAs($admin)->post(route('admin.applications.documents.store', $data['application']), [
            'application_task_id' => $task->id,
            'file' => UploadedFile::fake()->create('visa.pdf', 100, 'application/pdf'),
        ]);

        $document = Document::first();

        $this->actingAs($admin)
            ->delete(route('admin.applications.documents.destroy', [$data['application'], $document]))
            ->assertRedirect();

        $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_delete_returns_404_if_document_does_not_belong_to_application(): void
    {
        $admin = $this->makeAdmin();
        $data1 = $this->makeApplication();
        $data2 = $this->makeApplication();
        $task1 = $data1['application']->tasks->firstWhere('position', 3);

        $this->actingAs($admin)->post(route('admin.applications.documents.store', $data1['application']), [
            'application_task_id' => $task1->id,
            'file' => UploadedFile::fake()->create('visa.pdf', 100, 'application/pdf'),
        ]);

        $document = Document::first();

        $this->actingAs($admin)
            ->delete(route('admin.applications.documents.destroy', [$data2['application'], $document]))
            ->assertNotFound();

        $this->assertDatabaseHas('documents', ['id' => $document->id]);
    }
}
