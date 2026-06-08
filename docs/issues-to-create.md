# Issues To Create

These are concrete GitHub issues suitable for the next development round. The project is early alpha, so each issue should stay small, test-backed, and honest about scope.

## 1. Add Operation Audit Log For Key Actions

Record who performed critical actions such as purchase receipt, inventory adjustment, work order material issue, work order completion, user creation, and user status changes.

Acceptance criteria:

- Add an `audit_logs` table migration.
- Record actor user ID, action, entity type, entity ID or reference number, timestamp, and summary.
- Add tests for at least purchase receipt, inventory adjustment, and user status changes.
- Add a protected audit log page for administrators.

## 2. Generate Purchasing Suggestions From Shortage Analysis

Convert material shortage rows into draft purchasing suggestions so users can move from "what is missing" to "what should be purchased".

Acceptance criteria:

- Add a purchasing suggestion service backed by shortage analysis.
- Show material, shortage quantity, suggested quantity, and source work orders.
- Let an authorized purchasing user convert a suggestion into a purchase order draft.
- Add tests for suggestion generation and conversion.

## 3. Add Excel Import For Materials And Warehouses

Support early adopter migration from spreadsheets into master data.

Acceptance criteria:

- Define CSV or XLSX import format for materials and warehouses.
- Validate required columns and duplicate codes.
- Show row-level validation errors before saving.
- Add tests for valid import, duplicate code rejection, and missing required fields.

## 4. Add Excel Export For Master Data And Inventory Balances

Let users export core operational data for review and offline work.

Acceptance criteria:

- Export materials, warehouses, and stock balances.
- Include Chinese column headers.
- Keep export output deterministic for tests.
- Add tests for headers and representative row content.

## 5. Add Purchase Order Status Workflow

Strengthen purchase order lifecycle beyond simple draft/received behavior.

Acceptance criteria:

- Support draft, approved, partially_received, received, cancelled.
- Restrict receipt to approved or partially received orders.
- Prevent cancelling received orders.
- Add controller and service tests for valid and invalid transitions.

## 6. Add Work Order Status Workflow

Strengthen production work order lifecycle before more planning features are added.

Acceptance criteria:

- Support draft, released, issued, partially_completed, completed, closed, cancelled.
- Restrict material issue and completion based on status.
- Prevent duplicate or invalid transitions.
- Add tests for the main transition paths.

## 7. Add Partial Purchase Receipt

Allow a purchase order to be received in multiple batches instead of one full receipt.

Acceptance criteria:

- Add receipt quantity input.
- Track received quantity per order item.
- Set status to partially_received until all quantity is received.
- Preserve batch number for each receipt.
- Add tests for partial and final receipt.

## 8. Add Work Order Return And Supplement Issue

Support common shop-floor corrections after material issue.

Acceptance criteria:

- Add material return transaction from work order back into inventory.
- Add supplement issue transaction for extra material usage.
- Keep source work order reference numbers.
- Add permission checks for warehouse/admin roles.
- Add tests for stock balance impact.

## 9. Add Basic Work Order Material Cost Summary

Start cost visibility with actual issued material cost.

Acceptance criteria:

- Store or derive material unit cost from purchase order receipt or standard cost.
- Summarize actual material cost by work order.
- Show planned quantity, issued material quantity, and material cost.
- Add tests for cost calculation with multiple materials.

## 10. Add Data-Scope Permission Controls

Extend role checks beyond action permissions to control what data a user can see or operate on.

Acceptance criteria:

- Define an initial warehouse-level data-scope model.
- Allow a user to be assigned to one or more warehouses.
- Restrict inventory transactions and stock balances by assigned warehouse unless the user is admin.
- Add tests for allowed and denied warehouse access.
