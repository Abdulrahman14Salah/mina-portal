# Tasks: Phase 4 — Document Management System

**Input**: Design documents from `/specs/004-document-management/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/routes.md ✅, quickstart.md ✅
**Branch**: `004-document-management`
**Stack**: PHP 8.2+ / Laravel 11 / Breeze (Blade) / Alpine.js v3 / spatie/laravel-permission v6+ / MySQL (dev) / SQLite in-memory (tests) / Private local disk (dev) / S3 (prod)

> **Context for implementors**: This is Phase 4 of a Visa Application Client Portal. Phases 1–3 are complete. Phase 4 adds: (1) a `documents` table storing file metadata; (2) `DocumentService` that uploads files to a private disk and serves them back via a policy-gated controller; (3) client document upload on the Documents tab of the client dashboard; (4) reviewer inline document list on the application detail page; (5) admin upload on behalf of a client via a minimal admin application page. Access control is enforced by `DocumentPolicy` for every action. All strings go through `__()`. No business logic in controllers or Blade.
>
> **⚠️ CRITICAL: Phase 3 file modifications required**
> - `database/seeders/WorkflowStepTemplateSeeder.php` — change `firstOrCreate` to `updateOrCreate` and set `is_document_required = true` for steps 3 and 4
> - `database/seeders/RolePermissionSeeder.php` — add 3 new permissions and update client + reviewer assignments
> - `app/Models/VisaApplication.php` — add `documents()` HasMany relationship
> - `app/Models/ApplicationTask.php` — add `documents()` HasMany relationship
> - `app/Http/Controllers/Client/DashboardController.php` — extend eager-load to include `tasks.template` and `tasks.documents.uploader`
> - `app/Http/Controllers/Reviewer/ApplicationController.php` — extend `loadMissing` to include `tasks.documents.uploader`
> - `app/Providers/AppServiceProvider.php` — register `DocumentPolicy`
> - `routes/web.php` — add shared download route, client upload route, admin application and document routes
>
> **⚠️ Lang file pattern**: Real content in `resources/lang/{locale}/documents.php`. Proxy in `lang/{locale}/documents.php` containing `return require resource_path('lang/{locale}/documents.php')`. Both files must be created for each locale.
>
> **⚠️ `is_document_required` access**: The `application_tasks` table does NOT have `is_document_required`. Access it via `$task->template->is_document_required` (requires `template` relationship eager-loaded). Steps 3 ("Document Request") and 4 ("Document Review") are the document-required steps.
>
> **⚠️ File serving pattern**: Documents are stored on a named disk (e.g., `'local'` or `'s3'`) recorded in `documents.disk`. For local disk: `Storage::disk($document->disk)->download($document->path, $document->original_filename)` returns a StreamedResponse. For S3: redirect to `Storage::disk('s3')->temporaryUrl($document->path, now()->addMinutes(5))`.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story ([US1]–[US3])

---

## Phase 1: Setup (Database Foundation)

**Purpose**: Create the documents table, update the step template seeder to flag document-required steps, and add the three new permissions before any application code is written.

- [X] T001 Create the `documents` migration: run `php artisan make:migration create_documents_table`. In `up()`, define in this exact order: `$table->id()`, `$table->foreignId('application_id')->constrained('visa_applications')->cascadeOnDelete()`, `$table->foreignId('application_task_id')->constrained('application_tasks')->restrictOnDelete()`, `$table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete()`, `$table->string('original_filename', 255)`, `$table->string('stored_filename', 255)`, `$table->string('disk', 50)`, `$table->string('path', 500)`, `$table->string('mime_type', 100)`, `$table->unsignedBigInteger('size')`, `$table->timestamps()`. In `down()`, call `Schema::dropIfExists('documents')`. Run `php artisan migrate`.

- [X] T002 Update `database/seeders/WorkflowStepTemplateSeeder.php` for Phase 4: (1) Change the `$steps` array to add `'is_document_required'` to each entry: position 1 → `false`, position 2 → `false`, position 3 ("Document Request") → `true`, position 4 ("Document Review") → `true`, position 5 → `false`, position 6 → `false`. (2) Change `WorkflowStepTemplate::firstOrCreate(...)` to `WorkflowStepTemplate::updateOrCreate(...)` so existing rows get updated when the seeder runs again. The `updateOrCreate` first arg (search keys) stays `['visa_type_id' => $visaType->id, 'position' => $step['position']]`; the second arg (values to set/update) becomes `['name' => $step['name'], 'description' => $step['description'], 'is_document_required' => $step['is_document_required']]`.

- [X] T003 Update `database/seeders/RolePermissionSeeder.php`: (1) In the `$permissions` array, add three new entries: `'documents.upload'`, `'documents.download'`, `'documents.admin-upload'`. (2) Change the existing `$client->givePermissionTo(['dashboard.client'])` line to `$client->givePermissionTo(['dashboard.client', 'documents.upload'])`. (3) Change the existing `$reviewer->givePermissionTo([...])` line to include `'documents.download'` — full list: `['dashboard.reviewer', 'tasks.view', 'tasks.advance', 'tasks.reject', 'documents.download']`. (4) `$admin->givePermissionTo($permissions)` already grants admin all permissions — no change needed. Do NOT re-run the seeder yet; it will run via `migrate:fresh --seed` in Phase 6.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Models, DocumentService, DocumentPolicy, Form Requests, and language files — everything all user stories depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T004 [P] Create `app/Models/Document.php`: namespace `App\Models`. Class extends `Illuminate\Database\Eloquent\Model`. Add `use HasFactory`. Set `protected $fillable = ['application_id', 'application_task_id', 'uploaded_by', 'original_filename', 'stored_filename', 'disk', 'path', 'mime_type', 'size']`. Set `protected $casts = ['size' => 'integer']`. Add three relationships: `public function application(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(VisaApplication::class, 'application_id'); }`, `public function task(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(ApplicationTask::class, 'application_task_id'); }`, `public function uploader(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }`. Add all required `use` imports at the top.

- [X] T005 [P] Add `documents()` relationship to `app/Models/VisaApplication.php`: add `use Illuminate\Database\Eloquent\Relations\HasMany;` import if not present, then add the method: `public function documents(): HasMany { return $this->hasMany(Document::class, 'application_id'); }`. Also add `use App\Models\Document;` import. This is a one-method addition — do not remove any existing code.

- [X] T006 [P] Add `documents()` relationship to `app/Models/ApplicationTask.php`: add `public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(\App\Models\Document::class, 'application_task_id'); }`. This is a one-method addition — do not remove any existing code.

- [X] T007 [P] Create `app/Policies/DocumentPolicy.php`: namespace `App\Policies`. Add `use App\Models\Document; use App\Models\User; use App\Models\VisaApplication;`. Implement three public methods:
  - `public function upload(User $user): bool { return $user->can('documents.upload'); }` — checks if user has upload permission (clients and admins).
  - `public function download(User $user, Document $document): bool { if ($user->can('documents.download')) { return true; } return $document->application->user_id === $user->id; }` — allows reviewers/admins via permission, OR the owning client via ownership check.
  - `public function adminUpload(User $user): bool { return $user->can('documents.admin-upload'); }` — admin-only upload on behalf of client.

- [X] T008 Register `DocumentPolicy` in `app/Providers/AppServiceProvider.php`: add `use App\Models\Document; use App\Policies\DocumentPolicy;` imports, then in `boot()` add `Gate::policy(Document::class, DocumentPolicy::class)` alongside the existing `ApplicationTask` and `VisaApplication` policy registrations.

- [X] T009 Create `app/Services/Documents/DocumentService.php`: namespace `App\Services\Documents`. Constructor-inject `\App\Services\Auth\AuditLogService $auditLog`. Implement two public methods:

  **Method 1 — `upload(\App\Models\VisaApplication $application, \App\Models\ApplicationTask $task, \Illuminate\Http\UploadedFile $file, \App\Models\User $uploader): \App\Models\Document`**:
  (1) Generate stored filename: `$storedFilename = \Illuminate\Support\Str::uuid() . '.' . $file->getClientOriginalExtension();`
  (2) Store file: `$path = $file->storeAs('documents/' . $application->id, $storedFilename, config('filesystems.default', 'local'));`
  (3) Determine disk: `$disk = config('filesystems.default', 'local');`
  (4) Create DB record: `$document = \App\Models\Document::create(['application_id' => $application->id, 'application_task_id' => $task->id, 'uploaded_by' => $uploader->id, 'original_filename' => $file->getClientOriginalName(), 'stored_filename' => $storedFilename, 'disk' => $disk, 'path' => $path, 'mime_type' => $file->getMimeType(), 'size' => $file->getSize()]);`
  (5) Conditional status transition — atomic, no race condition: `\App\Models\VisaApplication::whereKey($application->id)->where('status', 'in_progress')->update(['status' => 'awaiting_documents']);`
  (6) Audit log: `$this->auditLog->log('document_uploaded', $uploader, ['document_id' => $document->id, 'reference' => $application->reference_number]);`
  (7) Return `$document`.

  **Method 2 — `serve(\App\Models\Document $document, \App\Models\User $actor): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\RedirectResponse`**:
  (1) Audit log: `$this->auditLog->log('document_downloaded', $actor, ['document_id' => $document->id, 'reference' => $document->application->reference_number]);`
  (2) If S3: `if ($document->disk === 's3') { return \Illuminate\Support\Facades\Redirect::to(\Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($document->path, now()->addMinutes(5))); }`
  (3) Otherwise local: `return \Illuminate\Support\Facades\Storage::disk($document->disk)->download($document->path, $document->original_filename);`

  Add all required `use` statements at the top.

- [X] T010 [P] Create `app/Http/Requests/Client/UploadDocumentRequest.php`: namespace `App\Http\Requests\Client`. Extends `Illuminate\Foundation\Http\FormRequest`. `authorize()` returns `true`. `rules()` returns: `['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], 'application_task_id' => ['required', 'integer', 'exists:application_tasks,id']]`. The `max:10240` is in kilobytes (= 10 MB). The `mimes` rule validates actual file contents via MIME type detection — not just the extension.

- [X] T011 [P] Create `app/Http/Requests/Admin/AdminUploadDocumentRequest.php`: namespace `App\Http\Requests\Admin`. Extends `Illuminate\Foundation\Http\FormRequest`. `authorize()` returns `true`. `rules()` returns: `['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], 'application_task_id' => ['required', 'integer', 'exists:application_tasks,id']]`. Rules are identical to the client request; separate class maintains modular boundary.

- [X] T012 [P] Create `resources/lang/en/documents.php`: return an array with exactly these keys: `'upload_success' => 'Document uploaded successfully.'`, `'admin_upload_success' => 'Document uploaded on behalf of the client.'`, `'upload' => 'Upload Document'`, `'choose_file' => 'Choose file (PDF, JPG, JPEG, PNG — max 10 MB)'`, `'no_documents' => 'No documents have been uploaded for this step yet.'`, `'no_document_steps' => 'No document uploads are required at this stage.'`, `'documents_for_step' => 'Documents for :step'`, `'uploaded_by_staff' => 'Uploaded by staff'`, `'uploaded_on' => 'Uploaded :date'`, `'download' => 'Download'`, `'documents_section' => 'Documents'`, `'file_label' => 'File'`, `'step_label' => 'Step'`, `'all_documents' => 'All Documents'`, `'upload_on_behalf' => 'Upload Document on Behalf of Client'`, `'select_step' => 'Select workflow step'`.

- [X] T013 [P] Create `resources/lang/ar/documents.php`: return the same keys as T012 with Arabic translations: `'upload_success' => 'تم رفع المستند بنجاح.'`, `'admin_upload_success' => 'تم رفع المستند نيابةً عن العميل.'`, `'upload' => 'رفع مستند'`, `'choose_file' => 'اختر ملفاً (PDF أو JPG أو JPEG أو PNG — الحد الأقصى 10 ميغابايت)'`, `'no_documents' => 'لم يتم رفع أي مستندات لهذه الخطوة بعد.'`, `'no_document_steps' => 'لا يلزم رفع مستندات في هذه المرحلة.'`, `'documents_for_step' => 'مستندات :step'`, `'uploaded_by_staff' => 'تم الرفع بواسطة الموظفين'`, `'uploaded_on' => 'تم الرفع بتاريخ :date'`, `'download' => 'تحميل'`, `'documents_section' => 'المستندات'`, `'file_label' => 'الملف'`, `'step_label' => 'الخطوة'`, `'all_documents' => 'جميع المستندات'`, `'upload_on_behalf' => 'رفع مستند نيابةً عن العميل'`, `'select_step' => 'اختر خطوة سير العمل'`.

- [X] T014 [P] Create `lang/en/documents.php`: the file must contain exactly one line of PHP: `return require resource_path('lang/en/documents.php');`. This is the Laravel 11 lang path proxy pattern used in this project.

- [X] T015 [P] Create `lang/ar/documents.php`: the file must contain exactly one line of PHP: `return require resource_path('lang/ar/documents.php');`.

- [X] T016 [P] Create `app/Http/Controllers/DocumentController.php` (root namespace, NOT inside Client/ or Admin/): namespace `App\Http\Controllers`. Class extends `Controller`. Constructor-inject `\App\Services\Documents\DocumentService $documentService`. Implement one method: `public function download(\App\Models\Document $document): \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\RedirectResponse { $this->authorize('download', $document); return $this->documentService->serve($document, auth()->user()); }`. Add all required `use` imports. **Note**: Route model binding automatically resolves `{document}` to an `App\Models\Document` instance.

- [X] T017 Add the shared download route and client upload route to `routes/web.php`: (1) Inside the existing `Route::middleware(['auth', 'verified'])->group(function () { ... })` block (the one that contains the admin dashboard and client dashboard), add the **shared download route**: `Route::get('/documents/{document}/download', [\App\Http\Controllers\DocumentController::class, 'download'])->name('documents.download');` (2) In the same group, add the **client upload route**: `Route::post('/client/documents', [\App\Http\Controllers\Client\DocumentController::class, 'store'])->middleware('active')->name('client.documents.store');` (3) Add the new aliases at the top of the file alongside the existing imports: `use App\Http\Controllers\DocumentController; use App\Http\Controllers\Client\DocumentController as ClientDocumentController;`.

**Checkpoint**: Run `php artisan migrate:fresh --seed`. Verify: `documents` table exists; `workflow_step_templates` rows for positions 3 and 4 have `is_document_required = 1`; `permissions` table has `documents.upload`, `documents.download`, `documents.admin-upload`; `client` role has `documents.upload`; `reviewer` role has `documents.download`.

---

## Phase 3: User Story 1 — Client Uploads Required Documents + Access Control (Priority: P1) 🎯 MVP

**Goal**: A client navigates to their Documents tab and uploads a file for the active document-required workflow step. The upload is stored securely under a UUID filename, the document appears in the list, and the application status transitions to `awaiting_documents`. Cross-client and unauthenticated access to download URLs returns 403 or login redirect.

**Independent Test**: Advance an application to step 3 ("Document Request") via the reviewer panel. Log in as the client. Visit `/client/dashboard/documents`. Upload a valid PDF. Verify the document appears with filename and date. Verify `visa_applications.status = 'awaiting_documents'`. Attempt to download the document URL as a different client → 403.

### Implementation for User Story 1

- [X] T018 [US1] Create `app/Http/Controllers/Client/DocumentController.php`: namespace `App\Http\Controllers\Client`. Extends `\App\Http\Controllers\Controller`. Constructor-inject `\App\Services\Documents\DocumentService $documentService`. Implement one method:
  `public function store(\App\Http\Requests\Client\UploadDocumentRequest $request): \Illuminate\Http\RedirectResponse { $this->authorize('upload', \App\Models\Document::class); $task = \App\Models\ApplicationTask::with('application')->findOrFail($request->application_task_id); abort_if($task->application->user_id !== auth()->id(), 403); $this->documentService->upload($task->application, $task, $request->file('file'), auth()->user()); return redirect()->route('client.dashboard', ['tab' => 'documents'])->with('success', __('documents.upload_success')); }`
  Add all `use` imports at the top. **Note**: The `authorize('upload', Document::class)` calls `DocumentPolicy::upload()` which checks `documents.upload` permission. The explicit `abort_if` ownership check prevents a client from uploading to another client's task even if they have the permission.

- [X] T019 [US1] Modify `app/Http/Controllers/Client/DashboardController.php`: find the existing eager-load line `VisaApplication::with(['visaType', 'tasks' => fn ($q) => $q->orderBy('position')])` and replace it with: `VisaApplication::with(['visaType', 'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents' => fn ($q) => $q->with('uploader')])])`. This is a single line change that adds eager-loading of `template` (for `is_document_required` access) and `documents.uploader` (for the document list with uploader attribution) for each task.

- [X] T020 [US1] Replace the content of `resources/views/client/dashboard/tabs/documents.blade.php` (currently a stub showing `{{ __('client.empty_documents') }}`). The new content must:

  (1) **Filter to document-required tasks**: `@php $docSteps = $application->tasks->sortBy('position')->filter(fn($t) => $t->template?->is_document_required); @endphp`

  (2) **Empty state when no document steps**: `@if($docSteps->isEmpty()) <div class="rounded-lg bg-white p-10 text-center shadow-sm"><p class="text-gray-500">{{ __('documents.no_document_steps') }}</p></div>`

  (3) **Otherwise loop through document-required tasks**: `@else <div class="space-y-6">@foreach($docSteps as $task)<div class="rounded-lg bg-white p-6 shadow-sm">` — inside each task card:
    - Heading: `<h4 class="font-semibold text-gray-900">{{ $task->name }}</h4>` with step badge using `__('tasks.step_number', ['number' => $task->position])`
    - Status indicator: show a status badge using `__('tasks.status_' . $task->status)` with appropriate colors
    - **Existing uploads list**: `@if($task->documents->isNotEmpty()) <ul class="mt-3 divide-y divide-gray-100">@foreach($task->documents as $doc)<li class="flex items-center justify-between py-2 text-sm"><span class="text-gray-700">{{ $doc->original_filename }}</span> <div class="flex items-center gap-3"><span class="text-xs text-gray-400">{{ $doc->uploader->id === auth()->id() ? __('documents.uploaded_on', ['date' => $doc->created_at->format('d M Y')]) : __('documents.uploaded_by_staff') }}</span><a href="{{ route('documents.download', $doc) }}" class="font-medium text-indigo-600 hover:underline">{{ __('documents.download') }}</a></div></li>@endforeach</ul>@else <p class="mt-2 text-sm text-gray-400">{{ __('documents.no_documents') }}</p>@endif`
    - **Upload form** (only when step is in_progress): `@if($task->status === 'in_progress')<form method="POST" action="{{ route('client.documents.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3">@csrf<input type="hidden" name="application_task_id" value="{{ $task->id }}"><div><label class="block text-sm font-medium text-gray-700">{{ __('documents.file_label') }}</label><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:rounded-md file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-gray-700"><p class="mt-1 text-xs text-gray-400">{{ __('documents.choose_file') }}</p></div>@error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror<button type="submit" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 ms-3">{{ __('documents.upload') }}</button></form>@endif`
  - Close task card and loop: `</div>@endforeach</div>@endif`

  Also add a success flash at the top of the view (before the step loop): `@if(session('success'))<div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif`

- [X] T021 [P] [US1] Write `tests/Feature/Client/DocumentUploadTest.php`: namespace `Tests\Feature\Client`. Use `RefreshDatabase`. In `setUp()`, seed `RolePermissionSeeder`, `VisaTypeSeeder`, `WorkflowStepTemplateSeeder`. Define helper `makeClientWithApplication(): array` that creates a User with role `client`, creates a VisaApplication, calls `app(\App\Services\Tasks\WorkflowService::class)->seedTasksForApplication($application)`, advances to step 3 via `app(\App\Services\Documents\DocumentService::class)` or directly by updating the task status to `in_progress` and the task for position 3 `status = 'in_progress'`, all other tasks `pending`/`completed` as appropriate. Return `['user' => $user, 'application' => $application->fresh(['tasks.template', 'tasks.documents'])]`.

  Use `Illuminate\Http\UploadedFile::fake()` for all file creation. Write these tests:
  1. `test_client_can_upload_valid_pdf()` — actingAs client, post to `client.documents.store` with `UploadedFile::fake()->create('passport.pdf', 100, 'application/pdf')` and the task's `application_task_id`, assertRedirect to client dashboard documents tab, assert 1 document in DB.
  2. `test_invalid_file_type_is_rejected()` — upload `UploadedFile::fake()->create('virus.exe', 50, 'application/octet-stream')`, assertSessionHasErrors('file'), assert 0 documents in DB.
  3. `test_file_over_10mb_is_rejected()` — upload `UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf')` (11 MB in KB), assertSessionHasErrors('file'), assert 0 documents.
  4. `test_upload_transitions_application_to_awaiting_documents()` — upload valid file, assert `$application->fresh()->status === 'awaiting_documents'`.
  5. `test_second_upload_does_not_double_transition()` — upload twice, assert status is still `'awaiting_documents'` (not changed again), assert 2 documents in DB.
  6. `test_cross_client_cannot_download_document()` — upload as client A, create client B, actingAs client B get the download route for the document, assertForbidden().
  7. `test_unauthenticated_cannot_download_document()` — upload as client A, get download route unauthenticated, assertRedirect(route('login')).
  8. `test_client_can_download_own_document()` — upload as client, actingAs same client get download route, assertOk() or assertRedirect() (since local disk download returns 200 streamed response).

  Use `Storage::fake('local')` in setUp to prevent real file writes during tests.

**Checkpoint**: `php artisan serve`, advance an application to step 3 via the reviewer panel. Visit `/client/dashboard/documents` as the client → upload form visible. Upload a PDF → document listed. Verify `visa_applications.status = 'awaiting_documents'`. Verify audit log.

---

## Phase 4: User Story 2 — Reviewer Downloads and Reviews Client Documents (Priority: P2)

**Goal**: A reviewer opens an application at the Document Review step and sees all uploaded documents inline, grouped under their workflow step. The reviewer can download any file with a single click.

**Independent Test**: Upload 2 documents as a client (step 3). Log in as reviewer. Open the application detail page. Verify both documents appear in a "Documents" section below the task list. Download one → file serves correctly. Verify `audit_logs` has `document_downloaded` event.

### Implementation for User Story 2

- [X] T022 [US2] Modify `app/Http/Controllers/Reviewer/ApplicationController.php`: in the `show()` method, find the line `$application->loadMissing(['visaType', 'user', 'tasks' => fn ($q) => $q->orderBy('position')])` and replace it with: `$application->loadMissing(['visaType', 'user', 'tasks' => fn ($q) => $q->orderBy('position')->with(['template', 'documents' => fn ($q) => $q->with('uploader')])])`. This is a single-line change adding `template` and `documents.uploader` to the nested eager-load. No other changes to this file.

- [X] T023 [US2] Update `resources/views/reviewer/applications/show.blade.php`: below the `@foreach ($application->tasks->sortBy('position') as $task)` task list section (i.e., after the `@endforeach` that closes the task list), add a document summary section:

  ```blade
  @php $allDocuments = $application->tasks->flatMap(fn($t) => $t->documents)->sortByDesc('created_at'); @endphp
  @if($allDocuments->isNotEmpty())
  <div class="space-y-4">
      <h3 class="text-lg font-semibold text-gray-900">{{ __('documents.documents_section') }}</h3>
      <div class="rounded-lg bg-white p-6 shadow-sm">
          <ul class="divide-y divide-gray-100">
              @foreach($allDocuments as $doc)
              <li class="flex items-center justify-between py-3 text-sm">
                  <div>
                      <p class="font-medium text-gray-900">{{ $doc->original_filename }}</p>
                      <p class="text-xs text-gray-400">{{ $doc->task->name }} — {{ $doc->created_at->format('d M Y') }} — {{ $doc->uploader->name }}</p>
                  </div>
                  <a href="{{ route('documents.download', $doc) }}" class="font-medium text-indigo-600 hover:underline">{{ __('documents.download') }}</a>
              </li>
              @endforeach
          </ul>
      </div>
  </div>
  @endif
  ```

  **Note**: `$doc->task` lazy-loads if not already loaded; it is acceptable here since each document's task was already eager-loaded as part of `tasks.documents`. The `$doc->task` access uses the `task()` BelongsTo on the Document model (FK: `application_task_id`). If it causes an N+1 issue, replace with a lookup: `$application->tasks->find($doc->application_task_id)?->name`.

- [X] T024 [P] [US2] Write `tests/Feature/Reviewer/DocumentReviewerTest.php`: namespace `Tests\Feature\Reviewer`. Use `RefreshDatabase`. In `setUp()`, seed `RolePermissionSeeder`, `VisaTypeSeeder`, `WorkflowStepTemplateSeeder`. Use `Storage::fake('local')`. Helpers: `makeReviewer()` (same as Phase 3's WorkflowTest), `makeApplicationWithDocument()` — creates a client + application, seeds tasks, uploads a document via `app(\App\Services\Documents\DocumentService::class)->upload(...)` directly. Write tests:
  1. `test_reviewer_sees_documents_on_application_detail()` — actingAs reviewer, get `reviewer.applications.show` for the application, assertOk(), assertSee the document's `original_filename`.
  2. `test_reviewer_can_download_document()` — actingAs reviewer, get `documents.download` route for document, assert status 200 (streamed download for local/fake disk).
  3. `test_reviewer_download_is_audit_logged()` — actingAs reviewer, download a document, assert `DB::table('audit_logs')->where('event', 'document_downloaded')->exists()` is true.
  4. `test_client_cannot_access_another_clients_document_via_reviewer_url()` — create two separate client applications with documents, actingAs clientB, attempt to download clientA's document URL, assertForbidden().

**Checkpoint**: Upload 2 documents as client (step 3). Log in as reviewer, open application detail → documents listed below task list with "Download" links. Click Download → file downloads. Check audit log.

---

## Phase 5: User Story 3 — Admin Uploads Documents on Behalf of a Client (Priority: P3)

**Goal**: An admin navigates to `/admin/applications`, finds an application, and uploads a document on the client's behalf. The upload appears in the client's Documents tab with "Uploaded by staff" attribution.

**Independent Test**: Log in as admin. Visit `/admin/applications` → see all applications. Click to open documents for one → upload a file. Log in as client → verify document appears on Documents tab with staff label.

### Implementation for User Story 3

- [X] T025 [US3] Create `app/Http/Controllers/Admin/ApplicationController.php`: namespace `App\Http\Controllers\Admin`. Extends `\App\Http\Controllers\Controller`. Implement one method: `public function index(): \Illuminate\View\View { abort_if(!auth()->user()->can('dashboard.admin'), 403); $applications = \App\Models\VisaApplication::with(['user', 'visaType'])->orderByDesc('created_at')->paginate(25); return view('admin.applications.index', compact('applications')); }`. Add all `use` imports.

- [X] T026 [US3] Create `app/Http/Controllers/Admin/DocumentController.php`: namespace `App\Http\Controllers\Admin`. Extends `\App\Http\Controllers\Controller`. Constructor-inject `\App\Services\Documents\DocumentService $documentService`. Implement two methods:

  **`index(\App\Models\VisaApplication $application): \Illuminate\View\View`**: `$this->authorize('adminUpload', \App\Models\Document::class); $application->loadMissing(['user', 'visaType', 'tasks' => fn($q) => $q->orderBy('position')->with(['template', 'documents' => fn($q) => $q->with('uploader')])]); return view('admin.applications.documents', compact('application'));`

  **`store(\App\Http\Requests\Admin\AdminUploadDocumentRequest $request, \App\Models\VisaApplication $application): \Illuminate\Http\RedirectResponse`**: `$this->authorize('adminUpload', \App\Models\Document::class); $task = \App\Models\ApplicationTask::findOrFail($request->application_task_id); abort_if($task->application_id !== $application->id, 404); $this->documentService->upload($application, $task, $request->file('file'), auth()->user()); return redirect()->route('admin.applications.documents.index', $application)->with('success', __('documents.admin_upload_success'));`

  Add all `use` imports.

- [X] T027 [US3] Create `resources/views/admin/applications/index.blade.php`: use `<x-app-layout>`. In `<x-slot name="header">`, display `<h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('documents.all_documents') }} — Applications</h2>`. In the main content (py-12 container, max-w-7xl):
  - A white rounded-lg table with columns: Reference, Client, Visa Type, Status, Documents.
  - `@foreach($applications as $application)` — each row shows: `$application->reference_number`, `$application->user->name`, `$application->visaType->name`, `$application->status`, and a link: `<a href="{{ route('admin.applications.documents.index', $application) }}" class="font-medium text-indigo-600 hover:underline">{{ __('documents.documents_section') }}</a>`.
  - Below the table, add `{{ $applications->links() }}` for pagination.

- [X] T028 [US3] Create `resources/views/admin/applications/documents.blade.php`: use `<x-app-layout>`. Header: `<h2>Application {{ $application->reference_number }} — {{ __('documents.all_documents') }}</h2>`. Content (max-w-4xl, py-12, space-y-6):

  (1) **Info card**: show client name, visa type, status.

  (2) **Success flash**: `@if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('success') }}</div>@endif`

  (3) **Admin upload form**: white rounded-lg card. Heading: `{{ __('documents.upload_on_behalf') }}`. Form: `<form method="POST" action="{{ route('admin.applications.documents.store', $application) }}" enctype="multipart/form-data" class="space-y-4">@csrf`. Inside:
  - `<label>{{ __('documents.step_label') }}</label><select name="application_task_id" class="...">@foreach($application->tasks->sortBy('position') as $task)<option value="{{ $task->id }}">{{ $task->position }}. {{ $task->name }}</option>@endforeach</select>`
  - `<label>{{ __('documents.file_label') }}</label><input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png"><p class="text-xs text-gray-400">{{ __('documents.choose_file') }}</p>`
  - `@error('file')<p class="text-sm text-red-600">{{ $message }}</p>@enderror`
  - `<button type="submit">{{ __('documents.upload') }}</button></form>`

  (4) **Existing documents list by step**: loop `@foreach($application->tasks->sortBy('position') as $task)` — for each task that has documents (`$task->documents->isNotEmpty()`): show task name as heading, then a list of documents with filename, uploader name (show `{{ __('documents.uploaded_by_staff') }}` if `$doc->uploader->id !== $application->user_id`, else client name), date, and Download link. Skip tasks with no documents.

- [X] T029 [US3] Add admin application and document routes to `routes/web.php`: inside the existing `Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () { ... })` block, append: `Route::get('/applications', [\App\Http\Controllers\Admin\ApplicationController::class, 'index'])->middleware('can:dashboard.admin')->name('applications.index'); Route::get('/applications/{application}/documents', [\App\Http\Controllers\Admin\DocumentController::class, 'index'])->name('applications.documents.index'); Route::post('/applications/{application}/documents', [\App\Http\Controllers\Admin\DocumentController::class, 'store'])->name('applications.documents.store');`. Add at top of file: `use App\Http\Controllers\Admin\ApplicationController as AdminApplicationController; use App\Http\Controllers\Admin\DocumentController as AdminDocumentController;`.

- [X] T030 [P] [US3] Write `tests/Feature/Admin/DocumentAdminTest.php`: namespace `Tests\Feature\Admin`. Use `RefreshDatabase`. In `setUp()`, seed `RolePermissionSeeder`, `VisaTypeSeeder`, `WorkflowStepTemplateSeeder`. Use `Storage::fake('local')`. Helper: `makeAdmin()` — create user, assign role `admin`. Write tests:
  1. `test_admin_can_view_application_list()` — actingAs admin, get `/admin/applications`, assertOk(), assertSee reference number.
  2. `test_admin_can_view_application_documents_page()` — create application with seeded tasks, actingAs admin, get `admin.applications.documents.index`, assertOk().
  3. `test_admin_can_upload_document_on_behalf()` — actingAs admin, post to `admin.applications.documents.store` with valid file + `application_task_id`, assertRedirect, assert 1 document in DB with `uploaded_by = admin->id`.
  4. `test_admin_upload_appears_on_client_documents_tab()` — admin uploads, then actingAs client get client dashboard documents tab, assertSee the document's `original_filename`.
  5. `test_reviewer_cannot_upload_admin_document()` — actingAs reviewer, post to admin document store route, assertForbidden().
  6. `test_client_cannot_access_admin_application_list()` — actingAs client, get `/admin/applications`, assertForbidden().

**Checkpoint**: Log in as admin → `/admin/applications` → see all applications. Open one → upload a file for step 3. Log in as client → Documents tab → see the document with staff attribution label.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final integration verification and test run.

- [X] T031 [P] Run `php artisan migrate:fresh --seed` and verify the complete setup: (1) `documents` table exists with all 10 columns. (2) `workflow_step_templates` rows for positions 3 and 4 have `is_document_required = 1` for all 3 visa types. (3) Verify permissions via tinker: `echo \Spatie\Permission\Models\Role::findByName('client')->permissions->pluck('name')->implode(', ')` → must include `documents.upload`. `echo \Spatie\Permission\Models\Role::findByName('reviewer')->permissions->pluck('name')->implode(', ')` → must include `documents.download`. `echo \Spatie\Permission\Models\Role::findByName('admin')->permissions->pluck('name')->implode(', ')` → must include all three document permissions.

- [X] T032 [P] Run `php artisan test --filter=Document` to execute all feature tests written in T021, T024, and T030. All tests must pass. If any fail, fix the underlying issue — do not skip or comment out tests.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately (T001–T003)
- **Foundational (Phase 2)**: Depends on Phase 1 — BLOCKS all user stories (T004–T017)
- **US1 Client Upload (Phase 3)**: Depends on Phase 2; specifically T004, T009, T010, T016 must be complete
- **US2 Reviewer Download (Phase 4)**: Depends on Phase 2 + Phase 3 (upload must work for reviewer to see anything)
- **US3 Admin Upload (Phase 5)**: Depends on Phase 2; parallel to US2 since it uses the same `DocumentService`
- **Polish (Phase 6)**: Depends on all previous phases

### User Story Dependencies

- **US1 (P1)**: Requires Foundational (T004–T017) complete. The `Document` model, `DocumentService`, `DocumentPolicy`, and Form Request must exist first.
- **US2 (P2)**: Requires US1 complete (reviewer sees documents uploaded by clients; nothing to show without uploads).
- **US3 (P3)**: Requires Foundational complete. Can be developed in parallel with US2 since `Admin\DocumentController` uses the same `DocumentService::upload()`.

### ⚠️ Note on `tasks.template` eager-load

The `$task->template->is_document_required` check requires the `template` relationship to be eager-loaded. This is added in T019 (Client DashboardController) and T022 (Reviewer ApplicationController). Any view that checks `$task->template?->is_document_required` will trigger lazy-loading if the relationship was not pre-loaded — this is acceptable but produces an N+1 query. The eager-load additions in T019 and T022 prevent this.

### Parallel Opportunities Within Phase 2

```
# These can all run in parallel immediately after T004 (Document model):
T005  Add documents() to VisaApplication
T006  Add documents() to ApplicationTask
T007  DocumentPolicy
T010  UploadDocumentRequest
T011  AdminUploadDocumentRequest
T012  resources/lang/en/documents.php
T013  resources/lang/ar/documents.php
T014  lang/en/documents.php (proxy)
T015  lang/ar/documents.php (proxy)

# Sequential chain:
T004 → T009 (DocumentService, needs Document model) → T008 (AppServiceProvider, needs DocumentPolicy from T007)
T009 → T016 (DocumentController, needs DocumentService)
T016 → T017 (routes, needs controller class to exist)
```

### Parallel Opportunities Within Phase 5

```
T025  Admin\ApplicationController (application list)
T026  Admin\DocumentController (depends on DocumentService from T009 ✅)
T027  Admin views (can write in parallel with T025/T026)
T030  Admin test (can write structure while T025-T027 are in progress)
```

---

## Implementation Strategy

### MVP First (US1 + US4 Only)

1. Complete Phase 1: Setup (T001–T003)
2. Complete Phase 2: Foundational (T004–T017)
3. Complete Phase 3: US1 Client Upload + Access Control (T018–T021)
4. **STOP and VALIDATE**: Client can upload documents. Cross-client 403 confirmed. Status transition works. Tests pass.
5. Then add US2: Reviewer document view (T022–T024)
6. Then add US3: Admin upload (T025–T030)

### Notes

- Every new PHP class must have the correct namespace matching its file path under `app/`
- `Storage::fake('local')` in test setUp prevents real file writes; `UploadedFile::fake()->create(name, sizeInKB, mimeType)` creates in-memory files
- `$file->storeAs(directory, filename, disk)` returns the relative path (e.g., `documents/42/uuid.pdf`) — store this in `documents.path`
- `Storage::disk($document->disk)->download($document->path, $document->original_filename)` works for local disk; for S3 use `temporaryUrl()`
- Run `php artisan config:clear && php artisan cache:clear` if route or permission changes don't take effect
- The `authorize('upload', Document::class)` call (with the class string, not an instance) maps to `DocumentPolicy::upload(User $user)` — this is the "gate without model" pattern in Laravel
- The `authorize('adminUpload', Document::class)` similarly maps to `DocumentPolicy::adminUpload(User $user)`
- The `authorize('download', $document)` call (with an instance) maps to `DocumentPolicy::download(User $user, Document $document)`
- `VisaApplication::whereKey($id)->where('status', 'in_progress')->update(...)` is a single atomic SQL UPDATE — no race condition risk on concurrent uploads
