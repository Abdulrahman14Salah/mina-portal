# Tasks: Document Management System

**Input**: Design documents from `/specs/010-document-management/`
**Branch**: `010-document-management`
**Stack**: PHP 8.2+ / Laravel 11, spatie/laravel-permission v6+, Blade SSR, AuditLogService, FILESYSTEM_DISK (local/S3)

> **Context for implementer**: This feature extends an existing, partially-built document system. Most files already exist — you are modifying them, not creating them from scratch. Read each task's "Current state" note before touching any file.

---

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel with other [P] tasks (different files, no dependencies)
- **[Story]**: User story this task belongs to
- No test tasks — tests are written as part of Phase H only

---

## Phase 1: Confirm Setup

**Purpose**: Verify you are on the correct branch with the right environment.

- [X] T001 Verify active branch is `010-document-management` by running `git branch --show-current`; if not, run `git checkout 010-document-management`
- [X] T002 Verify the app boots by running `php artisan route:list` and confirming no errors

---

## Phase 2: Foundational — Migrations & Permissions

**Purpose**: DB schema and permission changes that ALL user stories depend on.

**⚠️ CRITICAL**: Run `php artisan migrate` after T003 and T004 before writing any other code.

- [X] T003 Create new migration file `database/migrations/2026_03_21_000001_make_application_task_id_nullable_on_documents_table.php` with this exact content:

  ```php
  <?php
  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;

  return new class extends Migration {
      public function up(): void {
          Schema::table('documents', function (Blueprint $table) {
              $table->foreignId('application_task_id')
                  ->nullable()
                  ->change();
          });
      }
      public function down(): void {
          Schema::table('documents', function (Blueprint $table) {
              $table->foreignId('application_task_id')
                  ->nullable(false)
                  ->change();
          });
      }
  };
  ```

- [X] T004 Create new migration file `database/migrations/2026_03_21_000002_add_document_delete_permissions.php` with this exact content:

  ```php
  <?php
  use Illuminate\Database\Migrations\Migration;
  use Spatie\Permission\Models\Permission;
  use Spatie\Permission\Models\Role;
  use Spatie\Permission\PermissionRegistrar;

  return new class extends Migration {
      public function up(): void {
          app()[PermissionRegistrar::class]->forgetCachedPermissions();

          $deleteOwn = Permission::firstOrCreate(['name' => 'documents.delete-own', 'guard_name' => 'web']);
          $delete    = Permission::firstOrCreate(['name' => 'documents.delete',     'guard_name' => 'web']);

          Role::findByName('client', 'web')->givePermissionTo($deleteOwn);
          Role::findByName('admin',  'web')->givePermissionTo($delete);

          app()[PermissionRegistrar::class]->forgetCachedPermissions();
      }
      public function down(): void {
          app()[PermissionRegistrar::class]->forgetCachedPermissions();
          Permission::where('name', 'documents.delete-own')->delete();
          Permission::where('name', 'documents.delete')->delete();
          app()[PermissionRegistrar::class]->forgetCachedPermissions();
      }
  };
  ```

- [X] T005 Run `php artisan migrate` and confirm both migrations execute without errors

**Checkpoint**: `documents.application_task_id` is now nullable. Permissions `documents.delete-own` and `documents.delete` exist in the database.

---

## Phase 3: User Story 1 — Client Uploads (Task-Attached or Application-Level) 🎯 MVP

**Goal**: Clients can upload documents either linked to a specific task or directly to their application (no task required).

**Independent Test**: Log in as a client. Upload a document selecting a task → it appears under that task. Upload again leaving the task blank with only an `application_id` → it appears in the application-level document list.

### Implementation for User Story 1

- [X] T006 [US1] Update `app/Services/Documents/DocumentService.php` — make task parameter optional and support null task_id

  **Current state**: `upload()` signature is `upload(VisaApplication $application, ApplicationTask $task, UploadedFile $file, User $uploader, string $sourceType = 'client'): Document` and always sets `application_task_id` from `$task->id`.

  **Change 1**: Update the method signature to:
  ```php
  public function upload(
      VisaApplication $application,
      ?ApplicationTask $task,
      UploadedFile $file,
      User $uploader,
      string $sourceType = 'client'
  ): Document
  ```

  **Change 2**: In the `Document::create([...])` call, change:
  ```php
  'application_task_id' => $task->id,
  ```
  to:
  ```php
  'application_task_id' => $task?->id,
  ```

  **Change 3**: Update the audit log call so it works when `$task` is null. The reference is already taken from `$application->reference_number`, so no change needed there.

  Leave all other code in `DocumentService.php` unchanged.

- [X] T007 [US1] Update `app/Http/Requests/Client/UploadDocumentRequest.php` — make `application_task_id` optional and add `application_id`

  **Current state**: Rules are `['file' => [...], 'application_task_id' => ['required', 'integer', 'exists:application_tasks,id']]`

  **Replace the entire `rules()` method** with:
  ```php
  public function rules(): array
  {
      return [
          'file'                => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:10240'],
          'application_task_id' => ['nullable', 'integer', 'exists:application_tasks,id'],
          'application_id'      => ['nullable', 'integer', 'exists:visa_applications,id'],
      ];
  }
  ```

- [X] T008 [US1] Update `app/Http/Controllers/Client/DocumentController.php` — support null task (app-level upload)

  **Current state**: `store()` always loads a task via `ApplicationTask::with('application')->findOrFail($request->integer('application_task_id'))` and derives the application from the task.

  **Replace the entire `store()` method** with:
  ```php
  public function store(UploadDocumentRequest $request): RedirectResponse
  {
      $this->authorize('upload', Document::class);

      $user = Auth::user();
      abort_unless($user instanceof User, 403);

      if ($request->filled('application_task_id')) {
          $task = ApplicationTask::with('application')->findOrFail($request->integer('application_task_id'));
          abort_if($task->application->user_id !== Auth::id(), 403);
          abort_if(in_array($task->status, ['completed', 'rejected']), 422, __('documents.upload_error_task_closed'));
          $application = $task->application;
      } else {
          $task = null;
          $application = \App\Models\VisaApplication::findOrFail($request->integer('application_id'));
          abort_if($application->user_id !== Auth::id(), 403);
      }

      $this->documentService->upload($application, $task, $request->file('file'), $user);

      return redirect()->route('client.dashboard', ['tab' => 'documents'])
          ->with('success', __('documents.upload_success'));
  }
  ```

  Add these imports at the top of the file if not already present:
  ```php
  use App\Models\VisaApplication;
  ```
  (The file already imports `ApplicationTask`, `Document`, `User`, `DocumentService`, `RedirectResponse`, `Auth`.)

**Checkpoint**: A client can POST to `/client/documents` with a file and either `application_task_id` or `application_id` and the upload succeeds.

---

## Phase 4: User Story 2 — File Validation (Upload Caps + Closed Task + DOCX) ✅

**Goal**: Uploads are capped at 10 per task / 10 per application-level. Closed tasks block uploads. DOCX files are accepted.

**Independent Test**: Try uploading an 11th document to the same task → get a 422 error. Try uploading to a task with status `completed` → get a 422. Try uploading a `.docx` file → succeeds.

### Implementation for User Story 2

- [X] T009 [US2] Update `app/Services/Documents/DocumentService.php` — add document cap enforcement

  **Add these lines** at the very start of the `upload()` method body, before the `$storedFilename = ...` line:
  ```php
  if ($task !== null) {
      abort_if(
          Document::where('application_task_id', $task->id)->count() >= 10,
          422,
          __('documents.upload_error_cap_reached')
      );
  } else {
      abort_if(
          Document::where('application_id', $application->id)
                  ->whereNull('application_task_id')
                  ->count() >= 10,
          422,
          __('documents.upload_error_cap_reached')
      );
  }
  ```

- [X] T010 [P] [US2] Update `app/Http/Requests/Admin/AdminUploadDocumentRequest.php` — make task optional, add DOCX

  **Replace the entire `rules()` method** with:
  ```php
  public function rules(): array
  {
      return [
          'file'                => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:10240'],
          'application_task_id' => ['nullable', 'integer', 'exists:application_tasks,id'],
      ];
  }
  ```

- [X] T011 [P] [US2] Update `app/Http/Requests/Reviewer/UploadDocumentRequest.php` — make task optional, add DOCX

  **Replace the entire `rules()` method** with:
  ```php
  public function rules(): array
  {
      return [
          'file'                => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:10240'],
          'application_task_id' => ['nullable', 'integer', 'exists:application_tasks,id'],
      ];
  }
  ```

- [X] T012 [P] [US2] Add language strings for upload errors to `resources/lang/en/documents.php`

  **Add these keys** inside the returned array (after the last existing entry, before the closing `]`):
  ```php
  'upload_error_task_closed'  => 'This task is closed and no longer accepts document uploads.',
  'upload_error_cap_reached'  => 'The maximum number of documents (10) has been reached for this slot.',
  'application_documents'     => 'Application Documents',
  'upload_application_level'  => 'Upload General Document',
  ```

- [X] T013 [P] [US2] Add language strings for upload errors to `resources/lang/ar/documents.php`

  **Add these keys** inside the returned array:
  ```php
  'upload_error_task_closed'  => 'هذه المهمة مغلقة ولا تقبل رفع مستندات جديدة.',
  'upload_error_cap_reached'  => 'تم الوصول إلى الحد الأقصى للمستندات (10) لهذا القسم.',
  'application_documents'     => 'مستندات الطلب',
  'upload_application_level'  => 'رفع مستند عام',
  ```

**Checkpoint**: Uploading an 11th file to a task, uploading to a closed task, or uploading a non-docx bad file all return validation errors with readable messages.

---

## Phase 5: User Story 3 — Reviewer Uploads with Attribution (P2)

**Goal**: Reviewers can upload documents to assigned applications (task-attached or app-level). All reviewer uploads are labelled `source_type = 'reviewer'`.

**Independent Test**: Log in as a reviewer. Upload a document to an assigned application (no task selected) → document appears with `source_type = 'reviewer'` and reviewer's name. Attempt to upload to an unassigned application → 403.

### Implementation for User Story 3

- [X] T014 [US3] Read `app/Models/VisaApplication.php` to confirm the field name for reviewer assignment (look for `assigned_reviewer_id` or a relationship named `reviewer`). Note the exact field name before writing T015.

- [X] T015 [US3] Update `app/Http/Controllers/Reviewer/DocumentController.php` — add assignment check and null task support

  **Current state**: `store()` loads task via `ApplicationTask::findOrFail(...)`, checks `$task->application_id !== $application->id`, passes `'reviewer'` as source_type.

  **Replace the entire `store()` method** with (substitute `assigned_reviewer_id` with the actual field name found in T014 if different):
  ```php
  public function store(UploadDocumentRequest $request, VisaApplication $application): RedirectResponse
  {
      $this->authorize('reviewerUpload', Document::class);

      // Enforce reviewer is assigned to this application
      abort_if($application->assigned_reviewer_id !== Auth::id(), 403);

      $user = Auth::user();
      abort_unless($user instanceof User, 403);

      if ($request->filled('application_task_id')) {
          $task = ApplicationTask::findOrFail($request->integer('application_task_id'));
          abort_if($task->application_id !== $application->id, 404);
      } else {
          $task = null;
      }

      $this->documentService->upload($application, $task, $request->file('file'), $user, 'reviewer');

      return redirect()
          ->route('reviewer.applications.show', $application)
          ->with('success', __('documents.reviewer_upload_success'));
  }
  ```

  Add this import at the top if not already present:
  ```php
  use App\Models\ApplicationTask;
  ```
  (Check existing imports — `VisaApplication`, `Document`, `User`, `DocumentService`, `RedirectResponse`, `Auth` are already there.)

- [X] T016 [P] [US3] Add language string `reviewer_upload_success` to `resources/lang/en/documents.php`

  **Add** inside the returned array:
  ```php
  'reviewer_upload_success' => 'Document uploaded successfully.',
  ```

- [X] T017 [P] [US3] Add language string `reviewer_upload_success` to `resources/lang/ar/documents.php`

  **Add** inside the returned array:
  ```php
  'reviewer_upload_success' => 'تم رفع المستند بنجاح.',
  ```

**Checkpoint**: A reviewer uploading to their assigned application succeeds with correct attribution. Uploading to an unassigned application returns 403.

---

## Phase 6: User Story 4 — Role-Based Delete Permissions (P2)

**Goal**: Clients can delete their own documents while the task is open. Clients cannot delete reviewer/admin documents. Admins can delete any document.

**Independent Test**: Log in as client. Delete a document you uploaded to an open task → success. Try to delete it again from a closed task → blocked. Try to delete a reviewer-uploaded doc → 403. Log in as admin → delete any document → success.

### Implementation for User Story 4

- [X] T018 [US4] Update `app/Policies/DocumentPolicy.php` — add `delete()` gate

  **Add this method** to the `DocumentPolicy` class (after the existing `reviewerUpload()` method):
  ```php
  public function delete(User $user, Document $document): bool
  {
      // Admin can delete any document
      if ($user->can('documents.delete')) {
          return true;
      }

      // Client can only delete their own uploads
      if (! $user->can('documents.delete-own')) {
          return false;
      }

      if ($document->uploaded_by !== $user->id) {
          return false;
      }

      // Application-level document (no task): allow deletion anytime
      if ($document->application_task_id === null) {
          return true;
      }

      // Task-attached: only allow if task is still open (not completed or rejected)
      $task = $document->task;
      return $task !== null && ! in_array($task->status, ['completed', 'rejected']);
  }
  ```

  The `Document` model already has a `task()` relationship (`belongsTo(ApplicationTask::class, 'application_task_id')`), so `$document->task` is safe to call.

- [X] T019 [US4] Update `app/Services/Documents/DocumentService.php` — add `delete()` method

  **Add this method** to the `DocumentService` class (after the existing `serve()` method):
  ```php
  public function delete(Document $document, User $actor): void
  {
      $reference = $document->application?->reference_number ?? 'unknown';

      Storage::disk($document->disk)->delete($document->path);

      $this->auditLog->log('document_deleted', $actor, [
          'document_id' => $document->id,
          'reference'   => $reference,
      ]);

      $document->delete();
  }
  ```

  The `Storage` facade is already imported at the top of this file.

- [X] T020 [US4] Update `app/Http/Controllers/Client/DocumentController.php` — add `destroy()` method

  **Add this method** to the `Client\DocumentController` class (after the existing `store()` method):
  ```php
  public function destroy(Document $document): RedirectResponse
  {
      $this->authorize('delete', $document);

      $user = Auth::user();
      abort_unless($user instanceof User, 403);

      $this->documentService->delete($document, $user);

      return redirect()->route('client.dashboard', ['tab' => 'documents'])
          ->with('success', __('documents.delete_success'));
  }
  ```

- [X] T021 [US4] Add DELETE route for client in `routes/web.php`

  **Current state**: The client route group (lines 44-51) ends with the payments routes.

  **Add this line** inside the client route group, after the `Route::post('/client/documents', ...)` line:
  ```php
  Route::delete('/client/documents/{document}', [ClientDocumentController::class, 'destroy'])->middleware('active')->name('client.documents.destroy');
  ```

- [X] T022 [P] [US4] Add language strings for delete to `resources/lang/en/documents.php`

  **Add** inside the returned array:
  ```php
  'delete_success'           => 'Document deleted successfully.',
  'delete_confirm'           => 'Are you sure you want to delete this document? This cannot be undone.',
  'delete_error_task_closed' => 'This document cannot be deleted because the task is closed.',
  'delete_error_forbidden'   => 'You do not have permission to delete this document.',
  ```

- [X] T023 [P] [US4] Add language strings for delete to `resources/lang/ar/documents.php`

  **Add** inside the returned array:
  ```php
  'delete_success'           => 'تم حذف المستند بنجاح.',
  'delete_confirm'           => 'هل أنت متأكد من حذف هذا المستند؟ لا يمكن التراجع عن هذا الإجراء.',
  'delete_error_task_closed' => 'لا يمكن حذف هذا المستند لأن المهمة مغلقة.',
  'delete_error_forbidden'   => 'ليس لديك صلاحية لحذف هذا المستند.',
  ```

**Checkpoint**: Client can delete own document on open task. Client blocked from deleting on closed task. Client cannot delete reviewer docs. All actions show the correct flash message.

---

## Phase 7: User Story 5 — Admin Views and Manages All Documents (P3)

**Goal**: Admins can delete any document. Admin-uploaded documents are correctly labelled `source_type = 'admin'` (currently they default to `'client'` due to a bug).

**Independent Test**: Log in as admin. View an application's documents page. Delete a document → it disappears from the list and the audit log records the deletion. Upload a document as admin → its `source_type` in the DB is `'admin'`, not `'client'`.

### Implementation for User Story 5

- [X] T024 [US5] Fix `app/Http/Controllers/Admin/DocumentController.php` — correct source_type and add `destroy()`

  **Fix 1 (source_type bug)**: In the existing `store()` method, the call is:
  ```php
  $this->documentService->upload($application, $task, $request->file('file'), $user);
  ```
  Change it to:
  ```php
  $this->documentService->upload($application, $task, $request->file('file'), $user, 'admin');
  ```

  **Fix 2 (null task support)**: In the existing `store()` method:
  - `$request->integer('application_task_id')` will return `0` if the field is empty/null, which causes `findOrFail(0)` to throw a 404 when no task is selected.

  **Replace the existing `store()` method** with:
  ```php
  public function store(AdminUploadDocumentRequest $request, VisaApplication $application): RedirectResponse
  {
      $this->authorize('adminUpload', Document::class);

      $user = Auth::user();
      abort_unless($user instanceof User, 403);

      if ($request->filled('application_task_id')) {
          $task = ApplicationTask::findOrFail($request->integer('application_task_id'));
          abort_if($task->application_id !== $application->id, 404);
      } else {
          $task = null;
      }

      $this->documentService->upload($application, $task, $request->file('file'), $user, 'admin');

      return redirect()->route('admin.applications.documents.index', $application)
          ->with('success', __('documents.admin_upload_success'));
  }
  ```

  **Add `destroy()` method** to the `Admin\DocumentController` class (after the `store()` method):
  ```php
  public function destroy(VisaApplication $application, Document $document): RedirectResponse
  {
      $this->authorize('delete', $document);

      abort_if($document->application_id !== $application->id, 404);

      $user = Auth::user();
      abort_unless($user instanceof User, 403);

      $this->documentService->delete($document, $user);

      return redirect()->route('admin.applications.documents.index', $application)
          ->with('success', __('documents.delete_success'));
  }
  ```

  Add this import at the top if not already present (it's already there per the existing file):
  ```php
  use App\Models\ApplicationTask;
  ```

- [X] T025 [US5] Add DELETE route for admin in `routes/web.php`

  **Current state**: The admin route group (lines 53-67) ends with payments routes.

  **Add this line** after the existing `Route::post('/applications/{application}/documents', ...)` line in the admin group:
  ```php
  Route::delete('/applications/{application}/documents/{document}', [AdminDocumentController::class, 'destroy'])->middleware('can:dashboard.admin')->name('applications.documents.destroy');
  ```

**Checkpoint**: Admin can delete any document from any application. The admin's uploaded documents show `source_type = 'admin'`. The audit log records the deletion.

---

## Phase 8: Tests (Feature Tests for All New Behaviour)

**Purpose**: Cover all new capabilities and regression-protect existing ones.

- [X] T026 [P] Update `tests/Feature/Client/DocumentUploadTest.php` — add tests for new client behaviours

  **Add the following test methods** to the existing test class. Each test should use `RefreshDatabase`, create a client user with the `client` role, and create a `VisaApplication` belonging to that user.

  ```php
  /** @test */
  public function client_can_upload_application_level_document_without_task(): void
  {
      // Create client user with application, no task_id in request
      // POST to /client/documents with application_id and file
      // Assert: 302 redirect, document exists in DB with application_task_id = null
  }

  /** @test */
  public function client_upload_to_closed_task_is_rejected(): void
  {
      // Create task with status = 'completed'
      // POST to /client/documents with that task's id
      // Assert: 422 response
  }

  /** @test */
  public function client_cannot_upload_more_than_10_documents_to_same_task(): void
  {
      // Create 10 existing Document records for the same task
      // POST one more
      // Assert: 422 response
  }

  /** @test */
  public function client_cannot_upload_more_than_10_application_level_documents(): void
  {
      // Create 10 existing Document records with application_task_id = null for the application
      // POST one more with application_id only
      // Assert: 422 response
  }

  /** @test */
  public function client_can_delete_own_document_on_open_task(): void
  {
      // Create document owned by client on open task
      // DELETE /client/documents/{document}
      // Assert: 302 redirect, document no longer in DB
  }

  /** @test */
  public function client_cannot_delete_own_document_on_closed_task(): void
  {
      // Create document owned by client, task status = 'completed'
      // DELETE /client/documents/{document}
      // Assert: 403 response, document still in DB
  }

  /** @test */
  public function client_cannot_delete_reviewer_uploaded_document(): void
  {
      // Create document with source_type = 'reviewer', uploaded_by = reviewer user
      // Authenticate as client who owns the application
      // DELETE /client/documents/{document}
      // Assert: 403 response
  }

  /** @test */
  public function client_can_upload_docx_file(): void
  {
      // POST with a fake docx file (UploadedFile::fake()->create('test.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'))
      // Assert: 302 redirect, document in DB
  }
  ```

- [X] T027 [P] Update `tests/Feature/Admin/DocumentAdminTest.php` — add tests for admin delete and source_type fix

  **Add the following test methods**:

  ```php
  /** @test */
  public function admin_uploaded_documents_have_source_type_admin(): void
  {
      // POST to /admin/applications/{application}/documents with file
      // Assert: document in DB has source_type = 'admin'
  }

  /** @test */
  public function admin_can_upload_application_level_document_without_task(): void
  {
      // POST to /admin/applications/{application}/documents with file but no application_task_id
      // Assert: document in DB with application_task_id = null
  }

  /** @test */
  public function admin_can_delete_any_document(): void
  {
      // Create document on an application
      // DELETE /admin/applications/{application}/documents/{document}
      // Assert: 302 redirect, document not in DB
  }

  /** @test */
  public function admin_delete_returns_404_if_document_does_not_belong_to_application(): void
  {
      // Create document belonging to application A
      // DELETE using application B's ID and the document's ID
      // Assert: 404 response
  }
  ```

- [X] T028 [P] Update `tests/Feature/Reviewer/DocumentReviewerTest.php` — add reviewer assignment and app-level upload tests

  **Add the following test methods**:

  ```php
  /** @test */
  public function reviewer_can_upload_to_assigned_application(): void
  {
      // Create reviewer, assign to application (set assigned_reviewer_id)
      // POST to /reviewer/applications/{application}/documents with file
      // Assert: 302 redirect, document in DB with source_type = 'reviewer'
  }

  /** @test */
  public function reviewer_cannot_upload_to_unassigned_application(): void
  {
      // Create reviewer NOT assigned to the application
      // POST to /reviewer/applications/{application}/documents with file
      // Assert: 403 response
  }

  /** @test */
  public function reviewer_can_upload_application_level_document(): void
  {
      // POST without application_task_id to assigned application
      // Assert: document in DB with application_task_id = null, source_type = 'reviewer'
  }
  ```

- [X] T029 Run full test suite with `php artisan test --filter=Document` and confirm all tests pass

---

## Phase 9: Polish & Cross-Cutting Concerns

- [X] T030 [P] Run `php artisan route:list | grep document` and confirm all 7 document routes exist:
  1. `GET /documents/{document}/download` → `documents.download`
  2. `POST /client/documents` → `client.documents.store`
  3. `DELETE /client/documents/{document}` → `client.documents.destroy` ← NEW
  4. `GET /admin/applications/{application}/documents` → `admin.applications.documents.index`
  5. `POST /admin/applications/{application}/documents` → `admin.applications.documents.store`
  6. `DELETE /admin/applications/{application}/documents/{document}` → `admin.applications.documents.destroy` ← NEW
  7. `POST /reviewer/applications/{application}/documents` → `reviewer.applications.documents.store`

- [X] T031 [P] Verify the `Document` model's `$fillable` array in `app/Models/Document.php` includes `application_task_id`. It already does — confirm and do not change.

- [X] T032 Manually test the upload cap: Create 10 documents for one task in the DB and attempt an 11th upload via the UI or `php artisan tinker`. Confirm the 422 abort fires.

- [X] T033 Run `php artisan test` (full suite) and confirm no regressions.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies
- **Phase 2 (Migrations)**: Depends on Phase 1 → **BLOCKS all phases**
- **Phase 3 (US1 — Client Upload)**: Depends on Phase 2
- **Phase 4 (US2 — Validation)**: Depends on Phase 3 (needs updated service signature from T006)
- **Phase 5 (US3 — Reviewer)**: Depends on Phase 2; can run in parallel with Phase 3
- **Phase 6 (US4 — Client/Admin Delete)**: Depends on Phase 2; can run in parallel with Phases 3–5
- **Phase 7 (US5 — Admin Delete)**: Depends on Phase 6 (needs `DocumentService::delete()` from T019)
- **Phase 8 (Tests)**: Depends on all implementation phases
- **Phase 9 (Polish)**: Depends on Phase 8

### User Story Dependencies

- **US1 (P1)**: Start after Phase 2 — T006, T007, T008
- **US2 (P1)**: Start after T006 (needs updated `upload()` signature) — T009, T010, T011, T012, T013
- **US3 (P2)**: Start after Phase 2 — T014, T015, T016, T017 (parallel with US1/US2)
- **US4 (P2)**: Start after Phase 2 — T018, T019, T020, T021, T022, T023 (parallel with US1–US3)
- **US5 (P3)**: Start after T019 (needs `DocumentService::delete()`) — T024, T025

### Within Each Story

- T006 (service signature) → T008 (controller) and T009 (cap check)
- T019 (service delete) → T020 (client destroy) and T024 (admin destroy)
- T014 (read model) → T015 (reviewer controller)

### Parallel Opportunities

Tasks marked [P] within the same phase can be worked simultaneously:
- T010, T011, T012, T013 (Phase 4) — all different files
- T016, T017 (Phase 5) — both lang files
- T022, T023 (Phase 6) — both lang files
- T026, T027, T028 (Phase 8) — all different test files
- T030, T031 (Phase 9) — read-only verifications

---

## Parallel Example: User Story 1 + User Story 3

```
# After Phase 2 migrations are complete:

Parallel stream A (US1):
  T006 → T007 → T008 → T009

Parallel stream B (US3):
  T014 → T015 → T016 + T017 (parallel lang files)
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2)

1. Complete Phase 1: Setup check
2. Complete Phase 2: Run migrations ← CRITICAL BLOCKER
3. Complete Phase 3: US1 (client uploads, null task)
4. Complete Phase 4: US2 (validation caps, DOCX)
5. **STOP and VALIDATE**: Clients can upload with/without task, caps enforced, DOCX accepted
6. Continue to Phase 5 (US3) → Phase 6 (US4) → Phase 7 (US5)

### Incremental Delivery

1. Phase 2 done → DB ready
2. Phase 3 + Phase 4 done → Clients fully upload
3. Phase 5 done → Reviewers upload with attribution
4. Phase 6 + Phase 7 done → Deletion for all roles
5. Phase 8 + Phase 9 → Tests green, feature complete

---

## Notes

- **Do not modify** `app/Models/Document.php` — it is already correct
- **Do not modify** `app/Http/Controllers/DocumentController.php` (root download controller) — no changes needed
- **Do not modify** existing permissions in `database/migrations/2026_03_20_000001_seed_roles_permissions_and_visa_types.php` — only add the new T004 migration
- All `abort_if(...)` calls in controllers are intentional — do not replace with try/catch
- The `mimes` Laravel validation rule checks **actual file content** (not just extension), satisfying FR-006
- The `DocumentService::upload()` already writes to `'documents/{application_id}/{uuid}.{ext}'` on the configured private disk — do not change the storage path logic
- If `$task->status` values differ from `['completed', 'rejected']`, check `ApplicationTask::$fillable` or a status constants file and use the correct values
