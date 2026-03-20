# Tasks: Phase 1 — Foundation & Architecture

**Input**: Design documents from `/specs/001-phase1-foundation-auth/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅
**Branch**: `001-phase1-foundation-auth`
**Stack**: PHP 8.2+ / Laravel 11 / Breeze (Blade) / spatie/laravel-permission v6+ / MySQL (dev) / SQLite in-memory (tests)

> **Context for implementors**: This is a Visa Application Client Portal. Phase 1 builds the complete authentication foundation: registration, login/logout, password reset, role-based access control (Admin / Client / Reviewer), and admin user management. All controllers delegate to Service classes. No business logic in controllers or Blade views. No inline `$request->validate()`. No `$guarded = []`. Use `$fillable` on all models. No `if ($user->role === 'admin')` — always use `$user->can('permission')` or Policies.

## Format: `[ID] [P?] [Story?] Description`

- **[P]**: Can run in parallel (different files, no dependencies on incomplete tasks)
- **[Story]**: Which user story this task belongs to ([US1]–[US4])

---

## Phase 1: Setup

**Purpose**: Install dependencies, scaffold Breeze, and configure the testing environment.

- [X] T001 Install Laravel Breeze: run `composer require laravel/breeze --dev` then `php artisan breeze:install blade --no-interaction` in the project root. This scaffolds auth views into `resources/views/auth/`, controllers into `app/Http/Controllers/Auth/`, and routes into `routes/auth.php`. Commit the generated scaffold as a baseline before any overrides.
- [X] T002 Install spatie/laravel-permission: run `composer require spatie/laravel-permission`. No additional config file edits needed yet — publishing migrations is done in Phase 2.
- [X] T003 [P] Configure SQLite in-memory test database: open `phpunit.xml` (project root) and add or ensure the following `<env>` entries exist inside `<php>`: `<env name="DB_CONNECTION" value="sqlite"/>` and `<env name="DB_DATABASE" value=":memory:"/>`. This ensures feature tests run against an isolated in-memory database without touching MySQL.
- [X] T004 [P] Document required environment variables: open `.env.example` and add the following lines at the end of the file (after existing entries): `ADMIN_EMAIL=admin@example.com` and `ADMIN_PASSWORD=Change_Me_123`. Copy these to your local `.env` with real values. These are read by `AdminUserSeeder` — do NOT hardcode the values in PHP.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Database schema, User model, seeders, AuditLogService, and language files — everything that all user stories depend on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [X] T005 Modify the users migration: open `database/migrations/0001_01_01_000000_create_users_table.php`. Inside the `Schema::create('users', ...)` closure, after the `$table->timestamp('email_verified_at')->nullable();` line, add: `$table->boolean('is_active')->default(true);` and `$table->timestamp('last_login_at')->nullable();`. Run `php artisan migrate:fresh` to apply. These columns are required by `EnsureAccountIsActive` middleware (Phase 4) and `AuthService::login()`.
- [X] T006 Publish and run spatie/laravel-permission migrations: run `php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag="permission-migrations"` then `php artisan migrate`. This creates the `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, and `role_has_permissions` tables used by `RolePermissionSeeder` in T010.
- [X] T007 Create the `audit_logs` migration: create a new file `database/migrations/[timestamp]_create_audit_logs_table.php` (use `php artisan make:migration create_audit_logs_table`). Inside the `up()` method, define the table with these columns: `$table->id()`, `$table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()`, `$table->string('event')`, `$table->string('ip_address', 45)->nullable()`, `$table->text('user_agent')->nullable()`, `$table->json('metadata')->nullable()`, `$table->timestamp('created_at')->useCurrent()`. Add `$table->engine = 'InnoDB';` and do NOT add `updated_at` — this table is append-only. In `down()`, call `Schema::dropIfExists('audit_logs')`. Run `php artisan migrate`.
- [X] T008 Update `app/Models/User.php`: (a) Add `use Spatie\Permission\Traits\HasRoles;` and add `HasRoles` to the `use` list in the class body. (b) Add `'is_active'` and `'last_login_at'` to the `$fillable` array. (c) Add a `$casts` array (or merge into existing): `'is_active' => 'boolean'`, `'last_login_at' => 'datetime'`, `'email_verified_at' => 'datetime'`. Do NOT add `HasRoles` if it is already present. The model must not have `$guarded`.
- [X] T009 [P] Create `app/Services/Auth/AuditLogService.php`: create the file with class `AuditLogService` in namespace `App\Services\Auth`. Add a single public method: `public function log(string $event, ?\App\Models\User $user = null, array $metadata = []): void`. Inside, call `\DB::table('audit_logs')->insert(['user_id' => $user?->id, 'event' => $event, 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(), 'metadata' => !empty($metadata) ? json_encode($metadata) : null, 'created_at' => now()])`. Valid event strings (used throughout): `login_success`, `login_failed`, `logout`, `account_deactivated`, `role_changed`, `user_created`.
- [X] T010 [P] Create `database/seeders/RolePermissionSeeder.php`: (a) Create roles: `admin`, `client`, `reviewer` using `\Spatie\Permission\Models\Role::firstOrCreate(['name' => '...'])`. (b) Create permissions using `\Spatie\Permission\Models\Permission::firstOrCreate(['name' => '...'])` for each of: `users.view`, `users.create`, `users.edit`, `users.deactivate`, `roles.assign`, `dashboard.admin`, `dashboard.client`, `dashboard.reviewer`. (c) Assign permissions: `admin` role gets all 8 permissions; `client` role gets `dashboard.client` only; `reviewer` role gets `dashboard.reviewer` only. Use `$role->givePermissionTo([...])`. Wrap all DB operations in `\Spatie\Permission\PermissionRegistrar::class` cache reset at the end: `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions()`.
- [X] T011 Create `database/seeders/AdminUserSeeder.php`: create or update the admin user from environment variables. Use `\App\Models\User::firstOrCreate(['email' => env('ADMIN_EMAIL')], ['name' => 'Super Admin', 'password' => \Illuminate\Support\Facades\Hash::make(env('ADMIN_PASSWORD')), 'is_active' => true])`. Then call `$admin->syncRoles(['admin'])`. This depends on `RolePermissionSeeder` already having run (enforced by `DatabaseSeeder` call order in T012).
- [X] T012 Update `database/seeders/DatabaseSeeder.php`: ensure the `run()` method calls seeders in this exact order: (1) `$this->call(RolePermissionSeeder::class)`, (2) `$this->call(AdminUserSeeder::class)`. Remove any default user factory calls Breeze may have added. Run `php artisan db:seed` to verify it works without errors.
- [X] T013 [P] Create `resources/lang/en/auth.php`: return an array with at minimum these keys: `'login' => 'Log In'`, `'logout' => 'Log Out'`, `'register' => 'Create Account'`, `'email' => 'Email Address'`, `'password' => 'Password'`, `'confirm_password' => 'Confirm Password'`, `'name' => 'Full Name'`, `'remember_me' => 'Remember Me'`, `'forgot_password' => 'Forgot your password?'`, `'reset_password' => 'Reset Password'`, `'send_reset_link' => 'Email Password Reset Link'`, `'account_deactivated' => 'Your account has been deactivated. Please contact support.'`, `'unauthorized' => 'You do not have permission to access this page.'`, `'admin_dashboard' => 'Admin Dashboard'`, `'client_dashboard' => 'Client Dashboard'`, `'reviewer_dashboard' => 'Reviewer Dashboard'`, `'failed' => 'These credentials do not match our records.'`, `'throttle' => 'Too many login attempts. Please try again in :seconds seconds.'`. The file must be a valid PHP file returning an array.
- [X] T014 [P] Create `resources/lang/ar/auth.php`: create the Arabic translation file for all keys defined in T013. Keys must be identical; only the values differ. Provide reasonable Arabic translations (e.g., `'login' => 'تسجيل الدخول'`, `'logout' => 'تسجيل الخروج'`, `'register' => 'إنشاء حساب'`, `'email' => 'البريد الإلكتروني'`, `'password' => 'كلمة المرور'`, `'confirm_password' => 'تأكيد كلمة المرور'`, `'name' => 'الاسم الكامل'`, `'remember_me' => 'تذكرني'`, `'forgot_password' => 'نسيت كلمة المرور؟'`, `'reset_password' => 'إعادة تعيين كلمة المرور'`, `'send_reset_link' => 'إرسال رابط إعادة التعيين'`, `'account_deactivated' => 'تم تعطيل حسابك. يرجى التواصل مع الدعم.'`, `'unauthorized' => 'ليس لديك صلاحية الوصول إلى هذه الصفحة.'`, `'admin_dashboard' => 'لوحة تحكم المشرف'`, `'client_dashboard' => 'لوحة تحكم العميل'`, `'reviewer_dashboard' => 'لوحة تحكم المراجع'`, `'failed' => 'بيانات الاعتماد هذه غير متطابقة مع سجلاتنا.'`, `'throttle' => 'محاولات تسجيل دخول كثيرة جداً. يرجى المحاولة مجدداً بعد :seconds ثانية.'`).

**Checkpoint**: Run `php artisan migrate:fresh --seed`. Verify: users table has `is_active` and `last_login_at` columns; `audit_logs` table exists; roles `admin/client/reviewer` exist; admin user exists and has the `admin` role. Check with `php artisan tinker`: `App\Models\User::first()->getRoleNames()` should return `['admin']`.

---

## Phase 3: User Story 1 — New User Registration (Priority: P1) 🎯 MVP

**Goal**: A visitor can register a new account. On success they are logged in and redirected to their role-appropriate dashboard. Registration defaults the new user's role to `client`.

**Independent Test**: Visit `/register`, submit valid name/email/password, verify the user is created in the DB with role `client`, is logged in, and lands on `/client/dashboard`.

### Implementation for User Story 1

- [X] T015 [US1] Create `app/Http/Requests/Auth/RegisterRequest.php`: namespace `App\Http\Requests\Auth`. It must extend `Illuminate\Foundation\Http\FormRequest`. `authorize()` returns `true`. `rules()` returns: `'name' => ['required', 'string', 'max:255']`, `'email' => ['required', 'string', 'email', 'max:255', 'unique:\App\Models\User,email']`, `'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()]`. This replaces Breeze's inline validation in the controller. Do NOT extend Breeze's `RegisteredUserController` request — create this as a standalone Form Request class.
- [X] T016 [US1] Create `app/Services/Auth/AuthService.php` with `register()` method: namespace `App\Services\Auth`. Constructor-inject `AuditLogService $auditLog`. Add method `public function register(\App\Http\Requests\Auth\RegisterRequest $request): \App\Models\User`. Inside: (1) Create user: `User::create(['name' => $request->name, 'email' => $request->email, 'password' => Hash::make($request->password), 'is_active' => true])`. (2) Assign role: `$user->assignRole('client')`. (3) Log event: `$this->auditLog->log('user_created', $user)`. (4) Return `$user`. Use `Illuminate\Support\Facades\Hash` for hashing.
- [X] T017 [US1] Override `app/Http/Controllers/Auth/RegisteredUserController.php` (Breeze generated this file — replace its `store()` body completely): namespace `App\Http\Controllers\Auth`. Constructor-inject `\App\Services\Auth\AuthService $authService`. In `store(\App\Http\Requests\Auth\RegisterRequest $request)`: (1) Call `$user = $this->authService->register($request)`. (2) Log in: `Auth::login($user)`. (3) Fire event: `event(new \Illuminate\Auth\Events\Registered($user))`. (4) Redirect: `return redirect()->route('dashboard')`. Replace the type-hint on `$request` from Breeze's default to `RegisterRequest`. The `create()` method (returning the view) stays unchanged.
- [X] T018 [US1] Create `app/Http/Controllers/DashboardController.php`: namespace `App\Http\Controllers`. One method: `public function index(\Illuminate\Http\Request $request): \Illuminate\Http\RedirectResponse`. Inside, use `$user = $request->user()` then check: if `$user->hasRole('admin')` return `redirect()->route('admin.dashboard')`; elseif `$user->hasRole('reviewer')` return `redirect()->route('reviewer.dashboard')`; else return `redirect()->route('client.dashboard')`. This is the only place role-name string checks are used — all other places use `$user->can('permission')`.
- [X] T019 [P] [US1] Create `resources/views/dashboard/admin.blade.php`: extend `layouts.app` (Breeze's app layout). Set `@section('header')` to `__('auth.admin_dashboard')`. In `@section('content')` add a `<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">` containing `<p class="text-gray-500">{{ __('auth.admin_dashboard') }}</p>`. The view should be minimal — it is a placeholder for Phase 2 features.
- [X] T020 [P] [US1] Create `resources/views/dashboard/client.blade.php`: same structure as T019 but using `__('auth.client_dashboard')`.
- [X] T021 [P] [US1] Create `resources/views/dashboard/reviewer.blade.php`: same structure as T019 but using `__('auth.reviewer_dashboard')`.
- [X] T022 [US1] Add dashboard routes to `routes/web.php`: add a `Route::middleware('auth')->group(...)` block (merge with any existing one) containing: `Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard')`. Then add a second group `Route::middleware(['auth', 'verified'])->group(...)` containing: `Route::get('/admin/dashboard', fn() => view('dashboard.admin'))->middleware('can:dashboard.admin')->name('admin.dashboard')`, `Route::get('/client/dashboard', fn() => view('dashboard.client'))->middleware('can:dashboard.client')->name('client.dashboard')`, `Route::get('/reviewer/dashboard', fn() => view('dashboard.reviewer'))->middleware('can:dashboard.reviewer')->name('reviewer.dashboard')`. **Important**: `can:dashboard.admin` uses Spatie's Gate integration automatically once the package is installed.
- [X] T023 [US1] Update `resources/views/auth/register.blade.php`: replace every hardcoded English string with `__('auth.*')` calls using the keys defined in T013. For example: form title becomes `{{ __('auth.register') }}`, email label becomes `{{ __('auth.email') }}`, password label becomes `{{ __('auth.password') }}`, confirm password label becomes `{{ __('auth.confirm_password') }}`, submit button becomes `{{ __('auth.register') }}`. Do NOT change any HTML structure or Tailwind classes — only replace string literals.

**Checkpoint**: Run `php artisan serve`, visit `/register`, submit valid details. Verify: user in DB has role `client`; session is authenticated; browser redirects to `/client/dashboard`; `audit_logs` has a `user_created` row.

---

## Phase 4: User Story 2 — User Login (Priority: P1)

**Goal**: A registered user logs in with email/password, is redirected to their role dashboard. Login is denied for wrong credentials (generic error) and for deactivated accounts. Logout terminates the session. All events are audit-logged.

**Independent Test**: Log in as the seeded admin → lands on `/admin/dashboard`. Attempt login with wrong password → generic error with no mention of whether email exists. Deactivate a test user, attempt login → "account deactivated" message. Log out → redirected to `/login`.

### Implementation for User Story 2

- [X] T024 [US2] Extend `app/Services/Auth/AuthService.php` with `login()` and `logout()` methods (add to the class from T016): `public function login(\App\Http\Requests\Auth\LoginRequest $request): bool` — call `$request->authenticate()` (Breeze's `LoginRequest` handles the `Auth::attempt()` and throttle logic); if it throws `\Illuminate\Validation\ValidationException`, call `$this->auditLog->log('login_failed', null, ['email' => $request->email])` then re-throw; otherwise call `$this->auditLog->log('login_success', Auth::user())` and call `\App\Models\User::where('id', Auth::id())->update(['last_login_at' => now()])`, then return `true`. `public function logout(\Illuminate\Http\Request $request): void` — log `$this->auditLog->log('logout', Auth::user())` BEFORE logging out; then call `Auth::logout()`; then `$request->session()->invalidate()`; then `$request->session()->regenerateToken()`.
- [X] T025 [US2] Override `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (Breeze generated this — replace the body): Constructor-inject `\App\Services\Auth\AuthService $authService`. In `store(\App\Http\Requests\Auth\LoginRequest $request)`: call `$this->authService->login($request)` (let ValidationException bubble up to display errors); regenerate session: `$request->session()->regenerate()`; redirect via `redirect()->intended(route('dashboard'))`. In `destroy(\Illuminate\Http\Request $request)`: call `$this->authService->logout($request)`; return `redirect()->route('login')`. The `create()` method (returning login view) stays unchanged.
- [X] T026 [US2] Create `app/Http/Middleware/EnsureAccountIsActive.php`: namespace `App\Http\Middleware`. Implement `handle(\Illuminate\Http\Request $request, \Closure $next)`. Inside: if `Auth::check()` and `!Auth::user()->is_active`, then: call `app(\App\Services\Auth\AuditLogService::class)->log('login_failed', Auth::user(), ['reason' => 'deactivated_account_access'])`, call `Auth::logout()`, call `$request->session()->invalidate()`, call `$request->session()->regenerateToken()`, return `redirect()->route('login')->withErrors(['email' => __('auth.account_deactivated')])`. Otherwise return `$next($request)`.
- [X] T027 [US2] Register the `EnsureAccountIsActive` middleware in `bootstrap/app.php`: inside the `->withMiddleware(function (\Illuminate\Foundation\Configuration\Middleware $middleware) {...})` callback, add `$middleware->alias(['active' => \App\Http\Middleware\EnsureAccountIsActive::class])`. Then append `\App\Http\Middleware\EnsureAccountIsActive::class` to the `web` middleware group using `$middleware->appendToGroup('web', \App\Http\Middleware\EnsureAccountIsActive::class)`. This ensures it runs on every web request after authentication is resolved.
- [X] T028 [US2] Register Spatie's permission middleware aliases in `bootstrap/app.php` (same file as T027, same `withMiddleware` callback): add `$middleware->alias(['role' => \Spatie\Permission\Middleware\RoleMiddleware::class, 'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class, 'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class])`. This allows `->middleware('can:dashboard.admin')` (via Gate) and also `->middleware('permission:users.view')` on admin routes.
- [X] T029 [US2] Update `resources/views/auth/login.blade.php`: replace every hardcoded string with `__('auth.*')` calls (same approach as T023). Specifically: page/button title → `__('auth.login')`, email label → `__('auth.email')`, password label → `__('auth.password')`, remember me label → `__('auth.remember_me')`, forgot password link → `__('auth.forgot_password')`, submit button → `__('auth.login')`. Do NOT alter HTML structure.
- [X] T030 [P] [US2] Create password reset controller `app/Http/Controllers/Auth/PasswordResetLinkController.php` if Breeze did not generate it, or verify the Breeze-generated version exists. If it exists, no changes are needed — Breeze's default `PasswordResetLinkController` sends the reset link email correctly. Only add localization: update `resources/views/auth/forgot-password.blade.php` to use `__('auth.forgot_password')` for the heading and `__('auth.send_reset_link')` for the button. Similarly update `resources/views/auth/reset-password.blade.php` to use `__('auth.reset_password')` for the heading and button.

**Checkpoint**: Run all Phase 4 scenarios manually: (1) Login with admin credentials → `/admin/dashboard`. (2) Login with wrong password → generic error. (3) Deactivate a user via `php artisan tinker` (`User::find(2)->update(['is_active' => false])`), attempt login → deactivated message. (4) Login, then `GET /logout` or post to logout route → redirected to `/login`. Check `audit_logs` table for all events.

---

## Phase 5: User Story 3 — Role-Based Access Control (Priority: P2)

**Goal**: Each role can only access their permitted pages. Unauthorized access returns a 403. Role assignment by admin immediately affects the user's next session.

**Independent Test**: Log in as `client`, attempt `GET /admin/dashboard` → 403 with "unauthorized" message. Log in as `admin`, assign `reviewer` role to a user via Tinker, that user logs in → reaches `/reviewer/dashboard`.

### Implementation for User Story 3

- [X] T031 [US3] Create `app/Policies/UserPolicy.php`: namespace `App\Policies`. Add `use App\Models\User`. Implement these public methods: `viewAny(User $user): bool` → `$user->can('users.view')`; `view(User $user, User $model): bool` → `$user->can('users.view')`; `create(User $user): bool` → `$user->can('users.create')`; `update(User $user, User $model): bool` → `$user->can('users.edit')`; `deactivate(User $user, User $model): bool` → `$user->can('users.deactivate') && $user->id !== $model->id`; `assignRole(User $user, User $model): bool` → `$user->can('roles.assign')`. The `deactivate` method prevents self-deactivation per FR-014.
- [X] T032 [US3] Register `UserPolicy` in `app/Providers/AppServiceProvider.php`: inside `boot()`, add `\Illuminate\Support\Facades\Gate::policy(\App\Models\User::class, \App\Policies\UserPolicy::class)`. Add the necessary `use` imports at the top. In Laravel 11 there is no separate `AuthServiceProvider` — use `AppServiceProvider`.
- [X] T033 [US3] Create `resources/views/errors/403.blade.php`: extend `layouts.app`. In the content section render: `<div class="max-w-7xl mx-auto py-12 sm:px-6 lg:px-8 text-center"><h1 class="text-4xl font-bold text-gray-800">403</h1><p class="mt-4 text-gray-600">{{ __('auth.unauthorized') }}</p><a href="{{ url('/') }}" class="mt-6 inline-block text-blue-600 hover:underline">{{ __('auth.login') }}</a></div>`. This view is automatically rendered by Laravel when a `403` HTTP exception is thrown (including from `$this->authorize()` failing).

**Checkpoint**: Logged in as `client`, try `GET /admin/dashboard` — should return the 403 view with the unauthorized message. Logged in as `admin`, can access `/admin/dashboard`. Run `php artisan route:list` to verify all dashboard routes have correct middleware.

---

## Phase 6: User Story 4 — Admin User Management (Priority: P3)

**Goal**: Admin can list, create, edit, deactivate users and assign roles. Self-deactivation is blocked. Deactivated users cannot log in. All management events are audit-logged.

**Independent Test**: Log in as admin, navigate to `/admin/users`, create a new user with role `reviewer`, log in as that new user → lands on `/reviewer/dashboard`. Admin deactivates a client user → that client can no longer log in.

### Implementation for User Story 4

- [X] T034 [P] [US4] Create `app/Http/Requests/Admin/StoreUserRequest.php`: namespace `App\Http\Requests\Admin`. Extends `FormRequest`. `authorize()` returns `true` (Policy check is in the controller). `rules()` returns: `'name' => ['required', 'string', 'max:255']`, `'email' => ['required', 'string', 'email', 'max:255', 'unique:\App\Models\User,email']`, `'password' => ['required', 'string', \Illuminate\Validation\Rules\Password::min(8)->mixedCase()->numbers()]`, `'role' => ['required', 'string', 'in:admin,client,reviewer']`.
- [X] T035 [P] [US4] Create `app/Http/Requests/Admin/UpdateUserRequest.php`: namespace `App\Http\Requests\Admin`. `rules()` returns: `'name' => ['required', 'string', 'max:255']`, `'email' => ['required', 'string', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($this->route('user')->id)]`. The `ignore()` call excludes the current user's own email from the uniqueness check so an admin can save unchanged email.
- [X] T036 [P] [US4] Create `app/Http/Requests/Admin/AssignRoleRequest.php`: namespace `App\Http\Requests\Admin`. `rules()` returns: `'role' => ['required', 'string', 'in:admin,client,reviewer']`.
- [X] T037 [US4] Create `app/Services/Admin/UserService.php`: namespace `App\Services\Admin`. Constructor-inject `\App\Services\Auth\AuditLogService $auditLog`. Implement these public methods:
  - `index(): \Illuminate\Database\Eloquent\Collection` — return `\App\Models\User::with('roles')->latest()->get()`.
  - `store(\App\Http\Requests\Admin\StoreUserRequest $request): \App\Models\User` — create user: `$user = \App\Models\User::create(['name' => $request->name, 'email' => $request->email, 'password' => \Illuminate\Support\Facades\Hash::make($request->password), 'is_active' => true])`. Assign role: `$user->assignRole($request->role)`. Log: `$this->auditLog->log('user_created', $user, ['role' => $request->role])`. Return `$user`.
  - `update(\App\Models\User $user, \App\Http\Requests\Admin\UpdateUserRequest $request): \App\Models\User` — call `$user->update($request->only('name', 'email'))` and return the user.
  - `deactivate(\App\Models\User $currentUser, \App\Models\User $targetUser): void` — if `$currentUser->id === $targetUser->id` throw `\Illuminate\Auth\Access\AuthorizationException`. Call `$targetUser->update(['is_active' => false])`. Log: `$this->auditLog->log('account_deactivated', $targetUser, ['deactivated_by' => $currentUser->id])`.
  - `assignRole(\App\Models\User $currentUser, \App\Models\User $targetUser, string $role): void` — capture old role: `$oldRole = $targetUser->getRoleNames()->first() ?? 'none'`. Call `$targetUser->syncRoles([$role])`. Log: `$this->auditLog->log('role_changed', $targetUser, ['old_role' => $oldRole, 'new_role' => $role, 'changed_by' => $currentUser->id])`.
- [X] T038 [US4] Create `app/Http/Controllers/Admin/UserController.php`: namespace `App\Http\Controllers\Admin`. Extends `\App\Http\Controllers\Controller`. Constructor-inject `\App\Services\Admin\UserService $userService`. Implement: `index()` → `$this->authorize('viewAny', User::class)` → `$users = $this->userService->index()` → return `view('admin.users.index', compact('users'))`. `create()` → `$this->authorize('create', User::class)` → return `view('admin.users.create', ['roles' => ['admin', 'client', 'reviewer']])`. `store(StoreUserRequest $request)` → `$this->authorize('create', User::class)` → `$this->userService->store($request)` → `redirect()->route('admin.users.index')->with('success', 'User created.')`. `edit(User $user)` → `$this->authorize('update', $user)` → return `view('admin.users.edit', compact('user'))` with `['roles' => ['admin', 'client', 'reviewer'], 'currentRole' => $user->getRoleNames()->first()]`. `update(UpdateUserRequest $request, User $user)` → `$this->authorize('update', $user)` → `$this->userService->update($user, $request)` → redirect back with success. `deactivate(Request $request, User $user)` → `$this->authorize('deactivate', $user)` → `$this->userService->deactivate($request->user(), $user)` → redirect back with success. `assignRole(AssignRoleRequest $request, User $user)` → `$this->authorize('assignRole', $user)` → `$this->userService->assignRole($request->user(), $user, $request->role)` → redirect back with success.
- [X] T039 [P] [US4] Create `resources/views/admin/users/index.blade.php`: extends `layouts.app`. Display a table of all users showing: name, email, role (first role name), status (`is_active` → "Active" / "Deactivated"), and action links: "Edit" → `route('admin.users.edit', $user)`, "Deactivate" (only if `$user->is_active && Auth::user()->can('deactivate', $user)`) as a `DELETE` form via `@method('DELETE')` + `@csrf`. Add a "Create User" button linking to `route('admin.users.create')`. Use `@forelse ($users as $user)` to list rows; display "No users found" if empty.
- [X] T040 [P] [US4] Create `resources/views/admin/users/create.blade.php`: extends `layouts.app`. Display a form posting to `route('admin.users.store')` with `@csrf`. Include fields: name (text), email (email), password (password), role (select from `$roles` array). Display `@error` messages for each field. Submit button "Create User".
- [X] T041 [P] [US4] Create `resources/views/admin/users/edit.blade.php`: extends `layouts.app`. Display: (1) A form for `PATCH` to `route('admin.users.update', $user)` with `@csrf @method('PATCH')` containing name and email fields. (2) A separate form for `PATCH` to `route('admin.users.assign-role', $user)` with a role select preselected to `$currentRole` and submit button "Assign Role". Use `@can('assignRole', $user)` to conditionally show the role form. Display all `@error` messages.
- [X] T042 [US4] Add admin user management routes to `routes/web.php` inside a new group: `Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(function () { Route::resource('users', \App\Http\Controllers\Admin\UserController::class)->except(['show', 'destroy']); Route::delete('users/{user}/deactivate', [\App\Http\Controllers\Admin\UserController::class, 'deactivate'])->name('users.deactivate'); Route::patch('users/{user}/role', [\App\Http\Controllers\Admin\UserController::class, 'assignRole'])->name('users.assign-role'); })`. Note: authorization is handled in the controller via Policies — no middleware-level permission checks needed here beyond `auth`.

**Checkpoint**: Log in as admin. Navigate to `/admin/users` — see the user list. Create a new user with role `reviewer`. Log in as that user → `/reviewer/dashboard`. Log back in as admin, deactivate that user. Attempt login as deactivated user → denied. Attempt self-deactivation → 403.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: RTL support, final validation, and feature tests.

- [X] T043 Add RTL support: open `resources/views/layouts/app.blade.php` (Breeze-generated app layout). In the `<html>` tag, add: `lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"`. This satisfies the constitution's multi-language requirement for Arabic RTL rendering. No CSS changes required for Phase 1.
- [X] T044 [P] Run full migration and seed verification: run `php artisan migrate:fresh --seed`. Confirm output shows all migrations applied and seeders completed without errors. Spot-check with `php artisan tinker`: `User::first()->getRoleNames()` → `['admin']`; `\Spatie\Permission\Models\Permission::count()` → `8`; `\App\Models\User::first()->can('dashboard.admin')` → `true`.
- [X] T045 [P] Write feature tests in `tests/Feature/Auth/RegistrationTest.php`: test that (1) registration page loads, (2) valid registration creates user, assigns `client` role, logs in, redirects to `/dashboard`, (3) duplicate email fails with validation error, (4) weak password fails with validation error, (5) empty form fails with required field errors. Use `RefreshDatabase` trait. Use `$this->post('/register', [...])` for form submissions.
- [X] T046 [P] Write feature tests in `tests/Feature/Auth/LoginTest.php`: test that (1) login page loads, (2) valid credentials authenticate and redirect to role dashboard, (3) wrong password returns generic error (check error is present but NOT "email" or "password" enumeration), (4) login with an `admin` role user redirects to `/admin/dashboard`. Use `RefreshDatabase` and `RolePermissionSeeder`.
- [X] T047 [P] Write feature tests in `tests/Feature/Auth/LogoutTest.php`: test that (1) an authenticated user can POST to `/logout` and is redirected to `/login`, (2) session is invalidated after logout, (3) accessing `/dashboard` after logout redirects to `/login`.
- [X] T048 [P] Write feature tests in `tests/Feature/Auth/PasswordResetTest.php`: test that (1) forgot-password page loads, (2) submitting a valid email sends a password reset notification (use `Notification::fake()`), (3) submitting an unknown email does NOT reveal whether it exists (generic response).
- [X] T049 [P] Write feature tests in `tests/Feature/Auth/RoleAccessControlTest.php`: test that (1) `admin` can access `/admin/dashboard`, (2) `client` gets 403 on `/admin/dashboard`, (3) `reviewer` gets 403 on `/admin/dashboard`, (4) `client` can access `/client/dashboard`, (5) `reviewer` can access `/reviewer/dashboard`, (6) unauthenticated user is redirected to login on any dashboard. Seed roles via `RolePermissionSeeder` in `setUp()`.
- [X] T050 [P] Write feature tests in `tests/Feature/Auth/DeactivatedAccountTest.php`: test that (1) a deactivated user cannot log in (receives `auth.account_deactivated` error), (2) a deactivated user who is already logged in is kicked out on next request, (3) an active user CAN log in. Set `is_active = false` directly on the model.
- [X] T051 [P] Write feature tests in `tests/Feature/Auth/Admin/UserManagementTest.php`: test that (1) admin can view `/admin/users`, (2) admin can create a user (verify in DB), (3) admin can deactivate a user (verify `is_active = false`), (4) admin CANNOT deactivate themselves (verify 403 or redirect with error), (5) admin can assign a role and the user's role changes, (6) non-admin (client) cannot access `/admin/users` (403).

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately
- **Foundational (Phase 2)**: Depends on Phase 1 completion — BLOCKS all user stories
- **US1 Registration (Phase 3)**: Depends on Phase 2 completion
- **US2 Login (Phase 4)**: Depends on Phase 3 completion (requires `AuthService` from T016, dashboard routes from T022)
- **US3 RBAC (Phase 5)**: Depends on Phase 2 completion; can partially run after Phase 3
- **US4 Admin Management (Phase 6)**: Depends on Phase 2, Phase 3, and Phase 5 (requires `UserPolicy` from T031)
- **Polish (Phase 7)**: Depends on all previous phases

### User Story Dependencies

- **US1 (P1)**: Can start after Phase 2 — no dependency on other user stories
- **US2 (P1)**: Depends on US1 (`AuthService` class and dashboard routes must exist)
- **US3 (P2)**: Depends on Phase 2 only — `UserPolicy` and middleware are independent
- **US4 (P3)**: Depends on Phase 2, US1 (for `AuthService`), and US3 (for `UserPolicy`)

### Within Each Phase

- Tasks marked `[P]` within the same phase can run in parallel (they touch different files)
- Tasks without `[P]` depend on previous tasks in that phase completing first
- Within US4: Form Requests (T034–T036) → Service (T037) → Controller (T038) → Views (T039–T041) → Routes (T042)

---

## Parallel Example: Phase 2 (Foundational)

```text
# These can all run in parallel (different files):
T009  Create AuditLogService
T010  Create RolePermissionSeeder
T013  Create resources/lang/en/auth.php
T014  Create resources/lang/ar/auth.php

# These must run sequentially:
T005 → T006 → T007 → T008 → (T009 || T010 || T013 || T014) → T011 → T012
```

## Parallel Example: User Story 4 (Admin Management)

```text
# Form Requests can run in parallel:
T034  StoreUserRequest
T035  UpdateUserRequest
T036  AssignRoleRequest

# Views can run in parallel after T038:
T039  admin/users/index.blade.php
T040  admin/users/create.blade.php
T041  admin/users/edit.blade.php
```

---

## Implementation Strategy

### MVP First (User Stories 1 + 2 Only)

1. Complete Phase 1: Setup (T001–T004)
2. Complete Phase 2: Foundational (T005–T014)
3. Complete Phase 3: US1 Registration (T015–T023)
4. Complete Phase 4: US2 Login (T024–T030)
5. **STOP and VALIDATE**: Registration, login, logout, password reset, deactivation guard all work end-to-end
6. Deploy/demo

### Incremental Delivery

1. Setup + Foundational → Foundation complete
2. US1 Registration → Test independently
3. US2 Login + middleware → Test login/logout/deactivation
4. US3 RBAC → Test permission gates
5. US4 Admin Management → Test full admin workflow
6. Polish → Tests + RTL + final validation

### Notes

- Every task that creates a new PHP class must have the correct namespace matching its file path
- `Auth::` facade requires `use Illuminate\Support\Facades\Auth` at the top of the class
- `Hash::` facade requires `use Illuminate\Support\Facades\Hash`
- The `layouts.app` Blade layout is scaffolded by Breeze — do not recreate it
- Spatie's `can:` middleware delegate uses Laravel's Gate, which reads Spatie permissions automatically — no extra wiring needed
- Run `php artisan config:clear && php artisan cache:clear` after any changes to `bootstrap/app.php`
- All Blade strings MUST use `__()` — no hardcoded English strings in view files
