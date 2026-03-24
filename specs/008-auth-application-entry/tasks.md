# Tasks: Authentication & Application Entry

**Input**: Design documents from `/specs/008-auth-application-entry/`
**Prerequisites**: plan.md ✅, spec.md ✅, research.md ✅, data-model.md ✅, contracts/ ✅

**Context for implementer**: This feature has 3 gaps to close. Everything else (auth controllers, role assignment, auto-login, brute-force throttle, admin seeder, password rules) is already fully implemented in the codebase. Do NOT rewrite or touch existing working code — only make the targeted changes described in each task.

**No tests were requested in the spec** — the constitution mandates feature tests for auth flows; a test phase is included at the end.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no shared dependencies)
- **[Story]**: Which user story this task belongs to (US1–US4 map to spec.md)

---

## Phase 1: Setup — Confirm Baseline

**Purpose**: Verify the existing test suite is green before making any changes. This is the safety net.

- [X] T001 Run `php artisan test` from the project root and confirm all tests pass. If any fail, stop and report — do NOT proceed with implementation until baseline is green.

---

## Phase 2: Foundational — Lang Keys

**Purpose**: Add the two new translation keys that Phase 3 and Phase 4 both depend on. Complete this before touching any views.

**⚠️ CRITICAL**: All string additions must go to `resources/lang/` (NOT `lang/`). The `lang/` files are proxies.

- [X] T002 Add the `have_account` key to `resources/lang/en/client.php`.

  Open `resources/lang/en/client.php`. Find the closing `];` at the end of the array (currently line 61). Insert the following line immediately before it:

  ```php
      'have_account' => 'Already have an account? Login',
  ```

- [X] T003 [P] Add the `have_account` key to `resources/lang/ar/client.php`.

  Open `resources/lang/ar/client.php`. Find the closing `];` at the end of the array. Insert the following line immediately before it:

  ```php
      'have_account' => 'هل لديك حساب؟ تسجيل الدخول',
  ```

- [X] T004 [P] Add the `apply_now` key to `resources/lang/en/auth.php`.

  Open `resources/lang/en/auth.php`. Find the closing `];` at the end of the array (currently after the `'throttle'` key). Insert the following line immediately before `];`:

  ```php
      'apply_now' => "Don't have an account? Apply now",
  ```

- [X] T005 [P] Add the `apply_now` key to `resources/lang/ar/auth.php`.

  Open `resources/lang/ar/auth.php`. Find the closing `];` at the end of the array. Insert the following line immediately before `];`:

  ```php
      'apply_now' => 'ليس لديك حساب؟ قدّم الآن',
  ```

**Checkpoint**: Four lang files updated. Verify by checking that the new keys exist. Do NOT proceed to Phase 3 until T002–T005 are complete.

---

## Phase 3: User Story 1 — Apply Form as Root Entry Point (Priority: P1) 🎯 MVP

**Goal**: Visiting `/` renders the apply form for guests and redirects authenticated users to their dashboard. Duplicate email shows a specific error message and highlights the login link.

**Independent Test**: Navigate to `http://localhost:8000` as a guest — the 3-step apply form must load with no redirect. Navigate there as a logged-in user — must redirect to the dashboard.

### Implementation for User Story 1

- [X] T006 [US1] Modify `routes/web.php` to redirect `GET /` to the apply form under the `guest` middleware.

  Open `routes/web.php`. Find and **remove** this entire block (lines 26–28):

  ```php
  Route::get('/', function () {
      return view('welcome');
  });
  ```

  Then find the existing `guest` middleware group (starts around line 34):

  ```php
  Route::middleware('guest')->group(function () {
      Route::get('/apply', [OnboardingController::class, 'show'])->name('onboarding.show');
      Route::post('/apply', [OnboardingController::class, 'store'])->name('onboarding.store');
  });
  ```

  Replace it with this (adding the root redirect as the first route inside the group):

  ```php
  Route::middleware('guest')->group(function () {
      Route::get('/', fn() => redirect()->route('onboarding.show'))->name('home');
      Route::get('/apply', [OnboardingController::class, 'show'])->name('onboarding.show');
      Route::post('/apply', [OnboardingController::class, 'store'])->name('onboarding.store');
  });
  ```

  Save the file. The welcome view is no longer needed by this route.

- [X] T007 [US1] Add a custom `messages()` method to `app/Http/Requests/Client/OnboardingRequest.php` to override the duplicate-email error message.

  Open `app/Http/Requests/Client/OnboardingRequest.php`. Find the closing `}` of the `rules()` method. Add the following new method immediately after `rules()` closes, before the class closes:

  ```php
      public function messages(): array
      {
          return [
              'email.unique' => __('client.email_already_exists'),
          ];
      }
  ```

  Do NOT modify the existing `authorize()` or `rules()` methods.

- [X] T008 [US1] Add the `email_already_exists` lang key to `resources/lang/en/client.php`.

  Open `resources/lang/en/client.php`. Find the `'have_account'` key you added in T002. Insert the following key on the line immediately after it (before `];`):

  ```php
      'email_already_exists' => 'An account with this email already exists.',
  ```

- [X] T009 [P] [US1] Add the `email_already_exists` lang key to `resources/lang/ar/client.php`.

  Open `resources/lang/ar/client.php`. Find the `'have_account'` key you added in T003. Insert the following key immediately after it (before `];`):

  ```php
      'email_already_exists' => 'يوجد حساب مرتبط بهذا البريد الإلكتروني بالفعل.',
  ```

- [X] T010 [US1] Add the "Already have an account? Login" toggle link and duplicate-email highlight to `resources/views/client/onboarding/form.blade.php`.

  Open `resources/views/client/onboarding/form.blade.php`. Find the closing `</div>` that wraps the entire form (the outer `<div x-data="{ step: 1 }" ...>` closes just before `</x-guest-layout>`). The last few lines of the file look like:

  ```blade
          </div>
      </form>
  </div>
  </x-guest-layout>
  ```

  Replace those last four lines with:

  ```blade
          </div>
      </form>

      <p class="mt-6 text-center text-sm">
          <a id="login-toggle-link"
             href="{{ route('login') }}"
             class="{{ $errors->has('email') ? 'font-semibold text-indigo-700 underline' : 'text-gray-600 hover:text-gray-900 underline' }}">
              {{ __('client.have_account') }}
          </a>
      </p>
  </div>
  </x-guest-layout>
  ```

  **What this does**: Adds the "Already have an account? Login" link below the form. When the `email` field has a validation error (which includes the duplicate-email case), the link is rendered bold and indigo-coloured to draw attention. Otherwise it renders as a standard muted text link.

**Checkpoint**: User Story 1 is complete when:
1. `GET /` redirects to `/apply` for guests ✅
2. `GET /apply` shows the 3-step form with "Already have an account? Login" below it ✅
3. Submitting `/apply` with an existing email shows "An account with this email already exists." and the login link turns bold/highlighted ✅

---

## Phase 4: User Story 2 — Returning Client Login Toggle (Priority: P2)

**Goal**: The login page displays a "Don't have an account? Apply now" link below the form.

**Independent Test**: Navigate to `http://localhost:8000/login` — a text link "Don't have an account? Apply now" must be visible below the login button. Clicking it must navigate to `/apply`.

### Implementation for User Story 2

- [X] T011 [US2] Add the "Don't have an account? Apply now" toggle link to `resources/views/auth/login.blade.php`.

  Open `resources/views/auth/login.blade.php`. Find the closing `</form>` tag (currently the last line before `</x-guest-layout>`). The end of the file currently looks like:

  ```blade
          <x-primary-button class="ms-3">
              {{ __('auth.login') }}
          </x-primary-button>
      </div>
  </form>
  </x-guest-layout>
  ```

  Replace `</form>` and `</x-guest-layout>` with:

  ```blade
          <x-primary-button class="ms-3">
              {{ __('auth.login') }}
          </x-primary-button>
      </div>
  </form>

  <p class="mt-6 text-center text-sm text-gray-600">
      <a href="{{ route('onboarding.show') }}" class="underline hover:text-gray-900">
          {{ __('auth.apply_now') }}
      </a>
  </p>
  </x-guest-layout>
  ```

**Checkpoint**: User Story 2 is complete when:
1. `/login` shows the apply form toggle link below the login button ✅
2. Clicking the link navigates to `/apply` ✅
3. `/apply` shows the login toggle link (completed in Phase 3) ✅

---

## Phase 5: User Story 3 — Authenticated User Guard (Priority: P3)

**Status**: ✅ **ALREADY FULLY IMPLEMENTED — NO CODE CHANGES REQUIRED**

The existing `guest` middleware on `/`, `/apply` (added in T006) and `/login` already redirects authenticated users to `/dashboard`. The `DashboardController` then redirects to the role-specific dashboard (admin → `/admin/dashboard`, reviewer → `/reviewer/dashboard`, client → `/client/dashboard`).

**Verification only** (no task needed): Log in as any user, then navigate to `/`, `/apply`, and `/login`. All three should redirect to the appropriate dashboard.

---

## Phase 6: User Story 4 — Admin Auto-Provisioned at Setup (Priority: P4)

**Status**: ✅ **ALREADY FULLY IMPLEMENTED — NO CODE CHANGES REQUIRED**

`database/seeders/AdminUserSeeder.php` creates/updates the admin user from `ADMIN_EMAIL` and `ADMIN_PASSWORD` env vars and assigns the `admin` role. Called by `DatabaseSeeder` on `php artisan migrate:refresh --seed`.

**Verification only**: Run `php artisan migrate:refresh --seed` and confirm admin login works.

---

## Phase 7: Feature Tests

**Purpose**: Automated regression coverage for all three gaps, as required by the project constitution (Principle XII).

- [X] T012 Create `tests/Feature/Auth/ApplicationEntryTest.php` with the following complete content:

  ```php
  <?php

  namespace Tests\Feature\Auth;

  use App\Models\User;
  use App\Models\VisaType;
  use Illuminate\Foundation\Testing\RefreshDatabase;
  use Tests\TestCase;

  class ApplicationEntryTest extends TestCase
  {
      use RefreshDatabase;

      protected function setUp(): void
      {
          parent::setUp();
          $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
      }

      // -----------------------------------------------------------------------
      // US1: Root route redirects to apply form
      // -----------------------------------------------------------------------

      public function test_root_url_redirects_guest_to_apply_form(): void
      {
          $response = $this->get('/');

          $response->assertRedirect(route('onboarding.show'));
      }

      public function test_root_url_redirects_authenticated_user_to_dashboard(): void
      {
          $user = User::factory()->create();
          $user->assignRole('client');

          $response = $this->actingAs($user)->get('/');

          $response->assertRedirect(route('dashboard'));
      }

      public function test_apply_route_renders_onboarding_form(): void
      {
          VisaType::factory()->create(['name' => 'Test Visa', 'is_active' => true]);

          $response = $this->get(route('onboarding.show'));

          $response->assertOk();
          $response->assertViewIs('client.onboarding.form');
      }

      // -----------------------------------------------------------------------
      // US1: Duplicate email shows specific error and login link is present
      // -----------------------------------------------------------------------

      public function test_apply_form_shows_specific_error_for_duplicate_email(): void
      {
          User::factory()->create(['email' => 'existing@example.com']);
          $visaType = VisaType::factory()->create(['is_active' => true]);

          $response = $this->post(route('onboarding.store'), [
              'full_name'              => 'Test User',
              'email'                  => 'existing@example.com',
              'password'               => 'Password1',
              'password_confirmation'  => 'Password1',
              'phone'                  => '0501234567',
              'nationality'            => 'Saudi',
              'country_of_residence'   => 'Saudi Arabia',
              'visa_type_id'           => $visaType->id,
              'adults_count'           => 1,
              'children_count'         => 0,
              'application_start_date' => now()->addDays(10)->toDateString(),
              'job_title'              => 'Engineer',
              'employment_type'        => 'employed',
              'monthly_income'         => 5000,
              'agreed_to_terms'        => '1',
          ]);

          $response->assertSessionHasErrors(['email']);

          $errors = session('errors');
          $this->assertStringContainsString(
              'An account with this email already exists.',
              $errors->first('email')
          );
      }

      public function test_apply_form_login_toggle_link_is_rendered(): void
      {
          VisaType::factory()->create(['name' => 'Test Visa', 'is_active' => true]);

          $response = $this->get(route('onboarding.show'));

          $response->assertSee(route('login'));
      }

      // -----------------------------------------------------------------------
      // US2: Login page has apply toggle link
      // -----------------------------------------------------------------------

      public function test_login_page_shows_apply_now_toggle_link(): void
      {
          $response = $this->get(route('login'));

          $response->assertOk();
          $response->assertSee(route('onboarding.show'));
      }

      // -----------------------------------------------------------------------
      // US3: Authenticated users cannot access entry pages
      // -----------------------------------------------------------------------

      public function test_authenticated_user_cannot_access_login_page(): void
      {
          $user = User::factory()->create();
          $user->assignRole('client');

          $response = $this->actingAs($user)->get(route('login'));

          $response->assertRedirect(route('dashboard'));
      }

      public function test_authenticated_user_cannot_access_apply_page(): void
      {
          $user = User::factory()->create();
          $user->assignRole('client');

          $response = $this->actingAs($user)->get(route('onboarding.show'));

          $response->assertRedirect(route('dashboard'));
      }
  }
  ```

- [X] T013 Run `php artisan test --filter=ApplicationEntryTest` and confirm all tests pass. If any fail, fix the implementation (not the tests) until they pass.

- [X] T014 Run the full test suite with `php artisan test` and confirm no regressions. All previously passing tests must still pass.

---

## Phase 8: Polish & Verification

- [X] T015 [P] Delete `resources/views/welcome.blade.php` if it exists and is no longer referenced anywhere in the codebase. Check first with `grep -r "welcome" routes/ resources/views/` — only delete if the only reference was the route you removed in T006.

- [ ] T016 Follow the manual verification steps in `specs/008-auth-application-entry/quickstart.md` to validate all user flows end-to-end in the browser.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Phase 1 (Setup)**: No dependencies — start immediately
- **Phase 2 (Foundational — Lang Keys)**: Depends on Phase 1 green baseline
- **Phase 3 (US1 — Root Route + Apply Form)**: Depends on Phase 2 (T002, T003 must be done before T010)
- **Phase 4 (US2 — Login Toggle)**: Depends on Phase 2 (T004, T005 must be done before T011); can run in parallel with Phase 3
- **Phase 5 (US3)**: No code tasks — already implemented
- **Phase 6 (US4)**: No code tasks — already implemented
- **Phase 7 (Tests)**: Depends on Phases 3 and 4 being complete
- **Phase 8 (Polish)**: Depends on Phase 7

### User Story Dependencies

- **US1 (P1)**: Depends on Phase 2 (T002, T003 lang keys)
- **US2 (P2)**: Depends on Phase 2 (T004, T005 lang keys); independent from US1
- **US3 (P3)**: No code tasks — fully satisfied by T006 (root route guest middleware)
- **US4 (P4)**: No code tasks — fully pre-implemented

### Parallel Opportunities

- T002, T003, T004, T005 — all different files, all parallelizable (after T001)
- T003 and T005 (Arabic files) — can run alongside T002 and T004 respectively
- T008 and T009 (email_already_exists lang keys) — can run in parallel with each other
- T006, T007 — can run in parallel (different files)
- T011 — can run in parallel with T006–T010 (different file)

---

## Parallel Example: Phase 2 + Phase 3 + Phase 4

```
After T001 (baseline confirmed):

Parallel batch A (lang keys):
  T002: resources/lang/en/client.php  — have_account
  T003: resources/lang/ar/client.php  — have_account
  T004: resources/lang/en/auth.php    — apply_now
  T005: resources/lang/ar/auth.php    — apply_now

After A completes, parallel batch B:
  T006: routes/web.php              — root route redirect
  T007: OnboardingRequest.php       — messages() method
  T008: resources/lang/en/client.php — email_already_exists
  T009: resources/lang/ar/client.php — email_already_exists
  T011: login.blade.php             — apply now toggle link

After T007, T008, T009 complete:
  T010: form.blade.php              — login toggle + highlight (depends on T008/T009 for keys)

After all implementation:
  T012: ApplicationEntryTest.php    — write tests
  T013: run filter test
  T014: run full suite
  T015: cleanup welcome view
  T016: manual verification
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1 (T001) — confirm baseline
2. Complete Phase 2, T002 + T003 only — `have_account` and `email_already_exists` lang keys
3. Complete Phase 3 (T006–T010) — root route + apply form changes
4. **STOP and VALIDATE**: Visit `/` and `/apply`, test duplicate email flow
5. Continue to Phase 4 when US1 is working

### Incremental Delivery

1. Phase 1 + Phase 2 → lang foundation ready
2. Phase 3 → US1 independently testable (MVP)
3. Phase 4 → US2 independently testable
4. Phase 7 → full test coverage

---

## Notes

- **Do NOT touch**: `OnboardingService.php`, `AuthService.php`, `LoginRequest.php`, `AdminUserSeeder.php`, `RolePermissionSeeder.php` — all working correctly
- **File to modify** (existing): `routes/web.php`, `OnboardingRequest.php`, `login.blade.php`, `form.blade.php`, four lang files
- **File to create** (new): `tests/Feature/Auth/ApplicationEntryTest.php`
- [P] tasks operate on different files — safe to run simultaneously
- Each user story is independently testable after its phase completes
- Commit after each phase, not after each individual task
- If `VisaType::factory()` does not exist, use `VisaType::create(['name' => 'Test', 'is_active' => true, ...])` in the test setup instead
