# DTCS HIMS — Pharmacy Management System (PMS) Requirements & Codebase Audit

**Repository:** `Lesthermonleon/DTCS`  
**Target Module:** Pharmacy Management System (`PMS`)  
**Primary Role:** Pharmacist (`pharmacist`)  
**Audit Date:** 2026-11-08  

---

## 1. Existing Features and Actual Behavior

### 1.1 Pharmacist Role & Permissions
- **Role Definition:** `roles.slug = 'pharmacist'` (`dashboard_route = 'pharmacy.dashboard'`).
- **Existing Permissions (`permissions` table):**
  - `pms.prescriptions.verify` (`Verify Prescription`)
  - `pms.dispense` (`Dispense Medication`)
  - `clinical.prescribe` (`Prescribe Medication`, bound to `doctor`)
- **Middleware Enforcement:**
  - `app/Http/Middleware/RoleMiddleware.php` (`role:...`)
  - `app/Http/Middleware/PermissionMiddleware.php` (`permission:...`)
  - `PATCH /pharmacy/prescriptions/{prescription}/verify` enforces `['role:pharmacist', 'permission:pms.prescriptions.verify']`.
  - `Route::resource('dispensing', ...)` enforces `role:admin,pharmacist` and `permission:pms.dispense` on write methods (`create`, `store`, `edit`, `update`, `destroy`).

### 1.2 Pharmacy Dashboard (`/pharmacy/dashboard`)
- **Controller:** `App\Http\Controllers\DashboardController::pharmacy()`
- **View:** `resources/views/dashboard/pharmacy.blade.php`
- **Current Behavior:**
  - Displays 5 operational KPI cards (`Pending Prescriptions`, `Prescriptions for Verification`, `Pending Dispensing`, `Dispensed Today`, `Completed Prescriptions`).
  - Displays `Prescription Verification & Dispensing Queue` table.
  - Displays `Pharmacy Stock Directory` section with 4 stock cards (`Total Monitored`, `In Stock`, `Low Stock`, `Attention Required`) sourced from `MedicationStockProviderInterface::getAllMedicationStock()`.
  - Displays `Prescription Pipeline` doughnut chart, `Today's Operational Summary`, `Prescription Trend` (6M/12M line chart), `Medication Dispensing Trends` (7-day bar chart), and 3 compact queue panels (`Pending Verification`, `Awaiting Dispensing`, `Recent Dispensing Activities`).
- **Gaps Identified on Dashboard:**
  - Does not yet calculate or display explicit scorecards/counts for `Out-of-Stock Medicines`, `Near-Expiry Medicines`, `Expired Medicines`, or `Sales Today` (from POS transactions).
  - Does not yet display daily/weekly/monthly sales trend charts, payment method distribution, recent pharmacy POS transactions, or explicit alert banners for out-of-stock, low-stock, near-expiry, expired medicines, and pending verification/dispensing with date filtering.

### 1.3 Prescription Management (`/pharmacy/prescriptions`)
- **Controller:** `App\Http\Controllers\Pharmacy\PrescriptionController`
- **Views:** `resources/views/pharmacy/prescriptions/{index,show,create,edit,print}.blade.php`
- **Current Behavior:**
  - Doctors (`role:doctor`) create, edit (when `Pending`), and cancel (when `Pending`) prescriptions.
  - Pharmacists (`role:pharmacist`) view the prescription queue, view prescription details, and verify `Pending` prescriptions (`PATCH /pharmacy/prescriptions/{prescription}/verify`).
  - `PrescriptionController::index()` lists all prescriptions (or scopes to `doctor_id` when accessed by a Doctor).
- **Gaps Identified in Prescription Management:**
  - Does not support inpatient-focused filtering or displaying patient admission context (`patient_type`, `ward`, `bed_number`).
  - Does not record `verification_notes` when a Pharmacist verifies a prescription.
  - Does not support rejecting or returning a prescription (`Rejected` / `Returned` status or `rejection_reason` with required reason, `rejected_by`, `rejected_at`).
  - `PrescriptionController::show()` does not enforce patient/doctor authorization when a `doctor` views another doctor's prescription by direct ID (`IDOR` gap for `doctor` role).
  - In `DispensingController::store()`, when a Pharmacist dispenses a partial quantity (`quantity_dispensed < item->quantity`) for a single `PrescriptionItem`, line 285 unconditionally marks `$item->update(['status' => 'Dispensed'])` instead of tracking remaining item quantity (`quantity - sum(dispensed)`), preventing subsequent partial dispensings of the remaining units on the same line item.

### 1.4 Medication Dispensing (`/pharmacy/dispensing`)
- **Controller:** `App\Http\Controllers\Pharmacy\DispensingController`
- **Views:** `resources/views/pharmacy/dispensing/{index,create,show,print}.blade.php`
- **Current Behavior:**
  - Queries `MedicationStockProviderInterface` to validate stock availability, sufficient quantity, lot number, and future expiry date before recording `DispensingRecord`.
  - Records `DispensingRecord` (`prescription_item_id`, `pharmacist_id`, `quantity_dispensed`, `lot_number`, `expiry_date`, `notes`, `dispensed_at`) and writes an `ActivityLog` entry.
  - Updates parent `Prescription` status to `Partially Dispensed` or `Dispensed`.
  - Enforces immutability (`edit`, `update`, `destroy` abort with `403`).
- **Gaps Identified in Dispensing:**
  - `DispensingController::store()` line 204 currently allows `in_array($prescription->status, ['Verified', 'Partially Dispensed', 'Pending'])` — allowing dispensing of an unverified `Pending` prescription if submitted directly to `store()`. Clinical workflow requires a prescription to be `Verified` or `Partially Dispensed` before dispensing!
  - Partial dispensing at the item level needs to validate `requestedQty <= remainingAuthorizedQty` (`item->quantity - item->dispensingRecords()->sum('quantity_dispensed')`) and only transition the item's `status` to `Dispensed` when total dispensed quantity equals `$item->quantity`.

### 1.5 Medicine Availability (`/pharmacy/medicines`)
- **Controller:** `App\Http\Controllers\Pharmacy\MedicineAvailabilityController`
- **View:** `resources/views/pharmacy/medicines/index.blade.php`
- **Current Behavior:**
  - Read-only stock catalog sourced from `MedicationStockProviderInterface::getAllMedicationStock()`.
  - Supports search by medicine name or lot number, stock filter (`all`, `in_stock`, `low`, `out`), and expiry filter (`all`, `unexpired`, `expiring_soon`, `expired`).

---

## 2. Missing or Incomplete Features

1. **Point of Sale (POS) Module (`/pharmacy/pos`):**
   - No POS controller, routes, models, database tables, or views currently exist in the repository.
   - Needs full integration into the existing `pharmacist` account matching the POS reference layout (Top Header with store/terminal ID, Category Navigation tabs, Search & Barcode input, Responsive Product Grid with stock/low-stock/out-of-stock indicators & quantity selectors, Order Summary Panel with server-side Subtotal/VAT/Discount/Grand Total calculation, and vertical POS Utility Actions toolbar).
   - Must support both **Prescription-linked sales** (linking to `prescriptions.id` and `patients.id` without changing prescription/dispensing status) and **Walk-in / OTC sales**, plus receipt printing, transaction history, discount application with permission checks, and authorized **Refund / Void** workflow with audit logging.

2. **Pharmacy Reports Expansion (`/reports/pharmacy/*`):**
   - Currently only has 3 basic pages (`activity`, `dispensing`, `pending`).
   - Missing comprehensive **Pharmacy Reports** tabs/views for:
     - **Financial Summary** (Gross sales, discounts, refunds/voids, net sales, VAT collected, payment method breakdown)
     - **Sales Reports** (Daily, weekly, monthly sales, sales by medicine, sales by pharmacist, transaction history)
     - **Inventory Reports** (Available stock, low-stock, out-of-stock, near-expiry, expired medicines, stock movement / dispensing history)
     - **Discount Reports** (Discounted transactions, discount types, discount amounts, authorized pharmacist)
     - **Additional Reports** (Prescription Dispensing Reports, Partial Dispensing Reports, Refund & Void Reports, Pharmacist Activity Reports, Medicine Expiry Reports)

3. **Inventory Section (`/pharmacy/inventory/*`):**
   - Currently only has `/pharmacy/medicines` (`Medicine Availability`).
   - Missing structured Pharmacist Inventory views consistent with the separate Inventory / Supply Chain architecture:
     - `Inventory Items` (with medicine details, generic/brand name, strength, dosage form, unit, unit price, available quantity, reorder level, expiration date, stock status, and filters for Available, Low Stock, Out-of-Stock, Near-Expiry, Expired, and Stock Movement History)
     - `Purchase Orders` (read-only / boundary-aware view clearly identifying external Supply Chain ownership)
     - `Inventory Counts` (read-only / boundary-aware physical count & variance view)
     - `Stock Adjustments` (read-only / boundary-aware adjustment log view)

4. **Low-Stock Replenishment Requests — Static Demonstration (`/pharmacy/inventory/replenishment`):**
   - Missing static demonstration interface for Pharmacists to select low-stock/out-of-stock medicines, specify requested quantities, department, and remarks, preview the request, and simulate statuses (`Draft`, `Pending (Demo)`, `Submitted (Demo)`, `Acknowledged (Demo)`, `Rejected (Demo)`) with explicit `Demo / Not Transmitted` labeling and zero external transmission or stock mutation.

5. **Pharmacist Sidebar Organization (`resources/views/partials/_sidebar.blade.php`):**
   - Currently displays a flat list (`Dashboard`, `Prescriptions`, `Dispensing`, `Medicine Availability`, `Pharmacy Reports`).
   - Needs reorganization into the required 4-section hierarchy (`MAIN`, `PHARMACY OPERATIONS`, `INVENTORY`, `REPORTS`).

---

## 3. Existing Database Tables and Relationships

### 3.1 PMS Tables
- `prescriptions`:
  - Columns: `id`, `prescription_no`, `patient_id`, `doctor_id`, `status` (`ENUM('Pending','Verified','Partially Dispensed','Dispensed','Cancelled')`), `notes`, `diagnosis`, `prescribed_at`, `verified_by`, `verified_at`, `created_at`, `updated_at`.
  - Relationships: `belongsTo(Patient)`, `belongsTo(User, 'doctor_id')`, `belongsTo(User, 'verified_by')`, `hasMany(PrescriptionItem)`.
- `prescription_items`:
  - Columns: `id`, `prescription_id`, `medication_name`, `dosage`, `route`, `frequency`, `duration`, `quantity`, `instructions`, `status` (`ENUM('Pending','Dispensed')`), `created_at`, `updated_at`.
  - Relationships: `belongsTo(Prescription)`, `hasMany(DispensingRecord)`.
- `dispensing_records`:
  - Columns: `id`, `prescription_item_id`, `pharmacist_id`, `quantity_dispensed`, `lot_number`, `expiry_date`, `notes`, `dispensed_at`, `created_at`, `updated_at`.
  - Relationships: `belongsTo(PrescriptionItem)`, `belongsTo(User, 'pharmacist_id')`.

### 3.2 Financial & Catalog Tables
- `financial_rules`:
  - Columns: `id`, `currency_code`, `currency_symbol`, `tax_name`, `tax_rate`, `service_charge_rate`, `applies_to`, `notes`, `effective_from`, `created_by`, `created_at`.
  - Used with `App\Services\Finance\MoneyMath` for decimal-safe tax and total calculation.
- `invoices`:
  - General outpatient appointment/billing ledger (`patient_id` NOT NULL, `appointment_id`). Not designed for multi-item pharmacy POS cart line items or walk-in OTC sales where `patient_id` may be null.
- `catalog_services`:
  - Master service definitions (`module` enum includes `'PMS'`).

---

## 4. Current Stock Data Source and Ownership

- **Interface Contract:** `App\Services\Pharmacy\Contracts\MedicationStockProviderInterface` bound in `AppServiceProvider` to `App\Services\Pharmacy\MockMedicationStockProvider` via `config/pharmacy.php` (`stock_provider => env('PHARMACY_STOCK_PROVIDER', 'mock')`).
- **DTO:** `App\Services\Pharmacy\DTOs\MedicationStockData` holds `medicationName`, `availableQuantity`, `lotNumber`, `expiryDate`, `isAvailable`, `errorMessage`, plus optional catalog metadata we can enrich backward-compatibly (such as `code`, `genericName`, `brandName`, `category`, `strength`, `dosageForm`, `unit`, `unitPrice`, `reorderLevel`) with default parameter values so all existing callers and tests (`new MedicationStockData(...)`) continue working unchanged.
- **Ownership Boundary:** The Inventory / Supply Chain subsystem is an external subsystem in the DTCS architecture. PMS consumes stock data read-only via `MedicationStockProviderInterface` and records its own clinical dispensing events (`dispensing_records`) and pharmacy POS sales without claiming live write ownership of the external Supply Chain database.

---

## 5. Current POS and Payment Workflow

- Currently **non-existent** in PMS.
- `FinancialRule` and `MoneyMath` exist in the codebase to provide hospital-configured tax rates (e.g., 12% VAT when configured or standard VAT calculation) and currency formatting (`₱` / `PHP`).
- We will introduce an additive, auditable Pharmacy POS transaction schema (`pharmacy_pos_transactions` and `pharmacy_pos_transaction_items`) that:
  - Links optionally to `prescriptions` (`prescription_id`) and `patients` (`patient_id`) for prescription-linked sales, or supports walk-in/OTC sales.
  - Stores item-level pricing, quantity, discount, VAT, subtotal, and grand total calculated strictly on the server.
  - Tracks payment status (`Paid`, `Voided`, `Refunded`), payment method (`Cash`, `GCash / E-Wallet`, `Card`, `Hospital Charge / Inpatient Billing`), amount tendered, change due, payment reference, and idempotency token to prevent duplicate submissions.
  - Explicitly decouples payment completion from `Prescription` status and `DispensingRecord` creation (paying for a prescription in POS never marks it as dispensed).

---

## 6. Existing Authorization and Permission Gaps

1. **Granular PMS Permissions:**
   - Currently only `pms.prescriptions.verify` and `pms.dispense` exist.
   - We should add granular permissions (`pms.pos.access`, `pms.pos.discount`, `pms.pos.refund_void`, `pms.inventory.view`, `pms.replenishment.demo`, `pms.reports.view`) and bind them to `pharmacist` (and `pms.inventory.view`, `pms.reports.view` to `admin` for read-only oversight), while ensuring `admin`, `super_admin`, and `doctor` do **not** receive `pms.dispense`, `pms.prescriptions.verify`, or `pms.pos.*` cashier execution permissions.
2. **Prescription Viewing Authorization (`PrescriptionController::show` & `print`):**
   - Currently `PrescriptionController::show()` and `print()` do not verify that a logged-in `doctor` is authorized for the prescription's patient or is the prescribing doctor. We must enforce doctor authorization on `show()` and `print()`.
3. **Dispensing Status Validation (`DispensingController::store`):**
   - Currently allows `Pending` prescriptions to be dispensed if posted directly to `store()` unless blocked. We must ensure only `Verified` or `Partially Dispensed` prescriptions can be dispensed (while keeping backward compatibility with all existing tests in `PharmacyDispensingTest`, which use `createVerifiedPrescription()`).
4. **Admin Dispensing Route Middleware:**
   - In `routes/web.php`, `Route::resource('dispensing', DispensingController::class)` has `middleware('role:admin,pharmacist')` and `middlewareFor(['create', 'store', ...], 'permission:pms.dispense')`. Since `admin` does not have `pms.dispense` permission and `DispensingController::create/store` also checks `abort_if(! $user?->hasRole('pharmacist'), 403)`, Admin is already blocked from dispensing and only has read-only access to `index`/`show`/`print`.

---

## 7. Recommended Implementation Plan

1. **Minimal Additive Migration (`database/migrations/2026_11_08_000001_pms_pos_and_workflow_additive.php`):**
   - Add nullable workflow columns to `prescriptions`:
     - `verification_notes` (`TEXT NULL`)
     - `rejection_reason` (`TEXT NULL`)
     - `rejected_by` (`BIGINT UNSIGNED NULL`)
     - `rejected_at` (`TIMESTAMP NULL`)
     - Expand `prescriptions.status` enum on MySQL (if applicable) or support `'Rejected'` / `'Returned'` alongside `'Pending'`, `'Verified'`, `'Partially Dispensed'`, `'Dispensed'`, `'Cancelled'`.
   - Create `pharmacy_pos_transactions` table:
     - `id`, `transaction_no` (unique), `sale_type` (`prescription`, `walk_in`), `prescription_id` (nullable FK), `patient_id` (nullable FK), `customer_name` (nullable), `pharmacist_id` (FK to `users`), `terminal_id`, `subtotal`, `discount_type` (nullable: `none`, `senior_citizen`, `pwd`, `employee`, `promotional`), `discount_rate`, `discount_amount`, `discount_authorized_by` (nullable FK), `vatable_sales`, `vat_exempt_sales`, `vat_amount`, `grand_total`, `payment_method`, `amount_tendered`, `change_amount`, `payment_reference`, `status` (`Completed`, `Voided`, `Refunded`), `voided_by`, `voided_at`, `void_reason`, `idempotency_key` (unique nullable), `notes`, timestamps.
   - Create `pharmacy_pos_transaction_items` table:
     - `id`, `pharmacy_pos_transaction_id` (FK), `prescription_item_id` (nullable FK), `medicine_code`, `medication_name`, `generic_name`, `brand_name`, `category`, `strength`, `dosage_form`, `unit`, `lot_number`, `expiry_date`, `quantity`, `unit_price`, `line_subtotal`, timestamps.
   - Create `pharmacy_replenishment_requests` table (explicitly for **Static Demonstration Only**):
     - `id`, `request_no` (unique), `pharmacist_id` (FK), `requesting_department`, `items_json` (JSON array of selected low/out-of-stock medicines, available qty, reorder level, requested qty), `total_items`, `total_units_requested`, `remarks`, `demo_status` (`Draft`, `Pending (Demo)`, `Submitted (Demo)`, `Acknowledged (Demo)`, `Rejected (Demo)`), `is_demo` (`TINYINT(1) DEFAULT 1`), `submitted_at`, timestamps.
   - Seed granular PMS permissions and bind them to `pharmacist` (and read-only report/inventory view permissions to `admin`).

2. **Enrich `MedicationStockData` and `MockMedicationStockProvider` (Backward-Compatible):**
   - Add optional metadata properties with default values (`code`, `genericName`, `brandName`, `category`, `strength`, `dosageForm`, `unit`, `unitPrice`, `reorderLevel`, `barcode`) to `MedicationStockData`.
   - Populate rich, realistic medication attributes across the 12 catalog medicines in `MockMedicationStockProvider::getAllMedicationStock()` covering categories (`Best Seller`, `Vitamins & Supplements`, `Gastro / Stomach`, `Allergy`, `Cough & Cold`, `Others / Special`, `Default`) while keeping the exact 12 medicines, quantities, lot numbers, and expiry dates asserted by `MedicineAvailabilityTest`!

3. **Enhance Pharmacy Dashboard (`/pharmacy/dashboard`):**
   - Update `DashboardController::pharmacy()` and `resources/views/dashboard/pharmacy.blade.php` to include all required scorecards (`Total Medicines`, `Available Stock`, `Low Stock Medicines`, `Out-of-Stock Medicines`, `Near-Expiry Medicines`, `Expired Medicines`, `Pending Prescriptions`, `Pending Verification`, `Pending Dispensing`, `Dispensed Today`, `Sales Today`), date filtering, sales & dispensing analytics, stock & expiry distribution charts, payment method distribution, recent POS transactions, recent dispensing activities, and clinical/stock alert banners while preserving all existing strings tested by `MedicineAvailabilityTest` and `RoleDashboardsAnalyticsTest`.

4. **Enhance Prescription Management (`/pharmacy/prescriptions`):**
   - Add inpatient prescription queue tabs (`All`, `Pending Verification`, `Verified`, `Pending Dispensing`, `Partially Dispensed`, `Completed Dispensing`, `Rejected / Returned`), inpatient admission badges (`Ward`, `Bed`), verification notes, rejection/return workflow with required reason (`PATCH /pharmacy/prescriptions/{prescription}/reject`), and item-level partial dispensing tracking (`remaining quantity = prescribed - already dispensed`).

5. **Implement Pharmacist Point of Sale (`/pharmacy/pos`):**
   - Build `App\Http\Controllers\Pharmacy\PosController` and `resources/views/pharmacy/pos/index.blade.php` + `receipt.blade.php` matching the attached POS reference UI (Top Header, Category Navigation, Search/Barcode input, Product Grid with stock badges & quantity controls, Order Summary Panel with server-validated VAT/Discounts/Totals, and vertical Utility Toolbar for `Discount`, `Orders`, `Shift`, `Clear Cart`, `Printer`, `Settings`, `Refresh`).
   - Support prescription-linked sales (without changing prescription/dispensing status), walk-in OTC sales, receipt generation, and refund/void with reason and `ActivityLog` audit trail.

6. **Implement Comprehensive Pharmacy Reports (`/reports/pharmacy`):**
   - Expand `ReportController` and `ReportService` with comprehensive Pharmacy Reports (`Financial Summary`, `Sales Reports`, `Inventory Reports`, `Discount Reports`, `Prescription & Partial Dispensing Reports`, `Refund & Void Reports`, `Pharmacist Activity Reports`, `Medicine Expiry Reports`) with date-range filtering, search/sorting, print support, and role-based access control.

7. **Implement Inventory Section & Static Demo Low-Stock Replenishment Requests (`/pharmacy/inventory/*`):**
   - Build `App\Http\Controllers\Pharmacy\InventoryController` and `App\Http\Controllers\Pharmacy\ReplenishmentRequestController` providing:
     - `Inventory Items` (`/pharmacy/inventory/items` + stock status sub-views: Available, Low Stock, Out-of-Stock, Near-Expiry, Expired, Stock Movement History)
     - `Purchase Orders` (`/pharmacy/inventory/purchase-orders` — clearly labeled external Supply Chain boundary view)
     - `Inventory Counts` (`/pharmacy/inventory/counts` — clearly labeled external Supply Chain boundary view)
     - `Stock Adjustments` (`/pharmacy/inventory/adjustments` — clearly labeled external Supply Chain boundary view)
     - `Stock Replenishment Requests` (`/pharmacy/inventory/replenishment` — clearly labeled **STATIC / DEMONSTRATION ONLY — Demo / Not Transmitted** with `Draft`, `Pending (Demo)`, `Submitted (Demo)`, `Acknowledged (Demo)`, `Rejected (Demo)` statuses).

8. **Update Pharmacist Sidebar (`resources/views/partials/_sidebar.blade.php`):**
   - Update the `pharmacist` sidebar to match the exact 4-group structure (`MAIN`, `PHARMACY OPERATIONS`, `INVENTORY`, `REPORTS`).

---

## 8. Potential Conflicts with Other DTCS Subsystems & Mitigation

1. **Inventory / Supply Chain Subsystem Boundary:**
   - *Risk:* Creating writable stock tables in PMS would conflict with the external Inventory / Supply Chain subsystem.
   - *Mitigation:* Continue using `MedicationStockProviderInterface` as the single source of stock truth. Label Purchase Orders, Inventory Counts, and Stock Adjustments clearly with their subsystem ownership boundary, and label Low-Stock Replenishment Requests explicitly as **Static Demonstration Only (`Demo / Not Transmitted`)** with zero external API calls or stock mutations.
2. **Existing Test Suite Compatibility:**
   - *Risk:* Modifying `DashboardController::pharmacy()`, `MedicineAvailabilityController`, `PrescriptionController`, `DispensingController`, or `_sidebar.blade.php` could break existing assertions in `MedicineAvailabilityTest`, `PharmacyDispensingTest`, `RoleDashboardsAnalyticsTest`, `RequirementsAlignmentTest`, or `DoctorSidebarTest`.
   - *Mitigation:* Preserve all existing route names, middleware contracts, mock stock quantities/lots/expiries, and dashboard text labels while layering the new features cleanly.

