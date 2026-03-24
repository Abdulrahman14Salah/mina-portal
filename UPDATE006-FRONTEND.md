Here's the phased frontend spec based on the codebase review:
                                                                                          
  ---                             
  Frontend Improvement Spec — portal-sass                                                                                   
                                                                                          
  Stack: Laravel 11 + Blade SSR + Alpine.js v3 + Tailwind CSS 3 — no framework migration needed                             
                                                                                                                            
  ---                                                                                                                       
  Phase 1 — Foundation & Consistency (High Impact, Low Risk)                                                                
                                                                                                                            
  Goal: Eliminate visual inconsistencies that create a fragmented UX.                                                       
                                                                                                                            
  1.1 Standardize Form Components                                                                                           
                                                                                                                            
  - Extract missing primitives: x-select, x-checkbox, x-file-input, x-textarea                                              
  - Audit all forms — replace raw <input>, <select>, <textarea> with components
  - Targets: client/onboarding/form.blade.php, reviewer/applications/show.blade.php, all admin user forms                   
                                                                                                                            
  1.2 Button Standardization                                                                                                
                                                                                                                            
  - Audit all buttons — replace raw <button class="..."> with x-primary-button, x-secondary-button, x-danger-button         
  - Add disabled + loading spinner state to all submit buttons via Alpine.js (@submit → set loading = true, disable button)
                                                                                                                            
  1.3 Status Badge Component
                                                                                                                            
  - Extract x-status-badge component — currently inlined in 5+ files with identical logic                                   
  - Props: status string → maps to color + label
                                                                                                                            
  ---             
  Phase 2 — Accessibility & Semantics (Compliance Risk)                                                                     
                                                                                                                            
  Goal: Fix critical accessibility gaps.
                                                                                                                            
  2.1 Form Label Associations                                                                                               
  
  - Audit all <input> elements — ensure every one has a matching <label for="..."> or aria-label                            
  - Fix in: auth views, onboarding form, admin user forms, reviewer forms
                                                                                                                            
  2.2 Modal Focus Management
                                                                                                                            
  - components/modal.blade.php — add x-trap (Alpine Focus plugin) to trap focus inside modal when open, restore on close    
  
  2.3 Navigation Skip Links                                                                                                 
                  
  - Add <a href="#main-content">Skip to content</a> as first element in layouts/app.blade.php                               
  - Add id="main-content" to the main content area
                                                                                                                            
  2.4 ARIA on Interactive Components                                                                                        
                                                                                                                            
  - components/dropdown.blade.php — add aria-expanded, aria-haspopup                                                        
  - components/client/nav.blade.php — add role="tablist", role="tab", aria-selected
  - components/admin/table.blade.php — add aria-sort to sortable column headers                                             
                                                                                                                            
  ---                                                                                                                       
  Phase 3 — UX Polish & Feedback States                                                                                     
                                                                                                                            
  Goal: Give users confidence during interactions.
                                                                                                                            
  3.1 Loading States on All Forms                                                                                           
  
  - Onboarding multi-step form — disable Next/Submit while processing                                                       
  - Task forms (_question-form.blade.php, _payment-form.blade.php) — spinner on submit
  - Reviewer approval/rejection forms — prevent double-submit                                                               
                                                                                                                            
  3.2 Empty States                                                                                                          
                                                                                                                            
  - components/admin/table.blade.php — improve "No records" to include icon + contextual message                            
  - Client tasks tab — add illustration/message when no tasks exist
  - Reviewer dashboard — empty state when queue is clear                                                                    
                                                                                                                            
  3.3 File Upload UX
                                                                                                                            
  - _payment-form.blade.php — replace raw <input type="file"> with drag-and-drop zone component                             
  - Show filename + size after selection
  - Preview current receipt inline                                                                                          
                                                                                                                            
  3.4 Onboarding Wizard Progress                                                                                            
                                                                                                                            
  - Add visual step indicator (step dots or numbered bar) above the 3-step form                                             
  - Currently step state is hidden in Alpine x-data — surface it visually
                                                                                                                            
  ---             
  Phase 4 — Mobile Responsiveness                                                                                           
                                 
  Goal: Make all panels usable on mobile.
                                                                                                                            
  4.1 Admin Table Mobile Layout                                                                                             
                                                                                                                            
  - components/admin/table.blade.php — add card-based stacked layout below sm: breakpoint                                   
  - Priority columns remain visible; secondary columns collapse under expand toggle
                                                                                                                            
  4.2 Client Tab Navigation
                                                                                                                            
  - components/client/nav.blade.php — convert horizontal tab row to horizontally scrollable tab strip on mobile, prevent tab
   wrapping
                                                                                                                            
  4.3 Reviewer Application Detail

  - reviewer/applications/show.blade.php — long form stacks awkwardly on mobile; restructure sections into accordion or     
  stacked cards
                                                                                                                            
  ---             
  Phase 5 — Component Documentation & Dev Experience
                                                                                                                            
  Goal: Prevent regression and enable faster onboarding.
                                                                                                                            
  5.1 Component Catalog View (Admin-only)                                                                                   
  
  - Add /admin/ui route that renders all components with all states side-by-side (poor-man's Storybook)                     
  - Covers: buttons, badges, inputs, modals, table, cards
                                                                                                                            
  5.2 Consistent Translation Key Coverage
                                                                                                                            
  - Audit __() calls — ensure all hardcoded English strings use translation keys                                            
  - Create lang/en/ui.php for shared UI strings (buttons, empty states, loading messages)
                                                                                                                            
  ---             
  Priority Order                                                                                                            
                  
  ┌───────────────────┬────────┬────────┬─────────────────────┐
  │       Phase       │ Effort │ Impact │        Start        │                                                             
  ├───────────────────┼────────┼────────┼─────────────────────┤
  │ 1 — Consistency   │ Medium │ High   │ Now                 │                                                             
  ├───────────────────┼────────┼────────┼─────────────────────┤
  │ 2 — Accessibility │ Low    │ High   │ After Phase 1       │                                                             
  ├───────────────────┼────────┼────────┼─────────────────────┤
  │ 3 — UX Polish     │ Medium │ Medium │ After Phase 2       │                                                             
  ├───────────────────┼────────┼────────┼─────────────────────┤                                                             
  │ 4 — Mobile        │ Medium │ Medium │ Parallel w/ Phase 3 │
  ├───────────────────┼────────┼────────┼─────────────────────┤                                                             
  │ 5 — Dev XP        │ Low    │ Low    │ Last                │
  └───────────────────┴────────┴────────┴─────────────────────┘          