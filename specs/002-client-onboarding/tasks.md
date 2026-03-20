# Tasks: Phase 2 — Client Onboarding

**Input**: Design documents from `/specs/002-client-onboarding/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/routes.md ✅, quickstart.md ✅
**Branch**: `002-client-onboarding`
**Stack**: PHP 8.2+ / Laravel 11 / Breeze (Blade) / Alpine.js v3 / spatie/laravel-permission v6+ / MySQL (dev) / SQLite in-memory (tests)

> **Context for implementors**: This is Phase 2 of a Visa Application Client Portal. Phase 1 already built auth (login/register/roles) and is **complete**. Phase 2 adds: (1) a 3-step Alpine.js onboarding wizard form that creates a client account + visa application atomically; (2) an 8-tab client dashboard; (3) EN/AR session-based locale switching. All controllers delegate to Service classes. No business logic in controllers or Blade. No inline `$request->validate()`. No `$guarded = []`. Use `$fillable` on all models. No `if ($user->role === 'client')` — use `$user->can()` and Policies. All Blade strings use `__('client.*')`.
>
> **⚠️ Password field note**: The spec's 13 form fields don't explicitly list `password`, but the client must be able to log back in after their session expires. Add `password` and `password_confirmation` to Step 1 of the wizard alongside email. This is a practical implementation necessity.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story ([US1]–[US3])

---

## Phase 1: Setup (Database Foundation)

**Purpose**: Create the two new tables and seed initial visa types before any application code is written.

- [X] T001 Create the `visa_types` migration: run `php artisan make:migration create_visa_types_table`. In `up()`, define: `$table->id()`, `$table->string('name', 100)->unique()`, `$table->text('description')->nullable()`, `$table->boolean('is_active')->default(true)`, `$table->timestamps()`. In `down()`, call `Schema::dropIfExists('visa_types')`. Run `php artisan migrate` to apply.
- [X] T002 Create the `visa_applications` migration: run `php artisan make:migration create_visa_applications_table`. In `up()`, define columns in this exact order: `$table->id()`, `$table->foreignId('user_id')->constrained()->cascadeOnDelete()`, `$table->foreignId('visa_type_id')->constrained()->restrictOnDelete()`, `$table->string('reference_number', 15)->unique()->nullable()` (nullable because it is set by model event after insert), `$table->string('status', 30)->default('pending_review')`, `$table->string('full_name')`, `$table->string('email')`, `$table->string('phone', 30)`, `$table->string('nationality', 100)`, `$table->string('country_of_residence', 100)`, `$table->string('job_title', 150)`, `$table->string('employment_type', 50)`, `$table->decimal('monthly_income', 10, 2)`, `$table->unsignedTinyInteger('adults_count')->default(1)`, `$table->unsignedTinyInteger('children_count')->default(0)`, `$table->date('application_start_date')`, `$table->text('notes')->nullable()`, `$table->boolean('agreed_to_terms')->default(false)`, `$table->timestamps()`. In `down()`, call `Schema::dropIfExists('visa_applications')`. **Important**: this migration must run AFTER the `visa_types` migration. Run `php artisan migrate`.
- [X] T003 [P] Create `database/seeders/VisaTypeSeeder.php`: namespace `Database\Seeders`. In `run()`, use `\App\Models\VisaType::firstOrCreate(['name' => 'Tourist Visa'], ['description' => 'Short-term tourist visit visa.', 'is_active' => true])` and repeat for `'Work Permit'` (description: `'Employment authorization visa.'`) and `'Family Reunification'` (description: `'Visa for joining family members abroad.'`). All three must have `is_active = true`.
- [X] T004 Update `database/seeders/DatabaseSeeder.php`: add `$this->call(VisaTypeSeeder::class)` as the third call in `run()`, after `AdminUserSeeder::class`. Verify by running `php artisan db:seed --class=VisaTypeSeeder` — the `visa_types` table should contain exactly 3 rows.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Models, form request, service, policy, locale middleware, and language files — everything all user stories depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T005 [P] Create `app/Models/VisaType.php`: namespace `App\Models`. Class extends `Illuminate\Database\Eloquent\Model`. Add `use HasFactory`. Set `protected $fillable = ['name', 'description', 'is_active']`. Set `protected $casts = ['is_active' => 'boolean']`. Add relationship: `public function visaApplications(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(VisaApplication::class); }`. Add local scope: `public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder { return $query->where('is_active', true); }`.
- [X] T006 Create `app/Models/VisaApplication.php`: namespace `App\Models`. Class extends `Illuminate\Database\Eloquent\Model`. Add `use HasFactory`. Set `protected $fillable = ['user_id', 'visa_type_id', 'status', 'full_name', 'email', 'phone', 'nationality', 'country_of_residence', 'job_title', 'employment_type', 'monthly_income', 'adults_count', 'children_count', 'application_start_date', 'notes', 'agreed_to_terms']`. Note: `reference_number` is NOT in fillable — it is set by the model event. Set `protected $casts = ['monthly_income' => 'decimal:2', 'adults_count' => 'integer', 'children_count' => 'integer', 'agreed_to_terms' => 'boolean', 'application_start_date' => 'date']`. Add relationships: `public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(User::class); }` and `public function visaType(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(VisaType::class); }`. Add model boot for reference number: `protected static function booted(): void { static::created(function (VisaApplication $application) { $application->updateQuietly(['reference_number' => sprintf('APP-%05d', $application->id)]); }); }`. `updateQuietly` prevents re-firing model events.
- [X] T007 Create `app/Http/Requests/Client/OnboardingRequest.php`: namespace `App\Http\Requests\Client`. Extends `Illuminate\Foundation\Http\FormRequest`. `authorize()` returns `true` (public form, no auth required). `rules()` returns: `'full_name' => ['required', 'string', 'max:255']`, `'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email']`, `'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()]`, `'phone' => ['required', 'string', 'max:30']`, `'nationality' => ['required', 'string', 'max:100']`, `'country_of_residence' => ['required', 'string', 'max:100']`, `'visa_type_id' => ['required', 'integer', 'exists:visa_types,id']`, `'adults_count' => ['required', 'integer', 'min:1', 'max:20']`, `'children_count' => ['required', 'integer', 'min:0', 'max:20']`, `'application_start_date' => ['required', 'date', 'after_or_equal:today']`, `'job_title' => ['required', 'string', 'max:150']`, `'employment_type' => ['required', 'string', 'in:employed,self_employed,unemployed,student']`, `'monthly_income' => ['required', 'numeric', 'min:0']`, `'notes' => ['nullable', 'string', 'max:2000']`, `'agreed_to_terms' => ['required', 'accepted']`.
- [X] T008 Create `app/Services/Client/OnboardingService.php`: namespace `App\Services\Client`. Constructor-inject `\App\Services\Auth\AuditLogService $auditLog` (this service already exists from Phase 1 at `app/Services/Auth/AuditLogService.php`). Add method `public function handle(\App\Http\Requests\Client\OnboardingRequest $request): \App\Models\VisaApplication`. Inside, wrap everything in `return \Illuminate\Support\Facades\DB::transaction(function () use ($request) { ... })`. Inside the closure: (1) Create user: `$user = \App\Models\User::create(['name' => $request->full_name, 'email' => $request->email, 'password' => \Illuminate\Support\Facades\Hash::make($request->password), 'is_active' => true])`. (2) Assign role: `$user->assignRole('client')`. (3) Create application: `$application = \App\Models\VisaApplication::create(['user_id' => $user->id, 'visa_type_id' => $request->visa_type_id, 'full_name' => $request->full_name, 'email' => $request->email, 'phone' => $request->phone, 'nationality' => $request->nationality, 'country_of_residence' => $request->country_of_residence, 'job_title' => $request->job_title, 'employment_type' => $request->employment_type, 'monthly_income' => $request->monthly_income, 'adults_count' => $request->adults_count, 'children_count' => $request->children_count, 'application_start_date' => $request->application_start_date, 'notes' => $request->notes, 'agreed_to_terms' => true, 'status' => 'pending_review'])`. (4) Log: `$this->auditLog->log('application_created', $user, ['reference' => $application->reference_number])`. (5) Log in the user: `\Illuminate\Support\Facades\Auth::login($user)`. (6) Return `$application`. Add all required `use` statements at the top of the file.
- [X] T009 [P] Create `app/Policies/VisaApplicationPolicy.php`: namespace `App\Policies`. Add `use App\Models\User; use App\Models\VisaApplication`. Implement `public function view(User $user, VisaApplication $application): bool { return $user->id === $application->user_id; }`. Then register this policy in `app/Providers/AppServiceProvider.php` inside `boot()`: add `\Illuminate\Support\Facades\Gate::policy(\App\Models\VisaApplication::class, \App\Policies\VisaApplicationPolicy::class)`. Add the required `use` imports. This follows the same pattern as `UserPolicy` already registered in this file from Phase 1.
- [X] T010 [P] Create `app/Http/Middleware/SetLocale.php`: namespace `App\Http\Middleware`. Implement `handle(\Illuminate\Http\Request $request, \Closure $next): \Symfony\Component\HttpFoundation\Response`. Inside: `$locale = session('locale', 'en')`. If `$locale` is not in `['en', 'ar']`, set `$locale = 'en'`. Call `app()->setLocale($locale)`. Return `$next($request)`.
- [X] T011 Register `SetLocale` in `bootstrap/app.php`: inside the `->withMiddleware(function (\Illuminate\Foundation\Configuration\Middleware $middleware) { ... })` callback, add `$middleware->prependToGroup('web', \App\Http\Middleware\SetLocale::class)`. Use `prependToGroup` (not `appendToGroup`) so locale is set BEFORE any view rendering or auth checks occur in the web group. This file already has other middleware aliases registered from Phase 1 — add this alongside them without removing anything.
- [X] T012 [P] Create `app/Http/Controllers/LanguageController.php`: namespace `App\Http\Controllers`. One method: `public function switch(string $locale): \Illuminate\Http\RedirectResponse`. Inside: `$locale = in_array($locale, ['en', 'ar']) ? $locale : 'en'`. Call `session(['locale' => $locale])`. Return `redirect()->back()`.
- [X] T013 [P] Create `resources/lang/en/client.php`: return an array with ALL of these keys (copy exactly): `'wizard_step1_title' => 'Personal Information'`, `'wizard_step2_title' => 'Visa Details'`, `'wizard_step3_title' => 'Employment & Financial'`, `'step_indicator' => 'Step :current of :total'`, `'full_name' => 'Full Name'`, `'email' => 'Email Address'`, `'password' => 'Password'`, `'confirm_password' => 'Confirm Password'`, `'phone' => 'Phone Number'`, `'nationality' => 'Nationality'`, `'country_of_residence' => 'Country of Residence'`, `'visa_type' => 'Visa Type'`, `'select_visa_type' => '— Select a visa type —'`, `'adults_count' => 'Number of Adults'`, `'children_count' => 'Number of Children'`, `'application_start_date' => 'Preferred Start Date'`, `'job_title' => 'Job Title'`, `'employment_type' => 'Employment Type'`, `'employment_employed' => 'Employed'`, `'employment_self_employed' => 'Self-Employed'`, `'employment_unemployed' => 'Unemployed'`, `'employment_student' => 'Student'`, `'monthly_income' => 'Monthly Income'`, `'notes' => 'Additional Notes (optional)'`, `'agreed_to_terms' => 'I agree to the Privacy Policy and Terms of Service'`, `'next' => 'Next'`, `'back' => 'Back'`, `'submit_application' => 'Submit Application'`, `'dashboard_title' => 'My Application Dashboard'`, `'application_reference' => 'Application Reference'`, `'application_status' => 'Application Status'`, `'status_pending_review' => 'Pending Review'`, `'applied_for' => 'Applied for'`, `'tab_overview' => 'Overview'`, `'tab_documents' => 'Documents'`, `'tab_tasks' => 'Tasks'`, `'tab_payments' => 'Payments'`, `'tab_timeline' => 'Timeline'`, `'tab_messages' => 'Messages'`, `'tab_profile' => 'Profile'`, `'tab_support' => 'Support'`, `'empty_documents' => 'No documents uploaded yet. Document requests will appear here once your application is under review.'`, `'empty_tasks' => 'No tasks assigned yet. Your tasks will appear here once your application is in progress.'`, `'empty_payments' => 'No payments due yet. Payment details will appear here when your application advances.'`, `'empty_timeline' => 'No timeline events yet. Your application progress milestones will appear here.'`, `'empty_messages' => 'No messages yet. Communications from your case officer will appear here.'`, `'empty_support' => 'Need help? Contact our support team at support@portal.com.'`, `'language_en' => 'EN'`, `'language_ar' => 'AR'`.
- [X] T014 [P] Create `resources/lang/ar/client.php`: return the same keys as T013 with Arabic translations. Use: `'wizard_step1_title' => 'المعلومات الشخصية'`, `'wizard_step2_title' => 'تفاصيل التأشيرة'`, `'wizard_step3_title' => 'التوظيف والمعلومات المالية'`, `'step_indicator' => 'الخطوة :current من :total'`, `'full_name' => 'الاسم الكامل'`, `'email' => 'البريد الإلكتروني'`, `'password' => 'كلمة المرور'`, `'confirm_password' => 'تأكيد كلمة المرور'`, `'phone' => 'رقم الهاتف'`, `'nationality' => 'الجنسية'`, `'country_of_residence' => 'بلد الإقامة'`, `'visa_type' => 'نوع التأشيرة'`, `'select_visa_type' => '— اختر نوع التأشيرة —'`, `'adults_count' => 'عدد البالغين'`, `'children_count' => 'عدد الأطفال'`, `'application_start_date' => 'تاريخ البدء المفضل'`, `'job_title' => 'المسمى الوظيفي'`, `'employment_type' => 'نوع التوظيف'`, `'employment_employed' => 'موظف'`, `'employment_self_employed' => 'يعمل لحسابه الخاص'`, `'employment_unemployed' => 'غير موظف'`, `'employment_student' => 'طالب'`, `'monthly_income' => 'الدخل الشهري'`, `'notes' => 'ملاحظات إضافية (اختياري)'`, `'agreed_to_terms' => 'أوافق على سياسة الخصوصية وشروط الخدمة'`, `'next' => 'التالي'`, `'back' => 'السابق'`, `'submit_application' => 'تقديم الطلب'`, `'dashboard_title' => 'لوحة تحكم طلبي'`, `'application_reference' => 'رقم مرجع الطلب'`, `'application_status' => 'حالة الطلب'`, `'status_pending_review' => 'قيد المراجعة'`, `'applied_for' => 'طلب للحصول على'`, `'tab_overview' => 'نظرة عامة'`, `'tab_documents' => 'المستندات'`, `'tab_tasks' => 'المهام'`, `'tab_payments' => 'المدفوعات'`, `'tab_timeline' => 'الجدول الزمني'`, `'tab_messages' => 'الرسائل'`, `'tab_profile' => 'الملف الشخصي'`, `'tab_support' => 'الدعم'`, `'empty_documents' => 'لم يتم رفع أي مستندات بعد. ستظهر طلبات المستندات هنا عند مراجعة طلبك.'`, `'empty_tasks' => 'لم يتم تعيين مهام بعد. ستظهر مهامك هنا عند معالجة طلبك.'`, `'empty_payments' => 'لا توجد مدفوعات مستحقة بعد. ستظهر تفاصيل الدفع هنا عند تقدم طلبك.'`, `'empty_timeline' => 'لا توجد أحداث زمنية بعد. ستظهر مراحل تقدم طلبك هنا.'`, `'empty_messages' => 'لا توجد رسائل بعد. ستظهر هنا الاتصالات من مسؤول الحالة الخاص بك.'`, `'empty_support' => 'هل تحتاج مساعدة؟ تواصل مع فريق الدعم على support@portal.com.'`, `'language_en' => 'EN'`, `'language_ar' => 'AR'`.

**Checkpoint**: Run `php artisan migrate:fresh --seed`. Confirm: `visa_types` has 3 rows; `visa_applications` table exists; `VisaApplication` model can be instantiated; `OnboardingRequest` has 15 validation rules; `SetLocale` is registered in web group.

---

## Phase 3: User Story 1 — Client Submits Application Form (Priority: P1) 🎯 MVP

**Goal**: A guest visits `/apply`, completes the 3-step Alpine.js wizard, submits, and is logged in and redirected to `/client/dashboard`.

**Independent Test**: Visit `/apply` as a guest, fill all fields, submit → verify `users` table has 1 new client row, `visa_applications` has 1 row with `status = pending_review`, client is authenticated, redirected to `/client/dashboard`.

### Implementation for User Story 1

- [X] T015 [US1] Create `app/Http/Controllers/Client/OnboardingController.php`: namespace `App\Http\Controllers\Client`. Extends `\App\Http\Controllers\Controller`. Constructor-inject `\App\Services\Client\OnboardingService $onboardingService`. Implement two methods: (1) `public function show(): \Illuminate\View\View` — loads active visa types: `$visaTypes = \App\Models\VisaType::active()->orderBy('name')->get(['id', 'name'])` — returns `view('client.onboarding.form', compact('visaTypes'))`. (2) `public function store(\App\Http\Requests\Client\OnboardingRequest $request): \Illuminate\Http\RedirectResponse` — calls `$application = $this->onboardingService->handle($request)` — returns `redirect()->route('client.dashboard')->with('success', 'Your application has been submitted successfully.')`. No try/catch needed — Laravel's exception handler handles transaction failures and redirects back with errors automatically.
- [X] T016 [US1] Create `resources/views/client/onboarding/form.blade.php`: use `<x-guest-layout>` as the outer wrapper. Inside, use Alpine.js for step control: `<div x-data="{ step: 1 }">`. The form posts to `route('onboarding.store')` with `@csrf` and `method="POST"`. Structure the 3 steps as separate `<div x-show="step === N">` blocks. **Step 1** (x-show="step === 1"): heading `{{ __('client.wizard_step1_title') }}`, fields: full_name, email, password, password_confirmation, phone, nationality, country_of_residence. Each field uses `<x-input-label>`, `<x-text-input>`, `<x-input-error>` Breeze components. A "Next" button: `<button type="button" @click="if ($el.closest('form').querySelectorAll('[x-show=\'step===1\'] [required]').length === 0 || $el.closest('[x-show]').checkValidity?.()) step++" ...>{{ __('client.next') }}</button>`. Simpler approach: use `<button type="button" @click="step++" x-show="step < 3">` without client-side validation gating — server-side `OnboardingRequest` enforces all validation on submit. **Step 2** (x-show="step === 2"): heading `{{ __('client.wizard_step2_title') }}`, fields: visa_type_id (select element with `@foreach($visaTypes as $vt) <option value="{{ $vt->id }}" {{ old('visa_type_id') == $vt->id ? 'selected' : '' }}>{{ $vt->name }}</option> @endforeach`), adults_count (number input, min=1), children_count (number input, min=0), application_start_date (date input). Back and Next buttons. **Step 3** (x-show="step === 3"): heading `{{ __('client.wizard_step3_title') }}`, fields: job_title, employment_type (select with employed/self_employed/unemployed/student options using `__('client.employment_*')` labels), monthly_income (number), notes (textarea, not required). Consent checkbox: `<input type="checkbox" name="agreed_to_terms" value="1" id="agreed_to_terms" required {{ old('agreed_to_terms') ? 'checked' : '' }}>` with label `{{ __('client.agreed_to_terms') }}`. Submit button: `<x-primary-button>{{ __('client.submit_application') }}</x-primary-button>`. Display `@if(session('errors'))` old input errors at the top of the form so if the server rejects the submission, the client sees the errors (wizard resets to step 1). Include `@error('agreed_to_terms')` beneath the checkbox. Use `old()` to repopulate all fields on validation failure.
- [X] T017 [US1] Update `routes/web.php`: (1) Add language route near the top (before guest group): `Route::post('/language/{locale}', [\App\Http\Controllers\LanguageController::class, 'switch'])->name('language.switch')`. (2) Add a guest middleware group: `Route::middleware('guest')->group(function () { Route::get('/apply', [\App\Http\Controllers\Client\OnboardingController::class, 'show'])->name('onboarding.show'); Route::post('/apply', [\App\Http\Controllers\Client\OnboardingController::class, 'store'])->name('onboarding.store'); })`. (3) **Replace** the existing stub line `Route::get('/client/dashboard', fn () => view('dashboard.client'))->middleware('can:dashboard.client')->name('client.dashboard')` with `Route::get('/client/dashboard/{tab?}', [\App\Http\Controllers\Client\DashboardController::class, 'show'])->name('client.dashboard')` inside the existing `Route::middleware(['auth', 'verified'])->group(...)` block. The `dashboard.client` permission gate is now enforced by `VisaApplicationPolicy` in the controller, not the route.
- [X] T018 [P] [US1] Write `tests/Feature/Client/OnboardingTest.php`: namespace `Tests\Feature\Client`. Use `RefreshDatabase` trait. In `setUp()`, call `$this->seed(\Database\Seeders\RolePermissionSeeder::class)` and `$this->seed(\Database\Seeders\VisaTypeSeeder::class)`. Define a helper method `validPayload(): array` that returns all required fields with valid values (full_name, email, password, password_confirmation, phone, nationality, country_of_residence, visa_type_id (use `\App\Models\VisaType::first()->id`), adults_count=1, children_count=0, application_start_date=`now()->addDays(30)->toDateString()`, job_title, employment_type='employed', monthly_income=5000, agreed_to_terms=1). Write tests: (1) `test_onboarding_form_loads()` — `$this->get('/apply')->assertOk()`. (2) `test_valid_submission_creates_client_and_application()` — post valid payload, assert user exists with role 'client', assert `visa_applications` has 1 row with `status = 'pending_review'` and `agreed_to_terms = 1`, assert reference_number matches `/^APP-\d{5}$/`, assert authenticated, assert redirect to `client.dashboard`. (3) `test_duplicate_email_rejected()` — create a user with the same email first, post payload, assert `$response->assertSessionHasErrors('email')`, assert `visa_applications` table is empty. (4) `test_missing_required_field_rejected()` — post without `full_name`, assert session has errors for `full_name`. (5) `test_consent_unchecked_rejected()` — post without `agreed_to_terms`, assert session has errors for `agreed_to_terms`. (6) `test_authenticated_client_redirected_from_form()` — `actingAs($existingClient)->get('/apply')->assertRedirect()`. (7) `test_audit_log_created_on_submission()` — post valid payload, assert `audit_logs` table has 1 row with `event = 'application_created'`.

**Checkpoint**: Run `php artisan serve`, visit `/apply`, complete the 3 steps, submit. Verify redirect to `/client/dashboard`, reference number `APP-00001` visible, `audit_logs` has `application_created` row.

---

## Phase 4: User Story 2 — Client Dashboard Access (Priority: P2)

**Goal**: A logged-in client with an application record can view their 8-tab dashboard. Each tab shows either content or a clear empty-state message.

**Independent Test**: Log in as an onboarded client, visit `/client/dashboard` — see full name, reference number `APP-XXXXX`, status `Pending Review`, and 8 navigable tabs each with placeholder content.

### Implementation for User Story 2

- [X] T019 [US2] Create `app/Http/Controllers/Client/DashboardController.php`: namespace `App\Http\Controllers\Client`. Extends `\App\Http\Controllers\Controller`. Implement `public function show(string $tab = 'overview'): \Illuminate\View\View|\Illuminate\Http\RedirectResponse`. Inside: (1) Load the authenticated client's application: `$application = \App\Models\VisaApplication::with('visaType')->where('user_id', auth()->id())->first()`. (2) If `$application` is null, return `redirect()->route('onboarding.show')`. (3) Authorize: `$this->authorize('view', $application)` (uses `VisaApplicationPolicy::view`). (4) Validate tab: `$validTabs = ['overview', 'documents', 'tasks', 'payments', 'timeline', 'messages', 'profile', 'support']`. If `!in_array($tab, $validTabs)`, set `$tab = 'overview'`. (5) Return `view('client.dashboard.index', compact('application', 'tab'))`.
- [X] T020 [US2] Create `resources/views/client/dashboard/index.blade.php`: use `<x-app-layout>` as wrapper. In `<x-slot name="header">`, display the client's name: `<h2>{{ $application->full_name }}</h2>` and reference: `<span>{{ __('client.application_reference') }}: {{ $application->reference_number }}</span>`. Below the header slot, render tab navigation: an `<nav>` with 8 links, one per tab. Each link points to `route('client.dashboard', ['tab' => 'overview'])` etc. Apply an "active" CSS class when `$tab === 'overview'` etc. Use `__('client.tab_overview')` etc. for link labels. Below the nav, include the active tab partial: `@include('client.dashboard.tabs.' . $tab, ['application' => $application])`. This pattern means adding a new tab in future phases only requires creating a new partial file — no changes to this file.
- [X] T021 [P] [US2] Create `resources/views/client/dashboard/tabs/overview.blade.php`: Display: application status using `__('client.status_' . $application->status)` (renders "Pending Review"), visa type name using `$application->visaType->name`, and preferred start date `$application->application_start_date->format('d M Y')`. Show a simple card layout with all visible application details: full name, email, phone, nationality, country of residence, adults/children count. Use `__('client.*')` keys for all labels.
- [X] T022 [P] [US2] Create `resources/views/client/dashboard/tabs/documents.blade.php`: Display `<p class="text-gray-500">{{ __('client.empty_documents') }}</p>` inside a centered container. This is a placeholder for Phase 4.
- [X] T023 [P] [US2] Create `resources/views/client/dashboard/tabs/tasks.blade.php`: Display `<p class="text-gray-500">{{ __('client.empty_tasks') }}</p>`. Placeholder for Phase 3.
- [X] T024 [P] [US2] Create `resources/views/client/dashboard/tabs/payments.blade.php`: Display `<p class="text-gray-500">{{ __('client.empty_payments') }}</p>`. Placeholder for Phase 5.
- [X] T025 [P] [US2] Create `resources/views/client/dashboard/tabs/timeline.blade.php`: Display `<p class="text-gray-500">{{ __('client.empty_timeline') }}</p>`. Placeholder for Phase 3.
- [X] T026 [P] [US2] Create `resources/views/client/dashboard/tabs/messages.blade.php`: Display `<p class="text-gray-500">{{ __('client.empty_messages') }}</p>`. Placeholder for Phase 9.
- [X] T027 [P] [US2] Create `resources/views/client/dashboard/tabs/profile.blade.php`: Display the client's account details in a read-only card: full name, email, phone, nationality, country of residence, job title, employment type, monthly income. Use `__('client.*')` keys for labels. Note: this tab shows read-only data in Phase 2; editing will be added in a later phase.
- [X] T028 [P] [US2] Create `resources/views/client/dashboard/tabs/support.blade.php`: Display `<p class="text-gray-500">{{ __('client.empty_support') }}</p>`. Placeholder content for Phase 2.
- [X] T029 [P] [US2] Write `tests/Feature/Client/DashboardTest.php`: namespace `Tests\Feature\Client`. Use `RefreshDatabase`. In `setUp()`, seed `RolePermissionSeeder` and `VisaTypeSeeder`. Create a helper `makeOnboardedClient(): \App\Models\User` that creates a user with role 'client' and a matching `VisaApplication` record (set all required fields, `status = 'pending_review'`, `reference_number = 'APP-00001'`). Write tests: (1) `test_authenticated_client_sees_dashboard()` — `actingAs($client)->get('/client/dashboard')->assertOk()->assertSee('APP-00001')`. (2) `test_all_8_tabs_accessible()` — assert each of the 8 tab URLs returns 200. (3) `test_invalid_tab_falls_back_to_overview()` — `get('/client/dashboard/nonexistent')->assertOk()`. (4) `test_unauthenticated_visitor_redirected_to_login()` — `get('/client/dashboard')->assertRedirect(route('login'))`. (5) `test_client_without_application_redirected_to_onboarding()` — create a user with client role but NO application record, `actingAs` them, `get('/client/dashboard')`, assert redirect to `onboarding.show`. (6) `test_client_cannot_view_other_clients_application()` — create two onboarded clients, assert client A cannot access client B's dashboard (403 or redirect).

**Checkpoint**: Log in as the client created in Scenario 1. Visit `/client/dashboard`, `/client/dashboard/documents`, `/client/dashboard/payments`. All 8 tabs must load without errors. Each empty tab shows its placeholder message.

---

## Phase 5: User Story 3 — Bilingual Form and Dashboard (Priority: P3)

**Goal**: The EN/AR language toggle is visible on the onboarding form page. Switching to Arabic renders all labels, placeholders, and tab names in Arabic with RTL layout.

**Independent Test**: Visit `/apply`, click the "AR" toggle in the header → all labels switch to Arabic and layout becomes RTL. Click "EN" → switches back to English LTR.

### Implementation for User Story 3

- [X] T030 [US3] Update `resources/views/layouts/guest.blade.php` (this is the layout used by `<x-guest-layout>` — find the actual layout file Breeze uses; it may be `resources/views/components/guest-layout.blade.php` or `resources/views/layouts/guest.blade.php`; check which file exists): (1) On the `<html>` opening tag, add `lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"` — this mirrors the pattern already in `resources/views/layouts/app.blade.php` from Phase 1. (2) Add an EN/AR language toggle in the top-right corner of the page, before the main content area. Render it as two form buttons: `<form method="POST" action="{{ route('language.switch', 'en') }}" class="inline">@csrf<button type="submit" class="{{ app()->getLocale() === 'en' ? 'font-bold underline' : '' }}">{{ __('client.language_en') }}</button></form>` and repeat for `ar`. Show both toggles side-by-side. Place this toggle in the page header area so it is visible on every guest page including `/apply`.

**Checkpoint**: Visit `/apply`, click "AR" toggle, verify Arabic labels and RTL layout. Visit `/client/dashboard` after login, verify the `dir` attribute is `rtl` when locale is `ar` (app.blade.php from Phase 1 already handles this for authenticated pages).

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final integration verification and DB consistency check.

- [X] T031 [P] Run `php artisan migrate:fresh --seed` and verify complete setup: confirm `visa_types` has 3 rows, `roles` has 3 rows, `permissions` has 8 rows (from Phase 1). Run `php artisan tinker --execute="echo \App\Models\VisaType::active()->count();"` → should return `3`.
- [X] T032 [P] Run `php artisan test --filter=Client` to execute all feature tests written in T018 and T029. All tests must pass. If any fail, fix the underlying issue (do not skip or comment out tests).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately after Phase 1 auth is complete
- **Foundational (Phase 2)**: Depends on Phase 1 (T001–T004) completion — BLOCKS all user stories
- **US1 Registration (Phase 3)**: Depends on Phase 2; specifically T006, T007, T008 must be complete
- **US2 Dashboard (Phase 4)**: Depends on Phase 2; specifically T006, T009 must be complete. Can start after Phase 3 is done, or in parallel by a second developer
- **US3 Bilingual (Phase 5)**: Depends on T013, T014 (lang files) and the views created in Phase 3 and Phase 4. T030 can only be done after the guest layout is identified
- **Polish (Phase 6)**: Depends on all previous phases

### User Story Dependencies

- **US1 (P1)**: Requires Foundational complete (T005–T014)
- **US2 (P2)**: Requires Foundational complete (T005–T014); independent of US1 (a developer can work on the dashboard while another builds the form)
- **US3 (P3)**: Requires US1 and US2 views to exist (T016, T020–T028) and lang files (T013, T014)

### Parallel Opportunities Within Phase 2

```
# These can all run in parallel:
T005  VisaType model
T009  VisaApplicationPolicy
T010  SetLocale middleware
T012  LanguageController
T013  resources/lang/en/client.php
T014  resources/lang/ar/client.php

# Sequential chain:
T006 → T007 → T008 → T011
```

### Parallel Opportunities Within Phase 4

```
# After T019 and T020 are done, all 8 tab partials can be created in parallel:
T021  overview.blade.php
T022  documents.blade.php
T023  tasks.blade.php
T024  payments.blade.php
T025  timeline.blade.php
T026  messages.blade.php
T027  profile.blade.php
T028  support.blade.php
```

---

## Implementation Strategy

### MVP First (US1 + US2 Only)

1. Complete Phase 1: Setup (T001–T004)
2. Complete Phase 2: Foundational (T005–T014)
3. Complete Phase 3: US1 Registration form (T015–T018)
4. Complete Phase 4: US2 Dashboard (T019–T029)
5. **STOP and VALIDATE**: Full onboarding flow works end-to-end; all 8 dashboard tabs accessible
6. Then add US3: Bilingual support (T030)

### Notes

- Every new PHP class must have the correct namespace matching its file path under `app/`
- `Auth::` facade requires `use Illuminate\Support\Facades\Auth`
- `DB::` facade requires `use Illuminate\Support\Facades\DB`
- The `<x-app-layout>` and `<x-guest-layout>` Blade components are scaffolded by Breeze — do not recreate them
- Run `php artisan config:clear && php artisan cache:clear` if middleware changes don't take effect
- All `__('client.*')` calls reference `resources/lang/{locale}/client.php` — verify this file is in the correct location for Laravel 11 (see Phase 1 review note about `lang/` vs `resources/lang/`)
- The stub `Route::get('/client/dashboard', fn() => view('dashboard.client'))` from Phase 1 **MUST** be removed in T017 — leaving it will cause a route name conflict
