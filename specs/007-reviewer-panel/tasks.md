# Tasks: Reviewer Panel

**Input**: Design documents from `/specs/007-reviewer-panel/`
**Branch**: `007-reviewer-panel`
**Prerequisites**: plan.md ✅ spec.md ✅ research.md ✅ data-model.md ✅ contracts/routes.md ✅ quickstart.md ✅

> **Context for implementer**: This is a Laravel 11 / PHP 8.2+ project using Blade SSR, `spatie/laravel-permission` v6+, and local/S3 file storage.
> The reviewer panel already has a working queue dashboard and task advance/reject workflow.
> This phase adds: (1) a `source_type` column to the `documents` table, (2) reviewer document upload, (3) a required rejection reason, (4) a reviewer layout component.
> Run `php artisan migrate:fresh --seed` after completing foundational tasks.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to

---

## Phase 1: Setup

**Purpose**: Verify working state before making changes.

- [ ] T001 Run `php artisan test` and confirm all tests pass before making any changes

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database, model, service, permission, and language changes that ALL user stories depend on.

**⚠️ CRITICAL**: Complete this entire phase and run `php artisan migrate:fresh --seed` before starting any user story phase.

- [ ] T002 Create migration `database/migrations/2026_03_20_100000_add_source_type_to_documents_table.php` — add a `source_type` string column (max 20 chars, default `'client'`) to the `documents` table after the `uploaded_by` column. Use `$table->string('source_type', 20)->default('client')->after('uploaded_by');` in the `up()` method. The `down()` method should drop the column with `$table->dropColumn('source_type');`.

- [ ] T003 Update `app/Models/Document.php` — add `'source_type'` to the `$fillable` array. The array currently contains: `['application_id', 'application_task_id', 'uploaded_by', 'original_filename', 'stored_filename', 'disk', 'path', 'mime_type', 'size']`. Add `'source_type'` so it becomes part of that list.

- [ ] T004 Update `app/Services/Documents/DocumentService.php` — add a `string $sourceType = 'client'` parameter as the **5th argument** to the `upload()` method signature (after `User $uploader`). Inside the method, pass `'source_type' => $sourceType` to the `Document::create([...])` call. The full updated method signature should be: `public function upload(VisaApplication $application, ApplicationTask $task, UploadedFile $file, User $uploader, string $sourceType = 'client'): Document`

- [ ] T005 [P] Update `app/Http/Controllers/Client/DocumentController.php` — find the call to `$this->documentService->upload(...)` and add `'client'` as the 5th argument (the source type). Example: `$this->documentService->upload($application, $task, $request->file('file'), $user, 'client')`.

- [ ] T006 [P] Update `app/Http/Controllers/Admin/DocumentController.php` — find the call to `$this->documentService->upload(...)` and add `'admin'` as the 5th argument. Example: `$this->documentService->upload($application, $task, $request->file('file'), $user, 'admin')`.

- [ ] T007 Update `database/migrations/2026_03_20_000001_seed_roles_permissions_and_visa_types.php` — add `'documents.reviewer-upload'` to the `$permissions` array in the `up()` method. Then update the `$reviewer->syncPermissions([...])` call to include `'documents.reviewer-upload'` in the array alongside the existing reviewer permissions (`'dashboard.reviewer'`, `'tasks.view'`, `'tasks.advance'`, `'tasks.reject'`, `'documents.download'`).

- [ ] T008 Update `database/seeders/RolePermissionSeeder.php` — add `'documents.reviewer-upload'` to the `$permissions` array. Then update the `$reviewer->syncPermissions([...])` call to include `'documents.reviewer-upload'` alongside the existing reviewer permissions.

- [ ] T009 Update `app/Policies/DocumentPolicy.php` — add a new `reviewerUpload(User $user): bool` method that returns `$user->can('documents.reviewer-upload')`. Place it after the existing `adminUpload()` method.

- [ ] T010 [P] Update `resources/lang/en/reviewer.php` — add the following keys to the existing return array:
  ```php
  'reject_reason_label'       => 'Rejection Reason',
  'reject_reason_placeholder' => 'Describe why this task is being rejected (required)...',
  'upload_section_title'      => 'Upload Document',
  'upload_select_task'        => '— Select task —',
  'upload_submit'             => 'Upload',
  'upload_success'            => 'Document uploaded successfully.',
  'source_client'             => 'Client Upload',
  'source_reviewer'           => 'Reviewer Upload',
  'source_admin'              => 'Admin Upload',
  ```

- [ ] T011 [P] Update `resources/lang/ar/reviewer.php` — add the following keys (Arabic translations):
  ```php
  'reject_reason_label'       => 'سبب الرفض',
  'reject_reason_placeholder' => 'صِف سبب رفض هذه المهمة (مطلوب)...',
  'upload_section_title'      => 'رفع مستند',
  'upload_select_task'        => '— اختر مهمة —',
  'upload_submit'             => 'رفع',
  'upload_success'            => 'تم رفع المستند بنجاح.',
  'source_client'             => 'رفع العميل',
  'source_reviewer'           => 'رفع المراجع',
  'source_admin'              => 'رفع المدير',
  ```

- [ ] T012 Run `php artisan migrate:fresh --seed` — verify no errors. Then run `php artisan test` — confirm all existing tests still pass.

**Checkpoint**: Foundation complete. Source type tracking, new permission, and lang keys are in place.

---

## Phase 3: User Story 1 — Application Queue Dashboard (Priority: P1) 🎯 MVP

**Goal**: Reviewer sees their active workload in a clean, identifiable layout. No functional changes needed — the queue already works. This phase adds the reviewer layout component and integrates it.

**Independent Test**: Log in as reviewer → visit `/reviewer/dashboard` → see the applications queue in the reviewer-branded layout. No layout regressions.

### Implementation

- [ ] T013 [US1] Create `resources/views/components/reviewer-layout.blade.php` — a Blade component that wraps `<x-app-layout>` and provides a reviewer-specific header. Model it exactly after `resources/views/components/admin-layout.blade.php`. The component should:
  - Accept no props (just `$slot`)
  - Use `<x-app-layout>` as the outer wrapper
  - Provide an `<x-slot name="header">` containing a heading with `__('reviewer.dashboard_title')`
  - Render `{{ $slot }}` inside a `<div class="py-12"><div class="max-w-7xl mx-auto sm:px-6 lg:px-8">` container

- [ ] T014 [US1] Update `resources/views/reviewer/dashboard/index.blade.php` — replace the opening `<x-app-layout>` tag and its closing `</x-app-layout>` with `<x-reviewer-layout>` and `</x-reviewer-layout>`. Remove the `<x-slot name="header">` block from inside this file (the layout component provides the header). Keep the inner content (nav tabs, `@include` of tab partial) intact. The `<div class="py-12">` wrapper around the content should be removed since the layout component adds it.

**Checkpoint**: Reviewer dashboard renders without errors and uses the reviewer layout.

---

## Phase 4: User Story 2 — Task Review Workflow (Priority: P1)

**Goal**: Fix the rejection reason validation bug — reviewers must supply a non-empty reason to reject a task.

**Independent Test**: Open an application as reviewer → try to reject a task with an empty reason → form validation error shown → task NOT rejected. Then try with a reason → task rejected successfully.

### Implementation

- [ ] T015 [US2] Update `app/Http/Requests/Reviewer/RejectTaskRequest.php` — change the `rules()` method so that `note` is **required**, not nullable. Replace `['nullable', 'string', 'max:2000']` with `['required', 'string', 'min:5', 'max:2000']`.

- [ ] T016 [US2] Update the rejection form inside `resources/views/reviewer/applications/show.blade.php` — find the `<form>` that POSTs to `reviewer.applications.tasks.reject`. Make the following changes:
  1. Change the `<label>` text to use `__('reviewer.reject_reason_label')` instead of `__('reviewer.note_label')`.
  2. Change the `<textarea>` placeholder to `__('reviewer.reject_reason_placeholder')`.
  3. Add `required` attribute to the `<textarea>`.
  4. Add a validation error display below the textarea: `@error('note') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror`

- [ ] T017 [US2] Update `resources/views/reviewer/applications/show.blade.php` — replace the opening `<x-app-layout>` and `</x-app-layout>` wrapper with `<x-reviewer-layout>` and `</x-reviewer-layout>`. Remove the `<x-slot name="header">` block (the layout provides the header). The heading content (application reference number) should instead be placed directly at the top of the main content area as a `<h2>` or `<h3>` tag within the first content card.

- [ ] T018 [US2] Add test `test_reject_task_requires_reason` to `tests/Feature/Reviewer/WorkflowTest.php`. The test should:
  1. Create a reviewer user with `assignRole('reviewer')`.
  2. Create an onboarded application with tasks (use the existing `makeOnboardedApplication()` helper).
  3. Find the active task.
  4. POST to `reviewer.applications.tasks.reject` with `['note' => '']` (empty string).
  5. Assert `assertSessionHasErrors('note')`.
  6. Assert the task status is still `in_progress` (NOT `rejected`).

**Checkpoint**: Empty rejection reason shows a validation error. Task is not rejected. A reason with 5+ characters works correctly.

---

## Phase 5: User Story 3 — Document Review (Priority: P2)

**Goal**: Show source type labels alongside existing documents in the reviewer application detail view.

**Independent Test**: Upload a document as a client, log in as reviewer, open the application → see the document listed with a "Client Upload" label.

### Implementation

- [ ] T019 [US3] Update the documents section of `resources/views/reviewer/applications/show.blade.php` — find where documents are listed (the `@php($allDocuments = ...)` block). For each document displayed, add a source type badge after the uploader name. Use this pattern:
  ```blade
  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium
    {{ $doc->source_type === 'reviewer' ? 'bg-indigo-50 text-indigo-700' : '' }}
    {{ $doc->source_type === 'admin'    ? 'bg-amber-50 text-amber-700'   : '' }}
    {{ $doc->source_type === 'client'   ? 'bg-gray-100 text-gray-600'    : '' }}">
    {{ __('reviewer.source_' . $doc->source_type) }}
  </span>
  ```
  The variable name for each document in the loop may be `$document` or `$doc` — check the existing loop variable and use the correct one.

**Checkpoint**: Documents listed in the reviewer view show "Client Upload", "Reviewer Upload", or "Admin Upload" labels.

---

## Phase 6: User Story 4 — Reviewer Document Upload (Priority: P2)

**Goal**: Allow reviewers to upload documents to any application, attributed as "Reviewer Upload".

**Independent Test**: As reviewer, open an application → upload a PDF using the new upload form → document appears in the list with "Reviewer Upload" label.

### Implementation

- [ ] T020 [US4] Create `app/Http/Requests/Reviewer/UploadDocumentRequest.php` with this exact content:
  ```php
  <?php

  namespace App\Http\Requests\Reviewer;

  use Illuminate\Foundation\Http\FormRequest;

  class UploadDocumentRequest extends FormRequest
  {
      public function authorize(): bool
      {
          return true;
      }

      public function rules(): array
      {
          return [
              'file'                => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
              'application_task_id' => ['required', 'integer', 'exists:application_tasks,id'],
          ];
      }
  }
  ```

- [ ] T021 [US4] Create `app/Http/Controllers/Reviewer/DocumentController.php` with this exact content:
  ```php
  <?php

  namespace App\Http\Controllers\Reviewer;

  use App\Http\Controllers\Controller;
  use App\Http\Requests\Reviewer\UploadDocumentRequest;
  use App\Models\ApplicationTask;
  use App\Models\Document;
  use App\Models\User;
  use App\Models\VisaApplication;
  use App\Services\Documents\DocumentService;
  use Illuminate\Http\RedirectResponse;
  use Illuminate\Support\Facades\Auth;

  class DocumentController extends Controller
  {
      public function __construct(private DocumentService $documentService)
      {
      }

      public function store(UploadDocumentRequest $request, VisaApplication $application): RedirectResponse
      {
          $this->authorize('reviewerUpload', Document::class);

          $task = ApplicationTask::findOrFail($request->integer('application_task_id'));
          abort_if($task->application_id !== $application->id, 404);

          $user = Auth::user();
          abort_unless($user instanceof User, 403);

          $this->documentService->upload($application, $task, $request->file('file'), $user, 'reviewer');

          return redirect()
              ->route('reviewer.applications.show', $application)
              ->with('success', __('reviewer.upload_success'));
      }
  }
  ```

- [ ] T022 [US4] Update `routes/web.php` — inside the existing reviewer route group (the block starting with `Route::middleware(['auth', 'verified'])->prefix('reviewer')->name('reviewer.')->group(function () {`), add the following route **after** the existing reject route:
  ```php
  Route::post('/applications/{application}/documents', [ReviewerDocumentController::class, 'store'])
      ->middleware('can:documents.reviewer-upload')
      ->name('applications.documents.store');
  ```
  Also add the import at the top of the file: `use App\Http\Controllers\Reviewer\DocumentController as ReviewerDocumentController;`

- [ ] T023 [US4] Add the reviewer document upload form to `resources/views/reviewer/applications/show.blade.php`. Place it at the bottom of the page, after the existing documents section. The form should:
  1. Show a section heading using `__('reviewer.upload_section_title')`.
  2. Have `method="POST"` and `action="{{ route('reviewer.applications.documents.store', $application) }}"`.
  3. Include `@csrf`.
  4. Include a task selector `<select name="application_task_id">` populated with all tasks on the application: `@foreach($application->tasks->sortBy('position') as $task) <option value="{{ $task->id }}">{{ $task->name }}</option> @endforeach`. Add a blank first option using `__('reviewer.upload_select_task')`.
  5. Include a file input: `<input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png">`.
  6. Include a submit button using `__('reviewer.upload_submit')`.
  7. Display validation errors: `@error('file') <p class="text-xs text-red-600">{{ $message }}</p> @enderror` and same for `application_task_id`.

- [ ] T024 [US4] Add 4 tests to `tests/Feature/Reviewer/DocumentReviewerTest.php`:

  **Test 1** — `test_reviewer_can_upload_document`:
  - Create reviewer + application with document (use `makeApplicationWithDocument()` helper to get a valid setup, but you only need the application).
  - POST to `reviewer.applications.documents.store` with a fake PDF (`UploadedFile::fake()->create('letter.pdf', 100, 'application/pdf')`) and a valid `application_task_id` (any task on the application).
  - Assert redirect response (assertRedirect).
  - Assert the document exists in the database: `$this->assertDatabaseHas('documents', ['original_filename' => 'letter.pdf', 'source_type' => 'reviewer'])`.

  **Test 2** — `test_reviewer_upload_attributed_to_reviewer`:
  - Same setup as Test 1.
  - After uploading, fetch the document from DB and assert `source_type === 'reviewer'` and `uploaded_by === $reviewer->id`.

  **Test 3** — `test_invalid_file_type_rejected_for_reviewer_upload`:
  - Create reviewer + valid application.
  - POST to `reviewer.applications.documents.store` with `UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream')`.
  - Assert `assertSessionHasErrors('file')`.

  **Test 4** — `test_client_cannot_upload_via_reviewer_route`:
  - Create a client user + application.
  - POST to `reviewer.applications.documents.store` as the client.
  - Assert `assertForbidden()`.

**Checkpoint**: Reviewer can upload documents. Documents are labelled "Reviewer Upload". Clients cannot use this route.

---

## Phase 7: Polish & Cross-Cutting Concerns

- [ ] T025 Run `php artisan migrate:fresh --seed` then `php artisan test` — all tests must pass (target: 121+ passing, 0 failing).

- [ ] T026 [P] Manually verify quickstart.md Scenario 6 (reviewer upload) and Scenario 3 (rejection without reason blocked) in the browser.

- [ ] T027 [P] Verify RTL: switch locale to `ar` and confirm the reviewer dashboard and application detail page render without broken layout.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational)**: Depends on Phase 1 — **BLOCKS all user story phases**. Run `migrate:fresh --seed` at end of Phase 2.
- **Phase 3 (US1)**: Depends on Phase 2
- **Phase 4 (US2)**: Depends on Phase 2 — can run in parallel with Phase 3
- **Phase 5 (US3)**: Depends on Phase 2 — can run in parallel with Phase 3 & 4 (all different files)
- **Phase 6 (US4)**: Depends on Phase 2 — can run in parallel with Phase 3, 4, 5 (all different files)
- **Phase 7 (Polish)**: Depends on all phases complete

### User Story Dependencies

- **US1 (P1)**: Independent after Phase 2. Only touches: `reviewer-layout.blade.php`, `dashboard/index.blade.php`
- **US2 (P1)**: Independent after Phase 2. Only touches: `RejectTaskRequest.php`, `show.blade.php` (rejection form), `WorkflowTest.php`
- **US3 (P2)**: Independent after Phase 2. Only touches: `show.blade.php` (source badge display)
- **US4 (P2)**: Independent after Phase 2. Touches: new files + `show.blade.php` (upload form) + `web.php` + `DocumentReviewerTest.php`

⚠️ US2, US3, and US4 all modify `show.blade.php` — if working in parallel, coordinate changes to that file carefully or do them sequentially.

### Within Each User Story

- Foundational changes (Phase 2) before any story
- For US4: Form Request → Controller → Route → View → Tests (in that order)

### Parallel Opportunities Within Phase 2

Tasks T005 and T006 can run in parallel (different controller files).
Tasks T010 and T011 can run in parallel (different lang files).
Tasks T007 and T008 can run in parallel (different files).

---

## Parallel Example: Phase 2 (Foundational)

```
# Group A (sequential, must complete first):
T002 → T003 → T004 (migration → model → service, in order)

# Group B (parallel with each other, after T004):
T005 (Client DocumentController)
T006 (Admin DocumentController)
T007 (Migration seeder)
T008 (RolePermissionSeeder)
T009 (DocumentPolicy)
T010 (EN lang)
T011 (AR lang)

# Group C (after all above):
T012 (migrate:fresh --seed + test run)
```

---

## Implementation Strategy

### MVP (US1 + US2 only — fixes the rejection bug and adds the reviewer layout)

1. Complete Phase 1 (T001)
2. Complete Phase 2 (T002–T012)
3. Complete Phase 3 — US1: Reviewer Layout (T013–T014)
4. Complete Phase 4 — US2: Rejection Reason Fix (T015–T018)
5. **STOP and VALIDATE**: `php artisan test`, manually test rejection with empty reason
6. Optionally continue to Phase 5 (US3) and Phase 6 (US4)

### Full Delivery

1. Setup + Foundational (Phase 1–2)
2. US1 + US2 in parallel (layout + rejection fix) — share `show.blade.php` carefully
3. US3 (source type badges) — small change to `show.blade.php`
4. US4 (reviewer upload) — new files + one addition to `show.blade.php`
5. Polish + test run

---

## Notes

- `[P]` = different files, safe to run in parallel
- `[Story]` = traceability to spec user story
- After every group of changes, run `php artisan test` to catch regressions early
- The `source_type` default is `'client'` — existing documents in the database keep the correct value without any data backfill
- `RejectTaskRequest` change is a **bug fix** — the form will now show a validation error for empty rejection reasons
- `DocumentService::upload()` has a **default value** of `'client'` for `$sourceType` — callers that don't pass the 5th argument continue to work, but T005 and T006 should pass it explicitly for clarity
