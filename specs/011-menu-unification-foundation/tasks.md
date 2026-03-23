# Tasks: Menu Unification — Foundation and Architecture

**Branch**: `011-menu-unification-foundation`
**Input**: Design documents from `/specs/011-menu-unification-foundation/`
**Prerequisites**: plan.md ✓, spec.md ✓, research.md ✓, data-model.md ✓, contracts/menu-service.md ✓, quickstart.md ✓

**Tech stack**: PHP 8.2+ / Laravel 11, Blade SSR, `spatie/laravel-permission` v6+
**No database migrations** — config-based only.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no blocking dependencies)
- **[Story]**: Which user story this task maps to
- **No [P]**: Must run after the previous task completes

---

## Phase 1: Setup — Language Files & Unified Config

**Purpose**: Create the translation files and the single centralized navigation config that replaces `config/admin-navigation.php`. Must complete before any service/component work.

- [ ] T001 [P] Create `resources/lang/en/navigation.php` with the following exact content:

  ```php
  <?php
  return [
      'nav_aria_label' => 'Navigation',
      // Admin navigation
      'admin_dashboard'    => 'Dashboard',
      'admin_applications' => 'Applications',
      'admin_visa_types'   => 'Visa Types',
      'admin_clients'      => 'Clients',
      'admin_task_builder' => 'Task Builder',
      'admin_reviewers'    => 'Reviewers',
      'admin_users'        => 'Users',
      // Reviewer navigation
      'reviewer_dashboard'    => 'Dashboard',
      'reviewer_applications' => 'Applications',
      // Client tab navigation
      'client_overview'  => 'Overview',
      'client_documents' => 'Documents',
      'client_tasks'     => 'Tasks',
      'client_payments'  => 'Payments',
      'client_timeline'  => 'Timeline',
      'client_messages'  => 'Messages',
      'client_profile'   => 'Profile',
      'client_support'   => 'Support',
  ];
  ```

- [ ] T002 [P] Create `resources/lang/ar/navigation.php` with the following exact content (Arabic translations, RTL):

  ```php
  <?php
  return [
      'nav_aria_label' => 'قائمة التنقل',
      // Admin navigation
      'admin_dashboard'    => 'لوحة التحكم',
      'admin_applications' => 'الطلبات',
      'admin_visa_types'   => 'أنواع التأشيرات',
      'admin_clients'      => 'العملاء',
      'admin_task_builder' => 'منشئ المهام',
      'admin_reviewers'    => 'المراجعون',
      'admin_users'        => 'المستخدمون',
      // Reviewer navigation
      'reviewer_dashboard'    => 'لوحة التحكم',
      'reviewer_applications' => 'الطلبات',
      // Client tab navigation
      'client_overview'  => 'نظرة عامة',
      'client_documents' => 'المستندات',
      'client_tasks'     => 'المهام',
      'client_payments'  => 'المدفوعات',
      'client_timeline'  => 'الجدول الزمني',
      'client_messages'  => 'الرسائل',
      'client_profile'   => 'الملف الشخصي',
      'client_support'   => 'الدعم',
  ];
  ```

- [ ] T003 Create `config/navigation.php` with the following exact content (17 items, replaces `config/admin-navigation.php`):

  ```php
  <?php
  /**
   * Unified navigation configuration.
   *
   * Each item:
   *   label_key      - translation key from resources/lang/{locale}/navigation.php
   *   route          - named Laravel route (must exist in routes/web.php)
   *   route_params   - parameters passed to route() helper (optional, default [])
   *   roles          - array of role slugs that can see this item ('admin','reviewer','client')
   *   active_pattern - route name pattern for request()->routeIs(); null = use exact route name
   *   position       - display order (ascending); lower number appears first
   */
  return [
      // ── Admin items ─────────────────────────────────────────────────────────
      [
          'label_key'      => 'navigation.admin_dashboard',
          'route'          => 'admin.dashboard',
          'route_params'   => [],
          'roles'          => ['admin'],
          'active_pattern' => null,
          'position'       => 10,
      ],
      [
          'label_key'      => 'navigation.admin_applications',
          'route'          => 'admin.applications.index',
          'route_params'   => [],
          'roles'          => ['admin'],
          'active_pattern' => 'admin.applications.*',
          'position'       => 20,
      ],
      [
          'label_key'      => 'navigation.admin_visa_types',
          'route'          => 'admin.visa-types.index',
          'route_params'   => [],
          'roles'          => ['admin'],
          'active_pattern' => 'admin.visa-types.*',
          'position'       => 30,
      ],
      [
          'label_key'      => 'navigation.admin_clients',
          'route'          => 'admin.clients.index',
          'route_params'   => [],
          'roles'          => ['admin'],
          'active_pattern' => 'admin.clients.*',
          'position'       => 40,
      ],
      [
          'label_key'      => 'navigation.admin_task_builder',
          'route'          => 'admin.task-builder.index',
          'route_params'   => [],
          'roles'          => ['admin'],
          'active_pattern' => 'admin.task-builder.*',
          'position'       => 50,
      ],
      [
          'label_key'      => 'navigation.admin_reviewers',
          'route'          => 'admin.reviewers.index',
          'route_params'   => [],
          'roles'          => ['admin'],
          'active_pattern' => 'admin.reviewers.*',
          'position'       => 60,
      ],
      [
          'label_key'      => 'navigation.admin_users',
          'route'          => 'admin.users.index',
          'route_params'   => [],
          'roles'          => ['admin'],
          'active_pattern' => 'admin.users.*',
          'position'       => 70,
      ],
      // ── Reviewer items ───────────────────────────────────────────────────────
      [
          'label_key'      => 'navigation.reviewer_dashboard',
          'route'          => 'reviewer.dashboard',
          'route_params'   => [],
          'roles'          => ['reviewer'],
          'active_pattern' => null,
          'position'       => 10,
      ],
      [
          'label_key'      => 'navigation.reviewer_applications',
          'route'          => 'reviewer.dashboard',
          'route_params'   => [],
          'roles'          => ['reviewer'],
          'active_pattern' => 'reviewer.applications.*',
          'position'       => 20,
      ],
      // ── Client items (tab navigation) ────────────────────────────────────────
      [
          'label_key'      => 'navigation.client_overview',
          'route'          => 'client.dashboard',
          'route_params'   => [],
          'roles'          => ['client'],
          'active_pattern' => null,
          'position'       => 10,
      ],
      [
          'label_key'      => 'navigation.client_documents',
          'route'          => 'client.dashboard',
          'route_params'   => ['tab' => 'documents'],
          'roles'          => ['client'],
          'active_pattern' => null,
          'position'       => 20,
      ],
      [
          'label_key'      => 'navigation.client_tasks',
          'route'          => 'client.dashboard',
          'route_params'   => ['tab' => 'tasks'],
          'roles'          => ['client'],
          'active_pattern' => null,
          'position'       => 30,
      ],
      [
          'label_key'      => 'navigation.client_payments',
          'route'          => 'client.dashboard',
          'route_params'   => ['tab' => 'payments'],
          'roles'          => ['client'],
          'active_pattern' => null,
          'position'       => 40,
      ],
      [
          'label_key'      => 'navigation.client_timeline',
          'route'          => 'client.dashboard',
          'route_params'   => ['tab' => 'timeline'],
          'roles'          => ['client'],
          'active_pattern' => null,
          'position'       => 50,
      ],
      [
          'label_key'      => 'navigation.client_messages',
          'route'          => 'client.dashboard',
          'route_params'   => ['tab' => 'messages'],
          'roles'          => ['client'],
          'active_pattern' => null,
          'position'       => 60,
      ],
      [
          'label_key'      => 'navigation.client_profile',
          'route'          => 'client.dashboard',
          'route_params'   => ['tab' => 'profile'],
          'roles'          => ['client'],
          'active_pattern' => null,
          'position'       => 70,
      ],
      [
          'label_key'      => 'navigation.client_support',
          'route'          => 'client.dashboard',
          'route_params'   => ['tab' => 'support'],
          'roles'          => ['client'],
          'active_pattern' => null,
          'position'       => 80,
      ],
  ];
  ```

**Checkpoint**: Lang files and unified config exist. No other files touched yet.

---

## Phase 2: Foundational — Service, Composer, Registration

**Purpose**: Create the `MenuService` (filtering logic) and `MenuComposer` (view injection), then register the composer. These are prerequisites for ALL Blade component updates.

**⚠️ CRITICAL**: Phase 3 cannot begin until this phase is complete.

- [ ] T004 Create `app/Services/Navigation/MenuService.php` with the following exact content:

  ```php
  <?php

  namespace App\Services\Navigation;

  use App\Models\User;
  use Illuminate\Support\Facades\Log;

  class MenuService
  {
      /**
       * Return navigation items permitted for the given user,
       * sorted ascending by position.
       *
       * @return array<int, array{label_key: string, route: string, route_params: array, active_pattern: string|null, position: int}>
       */
      public function getForUser(?User $user): array
      {
          if ($user === null) {
              Log::warning('MenuService::getForUser called with null user.');
              return [];
          }

          $items = config('navigation', []);
          $seen  = [];
          $result = [];

          foreach ($items as $index => $item) {
              // Validate required keys
              if (! isset($item['label_key'], $item['route'], $item['roles'], $item['position'])) {
                  Log::warning("MenuService: item at index {$index} is missing required keys — skipped.");
                  continue;
              }

              // Check if user has at least one of the item's roles
              if (! $user->hasAnyRole($item['roles'])) {
                  continue;
              }

              // Deduplicate by route + serialized params (for multi-role users)
              $dedupeKey = $item['route'] . '|' . serialize($item['route_params'] ?? []);
              if (isset($seen[$dedupeKey])) {
                  continue;
              }
              $seen[$dedupeKey] = true;

              $result[] = [
                  'label_key'      => $item['label_key'],
                  'route'          => $item['route'],
                  'route_params'   => $item['route_params'] ?? [],
                  'active_pattern' => $item['active_pattern'] ?? null,
                  'position'       => $item['position'],
              ];
          }

          usort($result, fn ($a, $b) => $a['position'] <=> $b['position']);

          return $result;
      }
  }
  ```

- [ ] T005 Create `app/View/Composers/MenuComposer.php` with the following exact content:

  ```php
  <?php

  namespace App\View\Composers;

  use App\Services\Navigation\MenuService;
  use Illuminate\Support\Facades\Auth;
  use Illuminate\View\View;

  class MenuComposer
  {
      public function __construct(private MenuService $menuService) {}

      public function compose(View $view): void
      {
          $view->with('menuItems', $this->menuService->getForUser(Auth::user()));
      }
  }
  ```

- [ ] T006 Update `app/Providers/AppServiceProvider.php` — add the following two lines inside the `boot()` method, after the existing `View::composer('admin.*', AdminBreadcrumbComposer::class);` line:

  ```php
  // Import at the top of the file (add after the existing AdminBreadcrumbComposer import):
  use App\View\Composers\MenuComposer;

  // Inside boot() method, add this single line:
  View::composer('components.nav.main', MenuComposer::class);
  ```

  The full `boot()` method after the change should look like:

  ```php
  public function boot(): void
  {
      Gate::policy(User::class, UserPolicy::class);
      Gate::policy(ApplicationTask::class, ApplicationTaskPolicy::class);
      Gate::policy(Document::class, DocumentPolicy::class);
      Gate::policy(VisaApplication::class, VisaApplicationPolicy::class);
      Gate::policy(Payment::class, PaymentPolicy::class);

      View::composer('admin.*', AdminBreadcrumbComposer::class);
      View::composer('components.nav.main', MenuComposer::class);
  }
  ```

**Checkpoint**: `MenuService` and `MenuComposer` exist. Running `php artisan config:cache` should succeed. No Blade changes yet — existing pages still work.

---

## Phase 3: US1 — Centralized Menu Definition (Priority: P1)

**Goal**: Replace all three separate nav implementations with components that use `$menuItems` injected by `MenuComposer`. After this phase, all menu items are defined only in `config/navigation.php`.

**Independent Test**: Browse as Admin — nav renders identically to before. Browse as Reviewer — nav bar appears for the first time. Browse as Client — tab nav renders identically to before.

- [ ] T007 Create `resources/views/components/nav/main.blade.php` with the following exact content (shared rendering component — used by all three role nav components):

  ```blade
  {{--
      Shared navigation renderer.
      Receives $menuItems from MenuComposer (injected via AppServiceProvider).
      Each item: label_key, route, route_params, active_pattern, position.
  --}}
  <nav class="flex space-x-1 overflow-x-auto" aria-label="{{ __('navigation.nav_aria_label') }}">
      @foreach ($menuItems as $item)
          @php
              $pattern  = $item['active_pattern'] ?? $item['route'];
              $isActive = request()->routeIs($pattern);
              // For client tab items, also check the current tab parameter
              if ($item['active_pattern'] === null && ! empty($item['route_params']['tab'])) {
                  $isActive = request()->routeIs($item['route'])
                      && request()->route('tab') === $item['route_params']['tab'];
              }
              // Client overview: active when on client.dashboard with no tab or tab=overview
              if ($item['route'] === 'client.dashboard' && empty($item['route_params'])) {
                  $isActive = request()->routeIs('client.dashboard')
                      && (request()->route('tab') === null || request()->route('tab') === 'overview');
              }
          @endphp
          <a href="{{ route($item['route'], $item['route_params']) }}"
             class="whitespace-nowrap px-4 py-2 text-sm font-medium rounded-md transition-colors
                    {{ $isActive
                        ? 'bg-gray-900 text-white'
                        : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
              {{ __($item['label_key']) }}
          </a>
      @endforeach
  </nav>
  ```

- [ ] T008 [P] Replace the entire content of `resources/views/components/admin/nav.blade.php` with:

  ```blade
  <x-nav.main />
  ```

  > **Before**: The file contained a `@foreach(config('admin-navigation') as $item)` loop. That loop is now replaced entirely by the shared component. The `MenuComposer` registered in T006 automatically injects `$menuItems` into this component before it renders.

- [ ] T009 [P] Create `resources/views/components/reviewer/nav.blade.php` with the following content:

  ```blade
  <x-nav.main />
  ```

  > This is a new file. The `MenuComposer` registered in T006 injects `$menuItems` (filtered to reviewer role) before this renders.

- [ ] T010 Update `resources/views/components/reviewer-layout.blade.php` — add `<x-reviewer.nav />` and dashboard title inside the `<x-slot name="header">` block. Replace the entire file with:

  ```blade
  <x-app-layout>
      <x-slot name="header">
          <x-reviewer.nav />
          <h2 class="font-semibold text-xl text-gray-800 leading-tight mt-2">
              {{ __('reviewer.dashboard_title') }}
          </h2>
      </x-slot>

      {{ $slot }}
  </x-app-layout>
  ```

  > **Before**: The file only showed a hardcoded `<h2>` title with no nav component. Now it renders the reviewer nav bar, matching the pattern used by the admin layout.

- [ ] T011 Replace the entire content of `resources/views/components/client/nav.blade.php` with:

  ```blade
  <x-nav.main />
  ```

  > **Before**: The file contained an inline `@php $tabs = ['overview' => ..., 'documents' => ...]` array with a `@foreach` loop. That inline array is now removed. The `MenuComposer` registered in T006 injects `$menuItems` (filtered to client role) automatically.

**Checkpoint**: All three role nav components now use `$menuItems`. Visit `/admin/dashboard`, `/reviewer/dashboard`, `/client/dashboard` and verify nav renders correctly for each role. The reviewer now has a navigation bar.

---

## Phase 4: US2 — Role-Based Menu Visibility (Priority: P1)

**Goal**: Confirm via tests that each role sees only its designated items and no items from other roles.

**Independent Test**: `php artisan test --filter Navigation`

- [ ] T012 [P] [US2] Create `tests/Feature/Navigation/MenuServiceTest.php` with the following exact content:

  ```php
  <?php

  namespace Tests\Feature\Navigation;

  use App\Models\User;
  use App\Services\Navigation\MenuService;
  use Database\Seeders\RolePermissionSeeder;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Tests\TestCase;

  class MenuServiceTest extends TestCase
  {
      use RefreshDatabase;

      protected function setUp(): void
      {
          parent::setUp();
          $this->seed(RolePermissionSeeder::class);
      }

      public function test_admin_receives_only_admin_items(): void
      {
          $admin = User::factory()->create()->assignRole('admin');
          $items = app(MenuService::class)->getForUser($admin);

          $routes = array_column($items, 'route');
          $this->assertContains('admin.dashboard', $routes);
          $this->assertContains('admin.applications.index', $routes);
          $this->assertNotContains('reviewer.dashboard', $routes);
          $this->assertNotContains('client.dashboard', $routes);
          $this->assertCount(7, $items);
      }

      public function test_reviewer_receives_only_reviewer_items(): void
      {
          $reviewer = User::factory()->create()->assignRole('reviewer');
          $items = app(MenuService::class)->getForUser($reviewer);

          $routes = array_column($items, 'route');
          $this->assertContains('reviewer.dashboard', $routes);
          $this->assertNotContains('admin.dashboard', $routes);
          $this->assertCount(2, $items);
      }

      public function test_client_receives_only_client_items(): void
      {
          $client = User::factory()->create()->assignRole('client');
          $items = app(MenuService::class)->getForUser($client);

          $routeParams = array_column($items, 'route_params');
          $this->assertNotContains('admin.dashboard', array_column($items, 'route'));
          $this->assertCount(8, $items);
      }

      public function test_items_are_sorted_by_position_ascending(): void
      {
          $admin = User::factory()->create()->assignRole('admin');
          $items = app(MenuService::class)->getForUser($admin);

          $positions = array_column($items, 'position');
          $sorted = $positions;
          sort($sorted);
          $this->assertSame($sorted, $positions);
      }

      public function test_null_user_returns_empty_array(): void
      {
          $items = app(MenuService::class)->getForUser(null);
          $this->assertSame([], $items);
      }

      public function test_multi_role_user_sees_union_without_duplicates(): void
      {
          $user = User::factory()->create();
          $user->assignRole('admin');
          $user->assignRole('reviewer');

          $items = app(MenuService::class)->getForUser($user);
          // 7 admin + 2 reviewer = 9 unique items
          $this->assertCount(9, $items);
      }
  }
  ```

- [ ] T013 [P] [US2] Create `tests/Feature/Navigation/MenuVisibilityTest.php` with the following exact content:

  ```php
  <?php

  namespace Tests\Feature\Navigation;

  use App\Models\User;
  use Database\Seeders\RolePermissionSeeder;
  use Database\Seeders\VisaTypeSeeder;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Tests\TestCase;

  class MenuVisibilityTest extends TestCase
  {
      use RefreshDatabase;

      protected function setUp(): void
      {
          parent::setUp();
          $this->seed(RolePermissionSeeder::class);
          $this->seed(VisaTypeSeeder::class);
      }

      public function test_admin_nav_shows_admin_items_only(): void
      {
          $admin = User::factory()->create()->assignRole('admin');

          $response = $this->actingAs($admin)->get(route('admin.dashboard'));

          $response->assertOk();
          $response->assertSee(__('navigation.admin_applications'));
          $response->assertSee(__('navigation.admin_visa_types'));
          $response->assertDontSee(__('navigation.reviewer_applications'));
          $response->assertDontSee(__('navigation.client_documents'));
      }

      public function test_reviewer_nav_shows_reviewer_items_only(): void
      {
          $reviewer = User::factory()->create()->assignRole('reviewer');

          $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));

          $response->assertOk();
          $response->assertSee(__('navigation.reviewer_dashboard'));
          $response->assertSee(__('navigation.reviewer_applications'));
          $response->assertDontSee(__('navigation.admin_applications'));
          $response->assertDontSee(__('navigation.client_documents'));
      }

      public function test_client_nav_shows_client_tabs_only(): void
      {
          $client = User::factory()->create()->assignRole('client');

          $response = $this->actingAs($client)->get(route('client.dashboard'));

          $response->assertOk();
          $response->assertSee(__('navigation.client_documents'));
          $response->assertSee(__('navigation.client_tasks'));
          $response->assertDontSee(__('navigation.admin_applications'));
          $response->assertDontSee(__('navigation.reviewer_applications'));
      }

      public function test_reviewer_nav_bar_is_present(): void
      {
          $reviewer = User::factory()->create()->assignRole('reviewer');

          $response = $this->actingAs($reviewer)->get(route('reviewer.dashboard'));

          $response->assertOk();
          // Reviewer layout now includes <x-reviewer.nav /> — verify nav renders
          $response->assertSee('<nav', false);
      }

      public function test_admin_cannot_access_reviewer_dashboard(): void
      {
          $admin = User::factory()->create()->assignRole('admin');

          // Backend auth still enforced regardless of menu visibility
          $this->actingAs($admin)->get(route('reviewer.dashboard'))->assertForbidden();
      }

      public function test_client_cannot_access_admin_dashboard(): void
      {
          $client = User::factory()->create()->assignRole('client');

          $this->actingAs($client)->get(route('admin.dashboard'))->assertForbidden();
      }
  }
  ```

**Checkpoint**: Run `php artisan test --filter Navigation` — all 12 tests pass.

---

## Phase 5: US3 — Active Navigation State (Priority: P2)

**Goal**: Verify active state highlighting works for direct routes and for sub-routes (e.g., being on an application detail page keeps "Applications" highlighted).

**Independent Test**: Manual — navigate to `/admin/applications/1` and confirm the Applications nav item has the active CSS class `bg-gray-900 text-white`.

> **Note**: The active state implementation was built into `resources/views/components/nav/main.blade.php` in T007. This phase verifies the `active_pattern` values in `config/navigation.php` are correct and adds a targeted test.

- [ ] T014 [US3] Verify `config/navigation.php` (created in T003) has the following `active_pattern` wildcards — open the file and confirm these exact values are present:
  - `admin.applications.*` for the Applications item
  - `admin.visa-types.*` for the Visa Types item
  - `admin.clients.*` for the Clients item
  - `admin.task-builder.*` for the Task Builder item
  - `admin.reviewers.*` for the Reviewers item
  - `admin.users.*` for the Users item
  - `reviewer.applications.*` for the reviewer Applications item
  - `null` for all Dashboard items and all client tab items

  If any value is wrong, correct it in `config/navigation.php`. No other changes needed.

- [ ] T015 [P] [US3] Add active-state test method to `tests/Feature/Navigation/MenuVisibilityTest.php` — append this method to the existing test class:

  ```php
  public function test_applications_nav_item_active_on_application_detail_page(): void
  {
      $admin = User::factory()->create()->assignRole('admin');

      // Visit the applications list (active_pattern: admin.applications.*)
      $response = $this->actingAs($admin)->get(route('admin.applications.index'));

      $response->assertOk();
      // The active item has class bg-gray-900; verify the Applications link is active
      $response->assertSee('bg-gray-900');
  }
  ```

**Checkpoint**: `php artisan test --filter Navigation` still passes. Active state renders correctly.

---

## Phase 6: US4 — Role-Aware Dashboard Link (Priority: P2)

**Goal**: Each role's "Dashboard" link navigates to the correct role-specific dashboard.

**Independent Test**: Click the Dashboard nav item as each role and confirm the correct dashboard loads.

> **Note**: This is delivered by `config/navigation.php` (T003) which defines separate Dashboard items per role pointing to `admin.dashboard`, `reviewer.dashboard`, and `client.dashboard` respectively. The MenuService filters ensure each role sees only their own Dashboard item. No additional code change is required.

- [ ] T016 [US4] Verify in `config/navigation.php` (created in T003) that:
  - The item with `'label_key' => 'navigation.admin_dashboard'` has `'route' => 'admin.dashboard'` and `'roles' => ['admin']`
  - The item with `'label_key' => 'navigation.reviewer_dashboard'` has `'route' => 'reviewer.dashboard'` and `'roles' => ['reviewer']`
  - The item with `'label_key' => 'navigation.client_overview'` has `'route' => 'client.dashboard'` and `'roles' => ['client']`

  If any value is wrong, correct it. No other changes needed.

- [ ] T017 [P] [US4] Add dashboard link test methods to `tests/Feature/Navigation/MenuVisibilityTest.php` — append these two methods to the existing test class:

  ```php
  public function test_admin_dashboard_link_goes_to_admin_dashboard(): void
  {
      $admin = User::factory()->create()->assignRole('admin');
      $items = app(\App\Services\Navigation\MenuService::class)->getForUser($admin);

      $dashboardItem = collect($items)->firstWhere('label_key', 'navigation.admin_dashboard');
      $this->assertNotNull($dashboardItem);
      $this->assertSame('admin.dashboard', $dashboardItem['route']);
  }

  public function test_reviewer_dashboard_link_goes_to_reviewer_dashboard(): void
  {
      $reviewer = User::factory()->create()->assignRole('reviewer');
      $items = app(\App\Services\Navigation\MenuService::class)->getForUser($reviewer);

      $dashboardItem = collect($items)->firstWhere('label_key', 'navigation.reviewer_dashboard');
      $this->assertNotNull($dashboardItem);
      $this->assertSame('reviewer.dashboard', $dashboardItem['route']);
  }
  ```

**Checkpoint**: `php artisan test --filter Navigation` — all tests pass.

---

## Phase 7: Polish & Cleanup

**Purpose**: Remove the now-obsolete `config/admin-navigation.php`, run the full test suite to confirm zero regressions, and validate against the quickstart scenarios.

- [ ] T018 Delete `config/admin-navigation.php` — this file has been fully replaced by `config/navigation.php`. Run `php artisan config:cache` after deletion to confirm no config errors.

- [ ] T019 Run the full test suite to confirm zero regressions:

  ```bash
  php artisan test
  ```

  All 168+ existing tests must continue to pass. If any test references `config('admin-navigation')`, update it to use `config('navigation')` or the `MenuService` instead.

- [ ] T020 Validate against quickstart.md acceptance scenarios manually:
  - Log in as admin → verify 7 nav items appear, no reviewer/client items
  - Log in as reviewer → verify 2 nav items appear (Dashboard, Applications), nav bar visible for first time
  - Log in as client → verify 8 tab items appear, no admin/reviewer items
  - Switch to Arabic locale (`POST /language/ar`) → verify nav labels in Arabic
  - As admin, navigate to an application detail page → verify Applications item highlighted
  - As client, directly visit `/admin/dashboard` → confirm 403

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately; T001 and T002 are parallel
- **Phase 2 (Foundational)**: Depends on Phase 1 (needs `config/navigation.php` to exist for MenuService); T004 and T005 are parallel; T006 depends on T005
- **Phase 3 (US1)**: Depends on Phase 2 completion; T008, T009 are parallel; T010 and T011 are parallel after T009; T007 must precede T008/T009/T011
- **Phase 4 (US2)**: Depends on Phase 3 completion; T012 and T013 are parallel
- **Phase 5 (US3)**: Depends on Phase 3; T014 before T015
- **Phase 6 (US4)**: Depends on Phase 3; T016 before T017; Phase 5 and Phase 6 are parallel with each other
- **Phase 7 (Polish)**: Depends on Phases 4–6; T018 → T019 → T020

### User Story Dependencies

- **US1 (P1)**: Needs Phase 2 complete — then T007 → T008/T009/T011 in parallel → T010
- **US2 (P1)**: Needs Phase 3 complete — T012 and T013 in parallel
- **US3 (P2)**: Needs Phase 3 complete — T014 then T015; no dependency on US2
- **US4 (P2)**: Needs Phase 3 complete — T016 then T017; no dependency on US2 or US3

### Parallel Opportunities

```
Phase 1:  T001 ║ T002  (then T003)
Phase 2:  T004 ║ T005  (then T006)
Phase 3:  T007 → (T008 ║ T009) → (T010 ║ T011)
Phase 4:  T012 ║ T013
Phase 5+6: (T014→T015) ║ (T016→T017)
Phase 7:  T018 → T019 → T020
```

---

## Implementation Strategy

### MVP First (US1 + US2 only)

1. Complete Phase 1: Setup (T001–T003)
2. Complete Phase 2: Foundational (T004–T006)
3. Complete Phase 3: US1 Blade components (T007–T011)
4. Complete Phase 4: US2 Tests (T012–T013)
5. **STOP and VALIDATE**: `php artisan test --filter Navigation` — all pass
6. MVP delivered: unified menu, role filtering working

### Full Delivery (all 4 user stories)

Continue from MVP:

7. Phase 5: US3 active state verification (T014–T015)
8. Phase 6: US4 dashboard link verification (T016–T017)
9. Phase 7: Cleanup + full regression (T018–T020)

---

## Notes

- Every task includes the exact file path and exact code to write
- `config/navigation.php` (T003) is the single source of truth — never define menu items anywhere else
- `MenuService::getForUser()` uses `$user->hasAnyRole()` from `spatie/laravel-permission` — no custom role checking
- The `MenuComposer` is registered for the Blade component view names (e.g., `components.admin.nav`), not for route patterns
- Client tab active state uses `request()->route('tab')` because all client tabs share the same route name (`client.dashboard`)
- After T018 (deleting `config/admin-navigation.php`), run `php artisan config:clear` to flush cached config
- All string literals in Blade use `__('navigation.key')` — never hardcoded English strings
