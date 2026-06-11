# Product Design Audit - 2026-06-07

Historical note: this audit reflects the interface state on 2026-06-07. Several findings have since been addressed, including dashboard dead links, material search, material edit, material enable/disable, warehouse master, inventory transactions, BOM, purchase order, work order, shortage analysis, and user management. See `docs/system-design-review-2026-06-12.md` for the current implementation review.

## Audit Scope

- Product: Factory ERP MVP for Chinese small and mid-sized manufacturing companies.
- Flow reviewed: login -> dashboard -> material master, plus a mobile check on material master.
- Evidence folder: `docs/product-design-audit-2026-06-07/`
- Screenshots:
  - `01-login.png`
  - `02-dashboard.png`
  - `03-materials.png`
  - `04-materials-mobile.png`

## User Goal

A factory operator or manager should be able to enter the system, understand today's production and material work, and complete basic master-data and inventory preparation tasks without needing a developer or terminal.

## Step Review

### 1. Login

Health: mostly clear, but the promise is ahead of the implementation.

Strengths:
- The page is simple and focused.
- The Chinese copy is understandable for the target audience.
- The positioning mentions the right manufacturing loop: sales order, BOM, kit check, purchasing, work orders, issue, and inventory traceability.

Risks:
- The copy promises an end-to-end business loop, but the current system only exposes dashboard and material master. This increases the user's sense that nothing can be done after login.
- There is no visible helper text for first-time admin setup, demo mode, or environment state.

Recommendations:
- Change the early-stage login copy to say that the MVP currently supports material master and foundation setup, while inventory and BOM are being added.
- Add a small environment label such as "Development / Pilot environment" for early pilots.

### 2. Dashboard

Health: readable, but currently behaves more like a placeholder than a workbench.

Strengths:
- The four metric cards map to real manufacturing concerns: shortage alert, purchase suggestions, issue-ready work orders, and finished-goods receipt.
- Quick entries expose the intended product direction.

Risks:
- Several quick links are `#` links. They look clickable but do nothing, which is the strongest reason a user will feel the system is incomplete.
- Metrics all show zero without explaining whether that means no data, module not enabled, or not calculated yet.
- The sidebar includes a login-page link when already logged in. That weakens navigation trust.

Recommendations:
- Mark unavailable modules as "in development" or disable them visually until implemented.
- Add an empty-state explanation under the metric cards: maintain materials and warehouses first; inventory metrics will be calculated after inventory transactions are available.
- Replace the logged-in login-page navigation item with system settings, or remove it.
- Make the next recommended action prominent, such as adding a material or maintaining a warehouse.

### 3. Material Master

Health: functionally useful as the first real module, but still too thin for factory use.

Strengths:
- The main task is obvious: add a material, then view it in the list.
- Field names match common Chinese ERP vocabulary.
- Desktop layout is simple and easy to scan.

Risks:
- Inputs are not marked as required in the rendered DOM, so users do not know which fields are mandatory before submitting.
- There is no edit, disable, search, filter, import, or export action.
- There is no relation to warehouse, stock, BOM use, supplier, customer, or item category beyond a broad material type.
- The table becomes cramped on mobile. The screenshot has no page-level horizontal overflow, but rows wrap awkwardly and will become hard to read once real material names and specs are longer.

Recommendations:
- Add required indicators and inline validation messages next to fields.
- Add search and filter before edit/delete; factory master data grows quickly.
- Add actions per row: edit, disable/enable, view stock.
- Add import/export as early roadmap items because Chinese SME users often start from Excel.
- On mobile, use a card list or a deliberately scrollable table with clear affordance instead of letting cells wrap into narrow columns.

### 4. Mobile Material Page

Health: usable for a quick check, not yet good for daily mobile operation.

Strengths:
- Navigation stacks correctly.
- Form fields fit the viewport and remain tappable.
- There is no full-page horizontal overflow in the tested 375px viewport.

Risks:
- Table cells wrap into multiple lines, making records hard to compare.
- The sidebar consumes the first part of the page on mobile, pushing the work area down.

Recommendations:
- Keep mobile as secondary for MVP, but make list reading robust with record cards.
- Collapse navigation into a compact top bar once there are more modules.

## Accessibility Notes

Confirmed strengths:
- The pages use real text, native form controls, and good basic contrast.
- The main headings are readable and the form labels are visible.

Likely issues:
- Required fields are not communicated before submit.
- Placeholder text is doing too much work as examples.
- Links that do nothing are not announced as disabled.
- Table reading order on mobile may be confusing for assistive technology once rows wrap heavily.

Verification gaps:
- This audit did not complete a keyboard-only pass.
- This audit did not run automated WCAG checks.
- This audit did not test screen reader announcements.

## Priority Recommendations

P0 - Stop dead ends:
- Remove or visually disable `#` links.
- Explain which modules are implemented now and which are still in development.

P1 - Make the system feel usable:
- Add warehouse master next.
- Add inventory transactions immediately after warehouse master.
- Change dashboard metrics to explain data source and empty state.

P1 - Strengthen material master:
- Add required markers and inline validation.
- Add search/filter.
- Add row actions: edit, disable/enable, stock view.

P2 - Fit Chinese factory workflows:
- Add Excel import/export for material and warehouse master.
- Add print/export-friendly list pages.
- Add audit log visibility for master-data changes.

P2 - Improve mobile:
- Use card-style record lists for mobile screens with many columns.
- Convert sidebar to compact top navigation on small screens.

## Product Direction Verdict

The current implementation still matches the original goal, but only as the foundation of the MVP. It has the right first module and a sensible workbench direction. The main mismatch is expectation management: the UI currently presents future ERP workflows as if they already exist.

The next development order should be:

1. Warehouse master.
2. Inventory inbound/outbound/adjustment.
3. Dashboard metrics wired to real inventory data.
4. Material search, edit, enable/disable, and Excel import/export.
5. BOM after inventory has a usable material and warehouse base.
