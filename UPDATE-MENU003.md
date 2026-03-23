Menu Unification Specification

Executive Summary

This specification defines the refactoring of the current role-based menu system into a unified navigation structure. Currently, the application maintains separate menus for Admin, Reviewer, and Client roles. The goal is to merge these into a single, consistent main menu while preserving role-based visibility and access control.

This change improves:
	•	UI consistency across roles
	•	Maintainability of navigation logic
	•	Scalability for future features

⸻

Phase 1 — Menu Architecture Foundation

Goal

Create a single source of truth for all menu items.

Specs
	•	Create MenuService (app/Services/MenuService.php)
	•	Define all menu items in one structured array
	•	Remove duplicated menu definitions from Blade views

Rules
	•	No menu definitions inside Blade
	•	No role checks inside Blade

⸻

Phase 2 — Menu Data Structure

Goal

Standardize menu item format.

Specs

Each menu item must include:
	•	label
	•	route
	•	roles (array)

Example:

[
    'label' => 'Applications',
    'route' => 'admin.applications.index',
    'roles' => ['admin']
]

Rules
	•	Roles must be explicit
	•	Support shared items (multiple roles)

⸻

Phase 3 — Role-Based Filtering Logic

Goal

Display menu items dynamically based on user role.

Specs
	•	Implement filtering inside MenuService
	•	Return only allowed items for current user

Example:

public function getMenuForUser(User $user)
{
    return collect($this->menu)
        ->filter(fn($item) => in_array($user->role, $item['roles']));
}

Rules
	•	Do NOT filter in Blade
	•	Do NOT hardcode roles in views

⸻

Phase 4 — Dashboard Integration

Goal

Unify dashboard entry across roles.

Specs
	•	Add dashboard item in menu
	•	Resolve route dynamically per role

Example:
	•	admin → admin.dashboard
	•	reviewer → reviewer.dashboard
	•	client → client.dashboard

⸻

Phase 5 — Blade Integration

Goal

Render unified menu in layout.

Specs
	•	Pass filtered menu from controller or view composer
	•	Loop through menu items in layout

Example:

@foreach($menu as $item)
    <a href="{{ route($item['route']) }}">
        {{ $item['label'] }}
    </a>
@endforeach

Rules
	•	Blade should only render
	•	No logic except display

⸻

Phase 6 — Active State Handling

Goal

Highlight current page in menu.

Specs
	•	Detect current route
	•	Match against menu route
	•	Support nested routes

Rules
	•	Active state handled centrally (helper or service)

⸻

Phase 7 — Authorization Integrity

Goal

Ensure UI does not replace backend security.

Specs
	•	Keep policies and middleware unchanged
	•	Menu visibility is UI only

Rules
	•	Never rely on menu for security

⸻

Phase 8 — Testing

Goal

Validate role-based visibility.

Specs
	•	Admin sees admin items only
	•	Reviewer sees reviewer items only
	•	Client sees client items only
	•	Shared items visible to all

Rules
	•	Unauthorized routes must still be blocked

⸻

Phase 9 — Cleanup & Refactor

Goal

Remove old menu implementations.

Specs
	•	Delete old role-based menu files
	•	Remove duplicated Blade code

Rules
	•	Ensure no broken UI after cleanup

⸻

Future Enhancements
	•	Menu caching
	•	Icons support
	•	Nested menus
	•	Permission-based visibility (instead of role-only)

⸻

Summary

This refactor centralizes navigation logic into a single, maintainable structure while preserving strict role-based access control. It prepares the system for scalability and clean UI architecture.