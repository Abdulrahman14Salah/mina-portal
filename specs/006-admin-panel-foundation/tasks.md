# Tasks: Admin Panel Foundation & Architecture

**Branch**: `006-admin-panel-foundation`
**Input**: Design documents from `/specs/006-admin-panel-foundation/`
**Spec**: spec.md | **Plan**: plan.md | **Data Model**: data-model.md | **Routes**: contracts/routes.md

**Tests**: Included — feature tests covering access control, dashboard, and list UI.
**Target**: Tasks are written to be self-contained and executable by any LLM without additional context.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel with other [P]-marked tasks in the same phase
- **[US#]**: Which user story this task belongs to
- **No story label**: Setup or foundational task

## Path Conventions

All paths are relative to the Laravel project root `/Applications/MAMP/htdocs/portal-sass/`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create the config, lang, and service provider hooks that all subsequent phases depend on.
**No user story code yet — pure infrastructure.**

- [ ] T001 Create `config/admin-navigation.php` as a PHP file returning an array of 7 navigation items. Each item is an associative array with keys: `route` (named route string), `label_key` (lang key string), `icon` (SVG icon name string), `active_pattern` (route pattern for wildcard active state, or `null`). Items in order: `['route' => 'admin.dashboard', 'label_key' => 'admin.nav_dashboard', 'icon' => 'home', 'active_pattern' => null]`, `['route' => 'admin.applications.index', 'label_key' => 'admin.nav_applications', 'icon' => 'folder', 'active_pattern' => 'admin.applications.*']`, `['route' => 'admin.visa-types.index', 'label_key' => 'admin.nav_visa_types', 'icon' => 'identification', 'active_pattern' => 'admin.visa-types.*']`, `['route' => 'admin.clients.index', 'label_key' => 'admin.nav_clients', 'icon' => 'users', 'active_pattern' => 'admin.clients.*']`, `['route' => 'admin.task-builder.index', 'label_key' => 'admin.nav_task_builder', 'icon' => 'cog', 'active_pattern' => 'admin.task-builder.*']`, `['route' => 'admin.reviewers.index', 'label_key' => 'admin.nav_reviewers', 'icon' => 'eye', 'active_pattern' => 'admin.reviewers.*']`, `['route' => 'admin.users.index', 'label_key' => 'admin.nav_users', 'icon' => 'user', 'active_pattern' => 'admin.users.*']`

- [ ] T002 [P] Create `lang/en/admin.php` returning the following array exactly (copy verbatim):
  ```php
  <?php
  return [
      // Navigation
      'nav_dashboard'    => 'Dashboard',
      'nav_applications' => 'Applications',
      'nav_visa_types'   => 'Visa Types',
      'nav_clients'      => 'Clients',
      'nav_task_builder' => 'Task Builder',
      'nav_reviewers'    => 'Reviewers',
      'nav_users'        => 'Users',
      // Dashboard
      'dashboard_title'        => 'Admin Dashboard',
      'active_applications'    => 'Active Applications',
      'pending_review'         => 'Pending Review',
      'total_clients'          => 'Total Clients',
      'recent_applications'    => 'Recent Applications',
      'view_all'               => 'View All',
      'widget_error'           => 'Unable to load',
      'no_recent_applications' => 'No applications yet.',
      // List UI
      'search_placeholder' => 'Search…',
      'search_button'      => 'Search',
      'no_records'         => 'No records found.',
      'confirm_action'     => 'Are you sure you want to perform this action?',
      'action_view'        => 'View',
      'action_edit'        => 'Edit',
      'action_deactivate'  => 'Deactivate',
      // Table columns
      'col_reference'  => 'Reference',
      'col_client'     => 'Client',
      'col_visa_type'  => 'Visa Type',
      'col_status'     => 'Status',
      'col_submitted'  => 'Submitted',
      'col_actions'    => 'Actions',
      // Access
      'access_denied'  => 'You do not have permission to access this area.',
      // Breadcrumb
      'breadcrumb_home' => 'Admin',
  ];
  ```

- [ ] T003 [P] Create `lang/ar/admin.php` as a proxy file that requires the English file as a stub (Arabic content deferred to Phase 9):
  ```php
  <?php
  return require resource_path('lang/en/admin.php');
  ```

- [ ] T004 Create `app/View/Composers/AdminBreadcrumbComposer.php` in namespace `App\View\Composers`. The class has a single method `compose(Illuminate\View\View $view): void`. Inside compose: if the view does not already have a `breadcrumbs` variable bound (`$view->offsetExists('breadcrumbs')` is false), call `$view->with('breadcrumbs', [['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard']])`. This ensures every admin view has at least a root breadcrumb even if the controller does not set one.

- [ ] T005 In `app/Providers/AppServiceProvider.php`, inside the `boot()` method, add a view composer registration AFTER the existing policy registrations. Add: `use Illuminate\Support\Facades\View;` to imports if not present. Then inside `boot()` add: `View::composer('admin.*', \App\View\Composers\AdminBreadcrumbComposer::class);`. This registers the breadcrumb default for ALL views matching `admin.*` (i.e., all views inside `resources/views/admin/`).

**Checkpoint**: Config, lang files, and view composer hook exist. Run `php artisan config:clear && php artisan view:clear` to verify no syntax errors.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Register all routes and create stub controllers and the admin layout shell. Nothing can be tested without routes existing.

**⚠️ CRITICAL**: All user story phases depend on this phase being complete first.

- [ ] T006 Create `app/Http/Controllers/Admin/DashboardController.php` in namespace `App\Http\Controllers\Admin`. Class `DashboardController extends Controller`. Add one method: `public function index(): \Illuminate\View\View`. Inside: set `$breadcrumbs = [['label' => __('admin.breadcrumb_home'), 'route' => null]];` (current page = no link). Return `view('admin.dashboard', compact('breadcrumbs'));`. The view will be created in Phase 5; for now this is a stub that returns the view name.

- [ ] T007 [P] Create `app/Http/Controllers/Admin/VisaTypeController.php` in namespace `App\Http\Controllers\Admin`. Class `VisaTypeController extends Controller`. Add one method: `public function index(): \Illuminate\View\View`. Return `view('admin.placeholder', ['section' => __('admin.nav_visa_types'), 'breadcrumbs' => [['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'], ['label' => __('admin.nav_visa_types'), 'route' => null]]]);`

- [ ] T008 [P] Create `app/Http/Controllers/Admin/ClientController.php` in namespace `App\Http\Controllers\Admin`. Class `ClientController extends Controller`. Add one method: `public function index(): \Illuminate\View\View`. Return `view('admin.placeholder', ['section' => __('admin.nav_clients'), 'breadcrumbs' => [['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'], ['label' => __('admin.nav_clients'), 'route' => null]]]);`

- [ ] T009 [P] Create `app/Http/Controllers/Admin/TaskBuilderController.php` in namespace `App\Http\Controllers\Admin`. Class `TaskBuilderController extends Controller`. Add one method: `public function index(): \Illuminate\View\View`. Return `view('admin.placeholder', ['section' => __('admin.nav_task_builder'), 'breadcrumbs' => [['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'], ['label' => __('admin.nav_task_builder'), 'route' => null]]]);`

- [ ] T010 [P] Create `app/Http/Controllers/Admin/ReviewerController.php` in namespace `App\Http\Controllers\Admin`. Class `ReviewerController extends Controller`. Add one method: `public function index(): \Illuminate\View\View`. Return `view('admin.placeholder', ['section' => __('admin.nav_reviewers'), 'breadcrumbs' => [['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'], ['label' => __('admin.nav_reviewers'), 'route' => null]]]);`

- [ ] T011 In `routes/web.php`, add the following `use` statements at the top with the other admin imports (if not already present):
  ```php
  use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
  use App\Http\Controllers\Admin\VisaTypeController as AdminVisaTypeController;
  use App\Http\Controllers\Admin\ClientController as AdminClientController;
  use App\Http\Controllers\Admin\TaskBuilderController as AdminTaskBuilderController;
  use App\Http\Controllers\Admin\ReviewerController as AdminReviewerController;
  ```
  Then, INSIDE the existing `Route::middleware(['auth', 'verified'])->prefix('admin')->name('admin.')->group(...)` block (before the closing `}`), ADD these new routes:
  ```php
  Route::get('/dashboard', [AdminDashboardController::class, 'index'])->middleware('can:dashboard.admin')->name('dashboard');
  Route::get('/visa-types', [AdminVisaTypeController::class, 'index'])->middleware('can:dashboard.admin')->name('visa-types.index');
  Route::get('/clients', [AdminClientController::class, 'index'])->middleware('can:dashboard.admin')->name('clients.index');
  Route::get('/task-builder', [AdminTaskBuilderController::class, 'index'])->middleware('can:dashboard.admin')->name('task-builder.index');
  Route::get('/reviewers', [AdminReviewerController::class, 'index'])->middleware('can:dashboard.admin')->name('reviewers.index');
  ```
  Also verify that the existing `Route::get('/applications', ...)` route has `->middleware('can:dashboard.admin')` applied. If it does not, add it.

- [ ] T012 [P] Create `resources/views/admin/placeholder.blade.php`. This view uses `<x-admin-layout>` (to be created in T013). Content: display the `$section` variable in an `<h1>` and show "Coming soon." in a paragraph. Pass `$breadcrumbs` to the layout. Full content:
  ```blade
  <x-admin-layout :breadcrumbs="$breadcrumbs">
      <div class="py-12">
          <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <div class="overflow-hidden rounded-lg bg-white shadow-sm p-6">
                  <h1 class="text-2xl font-semibold text-gray-800">{{ $section }}</h1>
                  <p class="mt-2 text-gray-500">Coming soon.</p>
              </div>
          </div>
      </div>
  </x-admin-layout>
  ```

- [ ] T013 Create `resources/views/components/admin-layout.blade.php`. This is the admin-specific layout shell that wraps `<x-app-layout>` and injects the admin nav and breadcrumb. It accepts a `$breadcrumbs` prop. Content:
  ```blade
  @props(['breadcrumbs' => []])

  <x-app-layout>
      <x-slot name="header">
          <x-admin.nav />
      </x-slot>

      <x-admin.breadcrumb :items="$breadcrumbs" />

      {{ $slot }}
  </x-app-layout>
  ```
  **Note**: `<x-admin.nav>` and `<x-admin.breadcrumb>` are created in Phase 3. The views will not fully render until Phase 3 tasks are complete.

**Checkpoint**: Run `php artisan route:list --path=admin` — you should see all 5 new admin routes plus existing ones listed. No 404s on `/admin/dashboard`, `/admin/visa-types`, etc. (they will 500 until Phase 3 views exist, but routes must be resolvable).

---

## Phase 3: User Story 1 — Admin Navigates the Panel (Priority: P1) 🎯 MVP

**Goal**: Admin sees persistent navigation on every page with active-state highlighting and breadcrumbs.

**Independent Test**: Log in as admin, visit `/admin/dashboard` — see nav with 7 links, active state on Dashboard, breadcrumb "Admin". Click each nav link — all return 200 with correct active state.

### Implementation for User Story 1

- [ ] T014 Create `resources/views/components/admin/nav.blade.php`. This component reads `config('admin-navigation')` to render the nav menu. It must NOT have a PHP class file — it is an anonymous Blade component. Full content:
  ```blade
  <nav class="flex space-x-1 overflow-x-auto" aria-label="{{ __('admin.breadcrumb_home') }}">
      @foreach(config('admin-navigation') as $item)
          @php
              $pattern = $item['active_pattern'] ?? $item['route'];
              $isActive = request()->routeIs($pattern);
          @endphp
          <a href="{{ route($item['route']) }}"
             class="whitespace-nowrap px-4 py-2 text-sm font-medium rounded-md transition-colors
                    {{ $isActive
                        ? 'bg-gray-900 text-white'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
              {{ __($item['label_key']) }}
          </a>
      @endforeach
  </nav>
  ```

- [ ] T015 [P] Create `resources/views/components/admin/breadcrumb.blade.php`. Anonymous Blade component (no PHP class). Accepts a `$items` prop (array of `['label' => string, 'route' => string|null]`). Full content:
  ```blade
  @props(['items' => []])

  @if(count($items) > 0)
  <nav aria-label="Breadcrumb" class="px-6 py-3 text-sm text-gray-500">
      <ol class="flex items-center space-x-2">
          @foreach($items as $item)
              <li class="flex items-center">
                  @if(!$loop->first)
                      <span class="mx-2 text-gray-300">/</span>
                  @endif
                  @if($item['route'])
                      <a href="{{ route($item['route']) }}" class="hover:text-gray-700 hover:underline">
                          {{ $item['label'] }}
                      </a>
                  @else
                      <span class="text-gray-800 font-medium">{{ $item['label'] }}</span>
                  @endif
              </li>
          @endforeach
      </ol>
  </nav>
  @endif
  ```

- [ ] T016 Review `resources/views/components/admin-layout.blade.php` (created in T013) — confirm it correctly references `<x-admin.nav />` and `<x-admin.breadcrumb :items="$breadcrumbs" />`. If the component tag names do not match the filenames created in T014 and T015, fix them. Laravel resolves `<x-admin.nav>` from `resources/views/components/admin/nav.blade.php` and `<x-admin.breadcrumb>` from `resources/views/components/admin/breadcrumb.blade.php` — verify this mapping is correct.

- [ ] T017 Update `resources/views/admin/placeholder.blade.php` (created in T012) — no changes needed if T012 was done correctly. Verify the view passes `$breadcrumbs` as a prop to `<x-admin-layout>`. If `$breadcrumbs` is not passed, fix it.

- [ ] T018 Create `resources/views/admin/dashboard.blade.php` as a temporary stub (full version built in Phase 5). For now it just confirms the layout renders:
  ```blade
  <x-admin-layout :breadcrumbs="$breadcrumbs">
      <div class="py-12">
          <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <h1 class="text-2xl font-semibold text-gray-800">{{ __('admin.dashboard_title') }}</h1>
              <p class="mt-2 text-gray-500">Dashboard content coming in Phase 5.</p>
          </div>
      </div>
  </x-admin-layout>
  ```

**Checkpoint**: Visit `/admin/dashboard` as admin — you see the nav bar with 7 links, "Dashboard" is highlighted as active, breadcrumb shows "Admin". Click "Applications" — nav highlights "Applications", breadcrumb shows "Admin / Applications". All 7 nav links return HTTP 200.

---

## Phase 4: User Story 2 — Non-Admins Are Blocked (Priority: P1)

**Goal**: 403 for authenticated non-admins, redirect-to-login for unauthenticated, working redirect-after-login.

**Independent Test**: As a client user, `GET /admin/dashboard` returns HTTP 403 with a friendly message. As a guest, the same URL redirects to `/login` and after login redirects back to `/admin/dashboard`.

### Implementation for User Story 2

- [ ] T019 Create `resources/views/errors/403.blade.php`. Use the existing Breeze layout (`<x-app-layout>`). Show the `__('admin.access_denied')` message and a link back to the client dashboard or home. Content:
  ```blade
  <x-app-layout>
      <x-slot name="header">
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              403 — {{ __('admin.access_denied') }}
          </h2>
      </x-slot>

      <div class="py-12">
          <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <div class="overflow-hidden rounded-lg bg-white shadow-sm p-6">
                  <p class="text-gray-700">{{ __('admin.access_denied') }}</p>
                  <a href="{{ route('dashboard') }}" class="mt-4 inline-block text-blue-600 hover:underline">
                      Return to Dashboard
                  </a>
              </div>
          </div>
      </div>
  </x-app-layout>
  ```

- [ ] T020 Verify that `bootstrap/app.php` (or wherever the Laravel 11 application exception handler is configured) renders the `errors.403` view for `AuthorizationException` / HTTP 403. In Laravel 11, custom error views in `resources/views/errors/` are automatically used. No code changes needed if `resources/views/errors/403.blade.php` exists — just confirm it. If there is a custom exception handler that overrides this, ensure it falls through to the default for `403`.

- [ ] T021 Verify redirect-after-login works for admin URLs. The `auth` middleware in Laravel automatically stores the intended URL in the session when redirecting to `/login`. After login, `Auth::attempt()` should call `redirect()->intended(route('dashboard'))`. Check `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — the `store()` method should use `redirect()->intended(...)`. If it does, no changes needed. If it uses a hardcoded redirect, update it to `return redirect()->intended(route('dashboard'));`.

- [ ] T022 Create `tests/Feature/Admin/AdminDashboardTest.php`. Class `AdminDashboardTest extends TestCase`. Uses `RefreshDatabase`. `setUp()`: seed `RolePermissionSeeder`. Include ALL of the following test methods:

  **Access control tests:**
  - `test_admin_can_access_dashboard()`: Create admin user, `actingAs($admin)->get(route('admin.dashboard'))->assertOk()`
  - `test_client_is_forbidden_from_dashboard()`: Create client user, `actingAs($client)->get(route('admin.dashboard'))->assertForbidden()`
  - `test_reviewer_is_forbidden_from_dashboard()`: Create reviewer user, `actingAs($reviewer)->get(route('admin.dashboard'))->assertForbidden()`
  - `test_unauthenticated_is_redirected_to_login()`: `$this->get(route('admin.dashboard'))->assertRedirect(route('login'))`
  - `test_all_placeholder_sections_return_200_for_admin()`: Create admin, loop over `['admin.visa-types.index', 'admin.clients.index', 'admin.task-builder.index', 'admin.reviewers.index']`, assert each returns 200
  - `test_client_is_forbidden_from_all_admin_routes()`: Create client, loop over same routes, assert each returns 403

  **Note**: Stub tests for Phase 5/6 features (dashboard counts, search) as empty methods marked `/** @todo */` so the test class compiles — they will be filled in later phases.

**Checkpoint**: Run `php artisan test --filter AdminDashboardTest` — all access control tests pass.

---

## Phase 5: User Story 3 — Admin Dashboard Home Page (Priority: P2)

**Goal**: Dashboard shows 3 independent summary count cards and a recent-5-applications list. Each card fails independently (no full-page failure).

**Independent Test**: Load `/admin/dashboard` — see 3 cards with counts (Active Applications, Pending Review, Total Clients) and up to 5 recent applications. Clicking a card goes to the pre-filtered list.

### Implementation for User Story 3

- [ ] T023 Create `app/Services/Admin/AdminDashboardService.php` in namespace `App\Services\Admin`. Inject `App\Services\Auth\AuditLogService $auditLog` in constructor. The service has a private method `loadSafely(callable $fn): array`:
  ```php
  private function loadSafely(callable $fn): array
  {
      try {
          return ['data' => $fn(), 'error' => null];
      } catch (\Throwable $e) {
          \Illuminate\Support\Facades\Log::error('Dashboard widget failed', ['exception' => $e->getMessage()]);
          return ['data' => null, 'error' => __('admin.widget_error')];
      }
  }
  ```
  Then four public methods:
  - `getActiveApplicationsCount(): array` → `loadSafely(fn() => \App\Models\VisaApplication::whereNotIn('status', ['rejected', 'cancelled'])->count())`
  - `getPendingReviewCount(): array` → `loadSafely(fn() => \App\Models\VisaApplication::where('status', 'pending_review')->count())`
  - `getTotalClientsCount(): array` → `loadSafely(fn() => \App\Models\User::role('client')->count())`
  - `getRecentApplications(): array` → `loadSafely(fn() => \App\Models\VisaApplication::with(['user', 'visaType'])->latest()->take(5)->get())`

- [ ] T024 Create `resources/views/components/admin/dashboard-card.blade.php`. Anonymous Blade component. Props: `widget` (array with `data` and `error` keys), `title` (string), `href` (string|null, default null). Content:
  ```blade
  @props(['widget', 'title', 'href' => null])

  <div class="rounded-lg bg-white p-6 shadow-sm">
      <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wider">{{ $title }}</h3>
      @if($widget['error'])
          <p class="mt-2 text-sm text-red-500">{{ $widget['error'] }}</p>
      @else
          <p class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($widget['data']) }}</p>
          @if($href)
              <a href="{{ $href }}" class="mt-2 inline-block text-sm text-blue-600 hover:underline">
                  {{ __('admin.view_all') }} →
              </a>
          @endif
      @endif
  </div>
  ```

- [ ] T025 Update `app/Http/Controllers/Admin/DashboardController.php`. Replace the stub `index()` method. Inject `App\Services\Admin\AdminDashboardService $dashboardService` via constructor. In `index()`:
  ```php
  public function index(): \Illuminate\View\View
  {
      $breadcrumbs = [['label' => __('admin.breadcrumb_home'), 'route' => null]];

      $widgets = [
          'active_count'   => $this->dashboardService->getActiveApplicationsCount(),
          'pending_count'  => $this->dashboardService->getPendingReviewCount(),
          'client_count'   => $this->dashboardService->getTotalClientsCount(),
          'recent'         => $this->dashboardService->getRecentApplications(),
      ];

      return view('admin.dashboard', compact('breadcrumbs', 'widgets'));
  }
  ```

- [ ] T026 Replace the stub `resources/views/admin/dashboard.blade.php` with the full dashboard view. The view receives `$breadcrumbs` and `$widgets`. Use `<x-admin-layout>`, render 3 `<x-admin.dashboard-card>` components, then the recent applications table:
  ```blade
  <x-admin-layout :breadcrumbs="$breadcrumbs">
      <div class="py-12">
          <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

              <h1 class="text-2xl font-semibold text-gray-800">{{ __('admin.dashboard_title') }}</h1>

              {{-- Summary Cards --}}
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                  <x-admin.dashboard-card
                      :widget="$widgets['active_count']"
                      :title="__('admin.active_applications')"
                      :href="route('admin.applications.index')" />

                  <x-admin.dashboard-card
                      :widget="$widgets['pending_count']"
                      :title="__('admin.pending_review')"
                      :href="route('admin.applications.index', ['status' => 'pending_review'])" />

                  <x-admin.dashboard-card
                      :widget="$widgets['client_count']"
                      :title="__('admin.total_clients')"
                      :href="route('admin.clients.index')" />
              </div>

              {{-- Recent Applications --}}
              <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                  <div class="p-6">
                      <h2 class="text-lg font-semibold text-gray-700">{{ __('admin.recent_applications') }}</h2>
                  </div>
                  @if($widgets['recent']['error'])
                      <p class="px-6 pb-4 text-sm text-red-500">{{ $widgets['recent']['error'] }}</p>
                  @elseif($widgets['recent']['data'] && $widgets['recent']['data']->isNotEmpty())
                      <table class="min-w-full divide-y divide-gray-200">
                          <thead class="bg-gray-50">
                              <tr>
                                  <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_reference') }}</th>
                                  <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_client') }}</th>
                                  <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_visa_type') }}</th>
                                  <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_status') }}</th>
                                  <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('admin.col_submitted') }}</th>
                              </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-200 bg-white text-sm text-gray-700">
                              @foreach($widgets['recent']['data'] as $app)
                              <tr>
                                  <td class="px-6 py-4">
                                      <a href="{{ route('admin.applications.index', ['search' => $app->reference_number]) }}" class="text-blue-600 hover:underline">
                                          {{ $app->reference_number }}
                                      </a>
                                  </td>
                                  <td class="px-6 py-4">{{ $app->user?->name ?? '—' }}</td>
                                  <td class="px-6 py-4">{{ $app->visaType?->name ?? '—' }}</td>
                                  <td class="px-6 py-4">{{ $app->status }}</td>
                                  <td class="px-6 py-4">{{ $app->created_at->format('d M Y') }}</td>
                              </tr>
                              @endforeach
                          </tbody>
                      </table>
                  @else
                      <p class="px-6 pb-4 text-sm text-gray-500">{{ __('admin.no_recent_applications') }}</p>
                  @endif
              </div>

          </div>
      </div>
  </x-admin-layout>
  ```

- [ ] T027 Add dashboard-specific feature tests to `tests/Feature/Admin/AdminDashboardTest.php`. Fill in the `@todo` test stubs from T022 with real test code. Add these methods:
  - `test_dashboard_shows_summary_cards()`: Create admin, seed some VisaApplications and client users, `actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(__('admin.active_applications'))->assertSee(__('admin.pending_review'))->assertSee(__('admin.total_clients'))`
  - `test_dashboard_shows_recent_applications()`: Create admin, create 3 VisaApplications with a client user, `actingAs($admin)->get(route('admin.dashboard'))->assertOk()` then assert all 3 reference numbers are visible in the response
  - `test_dashboard_widget_shows_error_when_service_fails()`: Mock `AdminDashboardService` so `getActiveApplicationsCount()` returns `['data' => null, 'error' => 'Unable to load']`, then `actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee(__('admin.widget_error'))`

**Checkpoint**: `php artisan test --filter AdminDashboardTest` — all tests pass including dashboard cards and recent applications. Visit `/admin/dashboard` in browser — 3 cards visible, recent applications listed.

---

## Phase 6: User Story 4 — Consistent Admin List UI (Priority: P2)

**Goal**: Shared `<x-admin.table>` Blade component. Applications list and Users list refactored to use it with search, sort, and pagination.

**Independent Test**: Visit `/admin/applications?search=REF` — table filters. Click a column header — URL updates with `?sort_by=X&sort_dir=asc`, rows reorder. With 16+ records, pagination links appear at the bottom showing "Showing 1-15 of N". Empty search shows "No records found."

### Implementation for User Story 4

- [ ] T028 Create `resources/views/components/admin/table.blade.php`. Anonymous Blade component. Props: `columns` (assoc array `['column_key' => 'Column Label']`), `rows` (paginator or collection), `searchQuery` (string, default `''`), `sortBy` (string, default `'created_at'`), `sortDir` (string `'asc'|'desc'`, default `'desc'`), `searchAction` (string URL, default current URL via `request()->url()`). The component renders: search form (input + button), sortable table headers (each header links to the current URL with `sort_by` and `sort_dir` query params toggled), `@forelse` rows rendered via `$slot`, and pagination links. Full content:
  ```blade
  @props([
      'columns' => [],
      'rows',
      'searchQuery' => '',
      'sortBy' => 'created_at',
      'sortDir' => 'desc',
      'searchAction' => null,
  ])

  @php $action = $searchAction ?? request()->url(); @endphp

  <div>
      {{-- Search Form --}}
      <form method="GET" action="{{ $action }}" class="mb-4 flex gap-2">
          @foreach(request()->except(['search', 'page']) as $key => $value)
              <input type="hidden" name="{{ $key }}" value="{{ $value }}">
          @endforeach
          <input
              type="text"
              name="search"
              value="{{ $searchQuery }}"
              placeholder="{{ __('admin.search_placeholder') }}"
              class="rounded-md border-gray-300 shadow-sm text-sm focus:border-blue-500 focus:ring-blue-500 flex-1"
          >
          <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
              {{ __('admin.search_button') }}
          </button>
      </form>

      {{-- Table --}}
      <div class="overflow-x-auto">
          <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                  <tr>
                      @foreach($columns as $key => $label)
                          @php
                              $newDir = ($sortBy === $key && $sortDir === 'asc') ? 'desc' : 'asc';
                              $sortUrl = request()->fullUrlWithQuery(['sort_by' => $key, 'sort_dir' => $newDir, 'page' => 1]);
                          @endphp
                          <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                              <a href="{{ $sortUrl }}" class="flex items-center gap-1 hover:text-gray-700">
                                  {{ $label }}
                                  @if($sortBy === $key)
                                      <span>{{ $sortDir === 'asc' ? '↑' : '↓' }}</span>
                                  @endif
                              </a>
                          </th>
                      @endforeach
                      <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                          {{ __('admin.col_actions') }}
                      </th>
                  </tr>
              </thead>
              <tbody class="divide-y divide-gray-200 bg-white text-sm text-gray-700">
                  @forelse($rows as $row)
                      {{ $slot }}
                  @empty
                      <tr>
                          <td colspan="{{ count($columns) + 1 }}" class="px-6 py-8 text-center text-gray-400">
                              {{ __('admin.no_records') }}
                          </td>
                      </tr>
                  @endforelse
              </tbody>
          </table>
      </div>

      {{-- Pagination --}}
      @if(method_exists($rows, 'links'))
          <div class="mt-4">
              {{ $rows->links() }}
          </div>
      @endif
  </div>
  ```

  **⚠️ IMPORTANT NOTE on `@forelse` with slots**: The standard Blade `@forelse` does not work directly with `$slot` for row rendering because the slot is rendered once, not per-row. Instead, the calling view must use `@foreach` inside the component slot. The `@forelse/@empty` above handles the empty-state case. When there ARE rows, the `$slot` renders — the caller must put a `@foreach($rows as $row)` inside the slot. This is the correct pattern. If this causes issues, an alternative is to not use slots for rows and instead pass a row-rendering closure, but the slot approach is simpler for this stack.

- [ ] T029 Update `app/Http/Controllers/Admin/ApplicationController.php`. Modify the `index()` method to:
  1. Read query params: `$search = $request->input('search', '')`, `$sortBy = $request->input('sort_by', 'created_at')`, `$sortDir = $request->input('sort_dir', 'desc')`, `$statusFilter = $request->input('status', null)`
  2. Build query: `$query = \App\Models\VisaApplication::with(['user', 'visaType'])`
  3. Apply search: if `$search` is not empty, `$query->where(function($q) use ($search) { $q->where('reference_number', 'like', "%{$search}%")->orWhereHas('user', fn($u) => $u->where('name', 'like', "%{$search}%")); })`
  4. Apply status filter: if `$statusFilter`, `$query->where('status', $statusFilter)`
  5. Apply sort: validate `$sortBy` against allowed columns `['created_at', 'reference_number', 'status']`, default to `created_at`. `$query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc')`
  6. Paginate: `$applications = $query->paginate(15)->withQueryString()`
  7. Set breadcrumbs: `$breadcrumbs = [['label' => __('admin.breadcrumb_home'), 'route' => 'admin.dashboard'], ['label' => __('admin.nav_applications'), 'route' => null]]`
  8. Return: `view('admin.applications.index', compact('applications', 'search', 'sortBy', 'sortDir', 'breadcrumbs'))`

- [ ] T030 Replace `resources/views/admin/applications/index.blade.php` with a version that uses `<x-admin-layout>` and `<x-admin.table>`. Pass the correct columns and render rows inside the slot:
  ```blade
  <x-admin-layout :breadcrumbs="$breadcrumbs">
      <div class="py-12">
          <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
              <div class="mb-4 flex items-center justify-between">
                  <h1 class="text-2xl font-semibold text-gray-800">{{ __('admin.nav_applications') }}</h1>
              </div>

              <div class="overflow-hidden rounded-lg bg-white shadow-sm p-6">
                  <x-admin.table
                      :columns="[
                          'reference_number' => __('admin.col_reference'),
                          'created_at'       => __('admin.col_submitted'),
                          'status'           => __('admin.col_status'),
                      ]"
                      :rows="$applications"
                      :search-query="$search"
                      :sort-by="$sortBy"
                      :sort-dir="$sortDir"
                  >
                      @foreach($applications as $app)
                      <tr>
                          <td class="px-6 py-4">
                              <span class="font-mono text-sm">{{ $app->reference_number }}</span>
                          </td>
                          <td class="px-6 py-4">{{ $app->created_at->format('d M Y') }}</td>
                          <td class="px-6 py-4">
                              <span class="rounded-full px-2 py-1 text-xs font-medium
                                  {{ $app->status === 'approved' ? 'bg-green-100 text-green-700' :
                                     ($app->status === 'pending_review' ? 'bg-yellow-100 text-yellow-700' :
                                     'bg-gray-100 text-gray-600') }}">
                                  {{ $app->status }}
                              </span>
                          </td>
                          <td class="px-6 py-4 whitespace-nowrap">
                              <a href="{{ route('admin.applications.payments.index', $app) }}" class="text-sm text-blue-600 hover:underline">
                                  {{ __('admin.action_view') }}
                              </a>
                          </td>
                      </tr>
                      @endforeach
                  </x-admin.table>
              </div>
          </div>
      </div>
  </x-admin-layout>
  ```

- [ ] T031 Verify that `app/Http/Controllers/Admin/UserController.php`'s `index()` method passes `$breadcrumbs`, `$search`, `$sortBy`, `$sortDir` to its view, using the same pattern as T029. If the method does not already do this, update it similarly. Apply `paginate(15)->withQueryString()` to the query.

- [ ] T032 Update `resources/views/admin/users/index.blade.php` to use `<x-admin-layout>` and `<x-admin.table>`, following the same pattern as T030. Use columns: `['name' => 'Name', 'email' => 'Email', 'created_at' => __('admin.col_submitted')]`. Row actions: View and Deactivate (with confirmation). Deactivate uses a form with `@method('DELETE')` and an `onclick="return confirm(__('admin.confirm_action'))"` guard.

- [ ] T033 Add list UI feature tests to `tests/Feature/Admin/AdminDashboardTest.php`:
  - `test_applications_list_is_searchable()`: Create 3 applications, request `/admin/applications?search=` with one's reference number, assert only that application's reference appears, others do not
  - `test_applications_list_sorted_newest_first_by_default()`: Create 2 applications with different `created_at` values, request `/admin/applications`, assert the newer one appears before the older one in the response HTML
  - `test_applications_list_paginates_at_15()`: Create 16 applications, request `/admin/applications`, assert exactly 15 rows render and pagination links are present (`assertSee('Next')` or check the paginator)
  - `test_applications_list_empty_state()`: Create no applications, request `/admin/applications?search=NOTEXIST`, assert `__('admin.no_records')` appears in response

**Checkpoint**: `php artisan test --filter AdminDashboardTest` — all tests pass. Visit `/admin/applications`, type in search box and press Enter — results filter. Click "Reference" column header — URL updates and rows reorder.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Final wiring, constitution compliance scan, and regression verification.

- [ ] T034 Scan ALL Blade files in `resources/views/admin/` and `resources/views/components/admin/` for any hardcoded English strings NOT wrapped in `__()`. Replace any found with the appropriate `__('admin.*')` key. If a needed key does not exist in `lang/en/admin.php`, add it. This is required by Constitution Principle X.

- [ ] T035 [P] Verify `resources/views/errors/403.blade.php` exists and renders correctly. Test by visiting any admin URL as a client user in the browser — should see the user-friendly 403 message, not a stack trace.

- [ ] T036 [P] Run `php artisan route:list --path=admin` and confirm ALL admin routes have the `can:dashboard.admin` middleware in the Middleware column. If any route is missing it, add `->middleware('can:dashboard.admin')` to that route definition in `routes/web.php`.

- [ ] T037 Run the full test suite: `php artisan test`. Confirm all pre-existing tests still pass (AdminPaymentTest, DocumentAdminTest, UserManagementTest, PaymentWebhookTest, PaymentCheckoutTest, etc.) — these must not regress from the refactoring in T030–T032.

- [ ] T038 [P] Clear all caches and do a final manual walkthrough following `specs/006-admin-panel-foundation/quickstart.md`: `php artisan config:clear && php artisan view:clear && php artisan route:clear`. Then test each step in the quickstart guide manually.

- [ ] T039 [P] Update `specs/006-admin-panel-foundation/checklists/requirements.md` — mark all items as complete and add a "Implementation complete" note with today's date.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1** (Setup): No dependencies — start immediately
- **Phase 2** (Foundational): Depends on Phase 1 complete — **BLOCKS** all user story phases
- **Phase 3** (US1 — Navigation): Depends on Phase 2 complete
- **Phase 4** (US2 — Access Control): Depends on Phase 2 complete. **Can run in parallel with Phase 3**
- **Phase 5** (US3 — Dashboard): Depends on Phase 3 complete (needs admin layout + nav)
- **Phase 6** (US4 — List UI): Depends on Phase 3 complete (needs admin layout). Can start in parallel with Phase 5
- **Phase 7** (Polish): Depends on all previous phases complete

### User Story Dependencies

- **US1 (P1)** — Navigation: Can start after Phase 2. No dependency on US2.
- **US2 (P1)** — Access Control: Can start after Phase 2. No dependency on US1. (**Parallel with US1**)
- **US3 (P2)** — Dashboard: Depends on US1 (needs nav rendered via admin layout).
- **US4 (P2)** — List UI: Depends on US1 (needs admin layout). **Can run in parallel with US3**.

### Within Each Phase

- Tasks marked `[P]` within a phase can be executed simultaneously (different files).
- Non-`[P]` tasks within a phase should be executed sequentially in the order listed.

### Parallel Opportunities

```bash
# Phase 1 — run all in parallel:
T002 (lang/en)   T003 (lang/ar)   T004 (BreadcrumbComposer)

# Phase 2 — run stub controllers in parallel:
T007 (VisaTypeController)  T008 (ClientController)
T009 (TaskBuilderController)  T010 (ReviewerController)

# Phase 3 and Phase 4 — fully parallel:
Phase 3 (US1 Navigation)  ||  Phase 4 (US2 Access Control)

# Phase 5 and Phase 6 — fully parallel:
Phase 5 (US3 Dashboard)  ||  Phase 6 (US4 List UI)

# Phase 7 — run in parallel:
T035  T036  T038  T039
```

---

## Implementation Strategy

### MVP First (US1 + US2 — Navigation & Security)

1. Complete Phase 1: Setup (T001–T005)
2. Complete Phase 2: Foundational routes + stubs (T006–T013)
3. Complete Phase 3: Navigation (T014–T018) **AND** Phase 4: Access control (T019–T022) in parallel
4. **STOP and VALIDATE**: All admin routes accessible to admins (200), blocked for clients/reviewers (403), redirect for guests. Navigation visible with correct active state.
5. This is the deployable MVP — all Phase 6 sub-features can now be scaffolded.

### Full Delivery

6. Complete Phase 5: Dashboard home (T023–T027)
7. Complete Phase 6: Shared list UI + refactor (T028–T033) in parallel with Phase 5
8. Complete Phase 7: Polish (T034–T039)
9. Run `php artisan test` — all 104+ tests pass

---

## Notes

- `[P]` tasks operate on different files and can be assigned to separate agents simultaneously.
- Every task references exact file paths — create the file at the exact path stated.
- When a task says "verify X exists / no changes needed", check it and move on — do not skip.
- All Blade strings MUST use `__('admin.*')` — hardcoded English in Blade violates the project constitution.
- `withQueryString()` on paginators preserves search/sort params across pages — do not omit it.
- The `@forelse` empty state in `<x-admin.table>` only fires when `$rows` is empty. The calling view must put a real `@foreach($rows as $row)` INSIDE the component's default slot.
- Confirmation dialogs for destructive actions use `onclick="return confirm('...')"` — keep it simple, no modals required for Phase 6 foundation.
- After T037, if any pre-existing test fails, fix the regression before marking Phase 7 complete.
