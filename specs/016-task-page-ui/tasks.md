# Tasks: Task Page UI (016)

**Branch**: `016-task-page-ui`
**Input**: Design documents from `/specs/016-task-page-ui/`

## Format: `[ID] [P?] [Story] Description`

> **Route note**: All task page routes follow `/client/applications/{application}/tasks/{task}` — already registered. No new routes required.

---

## Phase 1: Setup (Verification — No Code Changes)

- [X] T001 Read `app/Http/Controllers/Client/TaskController.php`, `app/Services/Tasks/TaskAnswerService.php`, `app/Models/ApplicationTask.php`, and `app/Services/Tasks/WorkflowService.php` to confirm current state before modifications. **No code changes.**

---

## Phase 2: Foundational (Blocking Prerequisites)

**⚠️ CRITICAL**: All tasks in this phase MUST complete before any Phase 3+ work begins.

- [X] T002 Create `database/migrations/2026_03_23_200001_add_workflow_task_id_to_application_tasks.php` with the following exact content:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->foreignId('workflow_task_id')
                ->nullable()
                ->after('workflow_step_template_id')
                ->constrained('workflow_tasks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_task_id');
        });
    }
};
```

- [X] T003 Run `php artisan migrate` and confirm `workflow_task_id` column added to `application_tasks` with no errors.

**Checkpoint**: `workflow_task_id` nullable FK exists in `application_tasks`.

- [ ] T004 Modify `app/Models/ApplicationTask.php`. Replace the `$fillable` array and add the `workflowTask()` relationship. The old snippet:

```php
    protected $fillable = ['application_id', 'workflow_step_template_id', 'position', 'name', 'description', 'type', 'status', 'reviewer_note', 'rejection_reason', 'completed_at'];
```

Replace with:

```php
    protected $fillable = ['application_id', 'workflow_step_template_id', 'workflow_task_id', 'position', 'name', 'description', 'type', 'status', 'reviewer_note', 'rejection_reason', 'completed_at'];
```

Also add `use App\Models\WorkflowTask;` to the imports at the top (after the existing `use App\Models\WorkflowStepTemplate;` line).

Then add the `workflowTask()` method after the existing `template()` method:

```php
    public function workflowTask(): BelongsTo
    {
        return $this->belongsTo(WorkflowTask::class);
    }
```

- [X] T005 Modify `app/Services/Tasks/WorkflowService.php`. In `seedTasksForApplication()`, in the section-based path (`if ($hasSections)`), replace the `ApplicationTask::create` call. Old snippet:

```php
                        $tasks[] = ApplicationTask::create([
                            'application_id'            => $application->id,
                            'workflow_step_template_id' => null,
                            'position'                  => $position++,
                            'name'                      => $workflowTask->name,
                            'description'               => $workflowTask->description,
                            'type'                      => $workflowTask->type,
                            'status'                    => 'pending',
                        ]);
```

New snippet:

```php
                        $tasks[] = ApplicationTask::create([
                            'application_id'            => $application->id,
                            'workflow_step_template_id' => null,
                            'workflow_task_id'          => $workflowTask->id,
                            'position'                  => $position++,
                            'name'                      => $workflowTask->name,
                            'description'               => $workflowTask->description,
                            'type'                      => $workflowTask->type,
                            'status'                    => 'pending',
                        ]);
```

- [X] T006 Modify `app/Services/Tasks/TaskAnswerService.php`. Replace the entire `submitAnswers` method with the following:

```php
    public function submitAnswers(ApplicationTask $task, array $answers, User $client): void
    {
        if (! in_array($task->status, ['in_progress', 'rejected'], true)) {
            throw new InvalidArgumentException('Only an in_progress or rejected task can accept answer submissions.');
        }

        DB::transaction(function () use ($task, $answers): void {
            foreach ($answers as $questionId => $answerText) {
                TaskAnswer::updateOrCreate(
                    ['application_task_id' => $task->id, 'task_question_id' => (int) $questionId],
                    ['answer' => $answerText]
                );
            }

            if ($task->status === 'rejected') {
                $task->update(['status' => 'in_progress', 'rejection_reason' => null]);
            }
        });

        $this->auditLog->log('task_answers_submitted', $client, [
            'task_id'   => $task->id,
            'reference' => $task->application->reference_number,
        ]);
    }
```

- [X] T007 Modify `app/Http/Controllers/Client/TaskController.php`. Replace the entire `show()` method with the following:

```php
    public function show(VisaApplication $application, ApplicationTask $task): View|RedirectResponse
    {
        if ($task->status === 'pending') {
            return redirect()->route('client.dashboard');
        }

        $this->authorize('view', $application);

        abort_if($task->application_id !== $application->id, 404);

        $task->loadMissing(['documents', 'answers', 'workflowTask.questions']);

        $answers = $task->answers->keyBy('task_question_id');

        return view('client.tasks.show', compact('application', 'task', 'answers'));
    }
```

**Checkpoint**: `php artisan test --filter=TaskTypeBehaviorTest` must still pass (all 10 tests green) before proceeding.

---

## Phase 3: User Story 1 — Client Views Question Task (Priority: P1) 🎯 MVP

**Goal**: Clients can open a question task, see all questions, submit answers, and see pre-populated answers on revisit.

**Independent Test**: `php artisan test --filter=TaskPageTest` — US1 tests pass after T012.

### Implementation for User Story 1

- [X] T008 Add 9 new translation keys to `resources/lang/en/tasks.php`. After the existing `'receipt_uploaded' => 'Your receipt has been uploaded.',` line, add:

```php
    'your_answers'           => 'Your Answers',
    'no_questions_defined'   => 'No questions are required for this task. Awaiting reviewer action.',
    'submit_answers'         => 'Submit Answers',
    'payment_receipt'        => 'Payment Receipt',
    'current_receipt'        => 'Current Receipt',
    'replace_receipt'        => 'Replace Receipt',
    'upload_receipt'         => 'Upload Receipt',
    'info_task_note'         => 'This task contains information only. No action is required from you.',
    'answers_readonly_title' => 'Submitted Answers',
```

- [X] T009 [P] [US1] Create `resources/views/client/tasks/partials/_question-form.blade.php` with the following exact content:

```blade
@php $questions = $task->workflowTask?->questions ?? collect(); @endphp

@if ($questions->isEmpty())
    @include('client.tasks.partials._no-questions')
@else
    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-lg bg-white p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('tasks.your_answers') }}</h2>

        <form method="POST" action="{{ route('client.tasks.answers.submit', [$application, $task]) }}">
            @csrf
            <div class="space-y-6">
                @foreach ($questions as $question)
                    <div>
                        <label for="answer_{{ $question->id }}" class="block text-sm font-medium text-gray-700 mb-1">
                            {{ $question->prompt }}
                            @if ($question->required)
                                <span class="text-red-500 ml-0.5">*</span>
                            @endif
                        </label>
                        <textarea
                            id="answer_{{ $question->id }}"
                            name="answers[{{ $question->id }}]"
                            rows="3"
                            class="block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500 @error('answers.' . $question->id) border-red-500 @enderror"
                        >{{ old('answers.' . $question->id, $answers[$question->id]->answer ?? '') }}</textarea>
                        @error('answers.' . $question->id)
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    {{ __('tasks.submit_answers') }}
                </button>
            </div>
        </form>
    </div>
@endif
```

- [X] T010 [P] [US1] Create `resources/views/client/tasks/partials/_answers-readonly.blade.php` with the following exact content:

```blade
@php $questions = $task->workflowTask?->questions ?? collect(); @endphp

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('tasks.answers_readonly_title') }}</h2>

    @forelse ($questions as $question)
        <div class="mb-4">
            <p class="text-sm font-medium text-gray-700">{{ $question->prompt }}</p>
            <p class="mt-1 text-sm text-gray-600 bg-gray-50 rounded-md px-3 py-2">
                {{ $answers[$question->id]->answer ?? '—' }}
            </p>
        </div>
    @empty
        <p class="text-sm text-gray-500">{{ __('tasks.no_questions_defined') }}</p>
    @endforelse
</div>
```

- [X] T011 [P] [US1] Create `resources/views/client/tasks/partials/_no-questions.blade.php` with the following exact content:

```blade
<div class="rounded-lg bg-white p-6 shadow-sm">
    <p class="text-sm text-gray-500">{{ __('tasks.no_questions_defined') }}</p>
</div>
```

**Checkpoint**: T009, T010, T011 are complete and all three partial files exist.

---

## Phase 4: User Story 2 — Client Views Payment Task (Priority: P1)

**Goal**: Clients can open a payment task, upload a receipt, see the existing receipt as a download link, and replace it.

**Independent Test**: `php artisan test --filter=TaskPageTest` — US2 tests pass after T013.

### Implementation for User Story 2

- [X] T012 [P] [US2] Create `resources/views/client/tasks/partials/_payment-form.blade.php` with the following exact content:

```blade
@php $receipt = $task->documents->where('source_type', 'client')->whereNull('archived_at')->first(); @endphp

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 mb-4">{{ __('tasks.payment_receipt') }}</h2>

    @if ($receipt)
        <div class="mb-4 flex items-center gap-2 text-sm text-gray-600">
            <span class="font-medium">{{ __('tasks.current_receipt') }}:</span>
            <a href="{{ route('documents.download', $receipt) }}" class="text-indigo-600 hover:underline">
                {{ $receipt->original_filename }}
            </a>
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 mb-4">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('client.tasks.receipt.upload', [$application, $task]) }}" enctype="multipart/form-data">
        @csrf
        <div class="space-y-4">
            <div>
                <label for="receipt" class="block text-sm font-medium text-gray-700 mb-1">
                    {{ $receipt ? __('tasks.replace_receipt') : __('tasks.upload_receipt') }}
                </label>
                <input type="file" name="receipt" id="receipt" accept=".pdf,.jpg,.jpeg,.png"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-400">PDF, JPG, PNG — {{ __('documents.max_size') }}</p>
            </div>
            <button type="submit"
                class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                {{ $receipt ? __('tasks.replace_receipt') : __('tasks.upload_receipt') }}
            </button>
        </div>
    </form>
</div>
```

- [X] T013 [P] [US2] Create `resources/views/client/tasks/partials/_receipt-readonly.blade.php` with the following exact content:

```blade
@php $receipt = $task->documents->where('source_type', 'client')->whereNull('archived_at')->first(); @endphp

<div class="rounded-lg bg-white p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 mb-2">{{ __('tasks.payment_receipt') }}</h2>

    @if ($receipt)
        <a href="{{ route('documents.download', $receipt) }}" class="text-sm text-indigo-600 hover:underline">
            {{ $receipt->original_filename }}
        </a>
    @else
        <p class="text-sm text-gray-500">—</p>
    @endif
</div>
```

**Checkpoint**: T012, T013 are complete and both partial files exist.

---

## Phase 5: User Story 3 — Client Views Info Task (Priority: P2)

**Goal**: Info tasks display content only with no form or input.

### Implementation for User Story 3

- [X] T014 [US3] Create `resources/views/client/tasks/partials/_info-content.blade.php` with the following exact content:

```blade
<div class="rounded-lg bg-white p-6 shadow-sm">
    <p class="text-sm text-gray-500">{{ __('tasks.info_task_note') }}</p>
</div>
```

**Checkpoint**: All 6 partials exist in `resources/views/client/tasks/partials/`.

---

## Phase 6: Integration — Rewrite Main View

**⚠️ Depends on T009–T014 all being complete.**

- [X] T015 Replace the entire content of `resources/views/client/tasks/show.blade.php` with the following:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $task->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Task header --}}
            <div class="rounded-lg bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">{{ __('tasks.step_number', ['number' => $task->position]) }}</p>
                        <h1 class="text-xl font-semibold text-gray-900">{{ $task->name }}</h1>
                        @if ($task->description)
                            <p class="mt-2 text-sm text-gray-600">{{ $task->description }}</p>
                        @endif
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium
                        {{ $task->status === 'approved'    ? 'bg-green-100 text-green-700'   : '' }}
                        {{ $task->status === 'in_progress' ? 'bg-indigo-100 text-indigo-700' : '' }}
                        {{ $task->status === 'rejected'    ? 'bg-red-100 text-red-700'       : '' }}
                        {{ $task->status === 'pending'     ? 'bg-gray-100 text-gray-600'     : '' }}">
                        {{ __('tasks.status_' . $task->status) }}
                    </span>
                </div>

                @if ($task->reviewer_note)
                    <div class="mt-4 rounded-md bg-gray-50 border border-gray-100 px-4 py-3 text-sm text-gray-600">
                        <p class="font-semibold mb-1">{{ __('reviewer.reviewer_note') }}</p>
                        <p>{{ $task->reviewer_note }}</p>
                    </div>
                @endif

                @if ($task->rejection_reason)
                    <div class="mt-4 rounded-md bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-700">
                        <p class="font-semibold mb-1">{{ __('tasks.rejection_reason') }}</p>
                        <p>{{ $task->rejection_reason }}</p>
                    </div>
                @endif
            </div>

            {{-- Type-specific UI --}}
            @if ($task->type === 'question')
                @if (in_array($task->status, ['in_progress', 'rejected']))
                    @include('client.tasks.partials._question-form')
                @elseif ($task->status === 'approved')
                    @include('client.tasks.partials._answers-readonly')
                @endif
            @elseif ($task->type === 'payment')
                @if (in_array($task->status, ['in_progress', 'rejected']))
                    @include('client.tasks.partials._payment-form')
                @elseif ($task->status === 'approved')
                    @include('client.tasks.partials._receipt-readonly')
                @endif
            @elseif ($task->type === 'info')
                @include('client.tasks.partials._info-content')
            @endif

            {{-- Back link --}}
            <div>
                <a href="{{ route('client.dashboard') }}" class="text-sm text-blue-600 hover:underline">
                    &larr; {{ __('client.back_to_dashboard') }}
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
```

**Checkpoint**: Visit `/client/applications/{id}/tasks/{id}` in browser — all three task types render correctly.

---

## Phase 7: Polish — Tests

- [X] T016 Create `tests/Feature/Tasks/TaskPageTest.php` with the following exact content:

```php
<?php

namespace Tests\Feature\Tasks;

use App\Models\ApplicationTask;
use App\Models\TaskAnswer;
use App\Models\TaskQuestion;
use App\Models\User;
use App\Models\VisaApplication;
use App\Models\VisaType;
use App\Models\WorkflowSection;
use App\Models\WorkflowTask;
use App\Services\Tasks\TaskAnswerService;
use App\Services\Tasks\WorkflowService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\VisaTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(VisaTypeSeeder::class);
    }

    private function makeApplicationWithTask(string $taskType): array
    {
        $visaType = VisaType::first();
        $client   = User::factory()->create()->assignRole('client');

        $application = VisaApplication::create([
            'user_id'                => $client->id,
            'visa_type_id'           => $visaType->id,
            'status'                 => 'pending_review',
            'full_name'              => $client->name,
            'email'                  => $client->email,
            'phone'                  => '+1555000123',
            'nationality'            => 'Jordanian',
            'country_of_residence'   => 'UAE',
            'job_title'              => 'Engineer',
            'employment_type'        => 'employed',
            'monthly_income'         => 5000,
            'adults_count'           => 1,
            'children_count'         => 0,
            'application_start_date' => now()->addDays(30)->toDateString(),
            'agreed_to_terms'        => true,
        ]);

        $section      = WorkflowSection::create(['visa_type_id' => $visaType->id, 'name' => 'Test Section', 'position' => 99]);
        $workflowTask = WorkflowTask::create(['workflow_section_id' => $section->id, 'name' => 'Test Task', 'type' => $taskType, 'position' => 99]);

        app(WorkflowService::class)->seedTasksForApplication($application);

        $appTask = ApplicationTask::where('application_id', $application->id)->first();

        return [$client, $application, $workflowTask, $appTask];
    }

    // ── Pending redirect ───────────────────────────────────────────────────────

    public function test_pending_task_redirects_to_dashboard(): void
    {
        [$client, $application, $workflowTask, $firstTask] = $this->makeApplicationWithTask('info');

        $pendingTask = ApplicationTask::create([
            'application_id'   => $application->id,
            'workflow_task_id' => $workflowTask->id,
            'position'         => 2,
            'name'             => 'Locked Task',
            'type'             => 'info',
            'status'           => 'pending',
        ]);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $pendingTask]))
            ->assertRedirect(route('client.dashboard'));
    }

    // ── Question task ──────────────────────────────────────────────────────────

    public function test_question_task_shows_questions_form(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'What is your name?', 'required' => true, 'position' => 1]);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('What is your name?')
            ->assertSee('name="answers[', false);
    }

    public function test_question_task_prepopulates_existing_answers(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);
        TaskAnswer::create(['application_task_id' => $appTask->id, 'task_question_id' => $q->id, 'answer' => 'My stored answer']);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('My stored answer');
    }

    public function test_question_task_approved_shows_readonly_answers(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);
        TaskAnswer::create(['application_task_id' => $appTask->id, 'task_question_id' => $q->id, 'answer' => 'Final answer']);
        $appTask->update(['status' => 'approved']);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('Final answer')
            ->assertDontSee('name="answers[', false);
    }

    public function test_question_task_with_no_questions_shows_message(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee(__('tasks.no_questions_defined'));
    }

    public function test_rejected_question_task_auto_reopens_on_submit(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('question');

        $q = TaskQuestion::create(['workflow_task_id' => $workflowTask->id, 'prompt' => 'Q?', 'required' => true, 'position' => 1]);
        $appTask->update(['status' => 'rejected', 'rejection_reason' => 'Please redo']);

        $this->actingAs($client)
            ->post(route('client.tasks.answers.submit', [$application, $appTask]), [
                'answers' => [$q->id => 'Revised answer'],
            ])
            ->assertRedirect();

        $this->assertSame('in_progress', $appTask->fresh()->status);
        $this->assertNull($appTask->fresh()->rejection_reason);
    }

    // ── Payment task ───────────────────────────────────────────────────────────

    public function test_payment_task_shows_upload_form(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee(route('client.tasks.receipt.upload', [$application, $appTask]), false);
    }

    public function test_payment_task_shows_existing_receipt_as_download_link(): void
    {
        Storage::fake('local');

        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $file = UploadedFile::fake()->create('my-receipt.pdf', 100, 'application/pdf');
        app(TaskAnswerService::class)->uploadReceipt($appTask, $file, $client);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('my-receipt.pdf');
    }

    public function test_payment_task_approved_shows_readonly_receipt(): void
    {
        Storage::fake('local');

        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('payment');

        $file = UploadedFile::fake()->create('approved-receipt.pdf', 100, 'application/pdf');
        app(TaskAnswerService::class)->uploadReceipt($appTask, $file, $client);
        $appTask->update(['status' => 'approved']);

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee('approved-receipt.pdf')
            ->assertDontSee('name="receipt"', false);
    }

    // ── Info task ──────────────────────────────────────────────────────────────

    public function test_info_task_shows_no_form(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('info');

        $this->actingAs($client)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertOk()
            ->assertSee(__('tasks.info_task_note'))
            ->assertDontSee('<form', false);
    }

    // ── Security ───────────────────────────────────────────────────────────────

    public function test_client_cannot_view_another_clients_task(): void
    {
        [$client, $application, $workflowTask, $appTask] = $this->makeApplicationWithTask('info');

        $otherClient = User::factory()->create()->assignRole('client');

        $this->actingAs($otherClient)
            ->get(route('client.tasks.show', [$application, $appTask]))
            ->assertForbidden();
    }
}
```

- [X] T017 Run `php artisan test --filter=TaskPageTest` and confirm all 10 tests pass.

- [X] T018 Run `php artisan test` (full suite) and confirm all existing tests still pass alongside the new tests.

---

## Dependencies & Execution Order

- **T002–T003** (migration) MUST complete before T004–T007 (model/service/controller reference new column)
- **T004, T005, T006, T007** [P] can run in parallel after T003 — different files
- **T008** can run in parallel with T004–T007 — lang file only
- **T009, T010, T011** [P] can run in parallel — different files
- **T012, T013** [P] can run in parallel — different files
- **T014** independent — single file
- **T015** depends on T009–T014 all complete (view @includes all partials)
- **T016** depends on all implementation tasks (tests reference all new behaviour)

---

## Final File State

| File | Status |
|------|--------|
| `database/migrations/2026_03_23_200001_add_workflow_task_id_to_application_tasks.php` | NEW |
| `app/Models/ApplicationTask.php` | MODIFIED — `workflow_task_id` in fillable, `workflowTask()` added |
| `app/Services/Tasks/WorkflowService.php` | MODIFIED — seeds `workflow_task_id` |
| `app/Services/Tasks/TaskAnswerService.php` | MODIFIED — accepts `rejected` + auto-reopens |
| `app/Http/Controllers/Client/TaskController.php` | MODIFIED — pending redirect, eager-load, `$answers` |
| `resources/views/client/tasks/show.blade.php` | REWRITTEN |
| `resources/views/client/tasks/partials/_question-form.blade.php` | NEW |
| `resources/views/client/tasks/partials/_answers-readonly.blade.php` | NEW |
| `resources/views/client/tasks/partials/_no-questions.blade.php` | NEW |
| `resources/views/client/tasks/partials/_payment-form.blade.php` | NEW |
| `resources/views/client/tasks/partials/_receipt-readonly.blade.php` | NEW |
| `resources/views/client/tasks/partials/_info-content.blade.php` | NEW |
| `resources/lang/en/tasks.php` | MODIFIED — 9 new keys |
| `tests/Feature/Tasks/TaskPageTest.php` | NEW |
