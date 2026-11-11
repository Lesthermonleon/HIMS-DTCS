# DTCS HIMS — Pharmacy Point of Sale (POS) UI Style Alignment & Integration Report

**Repository:** `Lesthermonleon/DTCS`  
**Target Module:** Pharmacy Management System (PMS) — Point of Sale (POS)  
**Primary Account:** Pharmacist (`pharmacist` role)  
**Reference Document:** `docs/pos.html` and User Reference POS Screenshot  
**Date of Completion:** November 9, 2026  

---

## 1. Executive Summary & Audit Findings

The objective was to align the Pharmacy Point of Sale (`/pharmacy/pos`) interface with the existing DTCS system-wide design language, integrating the layout, features, and interactions demonstrated in `docs/pos.html` and the user-provided reference POS screenshot into the Pharmacist account.

### Key Audit Observations
1. **Visual Style Discrepancies:**
   - The standalone `docs/pos.html` prototype featured a generic gray monochrome interface and blue accents that did not incorporate DTCS branding tokens (deep teal identity, deep blue accents, and slate typography).
   - The prototype lacked DTCS dark-mode support and violated DTCS dark-theme guidelines (which require deep charcoal/black surfaces such as `#111827`, `#1f2937`, `#141d2e` and prohibit solid green background panels).
2. **Architecture & Subsystem Boundaries:**
   - The prototype was a standalone client-side script without backend Laravel bindings.
   - In DTCS, patient master records belong to an external hospital subsystem (read-only master boundary). Patient selection in POS must associate transactions without mutating master records.
   - Financial POS checkout must remain strictly segregated from clinical prescription dispensing (dispensing requires pharmacist verification and clinical fulfillment).
3. **Modal & Toolbar Interactions:**
   - The interface required four specialized interactive dialogs: Edit Order Item Modal (`#editItemModal`), Statutory Discount Modal (`#discountModal`), Patient Type Modal (`#patientTypeModal`), and Payment Modal (`#paymentModal`), alongside an action toolbar featuring utility shortcuts.

---

## 2. DTCS Design System Token Alignment

The POS workspace (`.dtcs-pos-workspace`) implements scoped CSS variables aligned with DTCS design tokens across light and dark modes:

| Element / Token | Light Mode Value | Dark Mode Value (`data-theme="dark"`) | Clinical Design Rationale |
| :--- | :--- | :--- | :--- |
| **Primary Brand Accent** | `#0f766e` (Deep Teal) | `#14b8a6` (Teal 400) | Hospital clinical teal identity |
| **Primary Hover / Active** | `#115e59` | `#2dd4bf` | High-contrast interactive feedback |
| **Secondary Accent** | `#0284c7` (Deep Blue) | `#38bdf8` (Sky Blue) | Secondary actions & buttons |
| **Surface (Workspace Shell)** | `#f8fafc` (Slate 50) | `#090d16` (Deep Charcoal Black) | Consistent DTCS background |
| **Surface (Cards & Panels)** | `#ffffff` (Pure White) | `#111827` (Slate 900) | Glare-free clinical panels |
| **Surface (Soft Containers)** | `#f8fafc` | `#1f2937` (Slate 800) | Secondary card wells & modals |
| **Borders & Dividers** | `#e2e8f0` (Slate 200) | `#374151` (Slate 700) | Clean definition without harsh black lines |
| **Primary Typography** | `#0f172a` (Slate 900) | `#f9fafb` (Slate 50) | High-contrast clinical legibility |
| **Secondary Typography** | `#334155` (Slate 700) | `#e5e7eb` (Slate 200) | Sub-headings, tables, and notes |
| **Muted Metadata** | `#64748b` (Slate 500) | `#9ca3af` (Slate 400) | Barcodes, timestamps, units |
| **Stock Status: In Stock** | `#15803d` / `#f0fdf4` | `#4ade80` / `rgba(22, 163, 74, 0.16)` | Muted green badge (NO solid green backgrounds) |
| **Stock Status: Low Stock** | `#b45309` / `#fffbeb` | `#fbbf24` / `rgba(217, 119, 6, 0.16)` | Amber warning pill |
| **Stock Status: Out of Stock** | `#dc2626` / `#fef2f2` | `#f87171` / `rgba(220, 38, 38, 0.16)` | Crimson critical alert |

---

## 3. Layout Structure & UI Components

The POS interface follows a 3-column responsive layout (`minmax(0, 1.85fr) minmax(320px, 0.95fr) 66px`):

### 1. Catalog Panel (Left)
- **Top Category Navigation:** Horizontal scrollable pill buttons (`All Items`, `Default`, `Best Seller`, `Others / Special`, `Vitamins & Supplements`, `Gastro / Stomach`, `Allergy`, `Cough & Cold`) with active teal indicator.
- **Search & Barcode Input:** Real-time dual filtering supporting medicine generic names, brand names, category tags, and barcode matching (with automatic cart entry on exact barcode match).
- **Product Card Grid:** 4-column responsive grid displaying medicine name, strength/dosage form pill, price (₱), available stock badge, and out-of-stock overlay disabling click interactions.

### 2. Order Summary Panel (Center-Right)
- **Header:** Live item count badge and patient type indicator.
- **Itemized Cart List:** Scrollable list showing product name, quantity, unit price, and line subtotal. Clicking the edit icon (`bi-pencil`) triggers the Edit Order Item modal. Clicking the delete icon removes the item.
- **Calculations Breakdown:**
  - Subtotal (VAT-inclusive base)
  - VAT breakdown (reflects 12% standard or ₱0.00 VAT-exempt for senior/PWD)
  - Statutory / Government Discount display with dynamic percentage tag
  - Grand Total in large typography
- **Proceed to Payment Button:** Highlights active balance; opens the Payment Modal when items exist.

### 3. Action Toolbar (Far-Right)
A vertical utility toolbar featuring distinct icons and text labels:
- **Discount (`%`):** Opens Government / Statutory Discount modal.
- **Patient Type (`bi-hospital`):** Opens Outpatient / Inpatient selection and registry picker modal.
- **Clear (`bi-trash3`):** Clears the active cart with confirmation.
- **Orders (`bi-receipt`):** Opens recent completed and voided transactions overview modal.
- **Shift (`bi-clock-history`):** Opens real-time cashier shift analytics (completed sales, void counts, discounts granted, net sales).
- **Printer (`bi-printer`):** Triggers receipt print preview.
- **Settings (`bi-gear`):** Displays terminal identifier, store code, and tax configuration.
- **Refresh (`bi-arrow-clockwise`):** Reloads POS catalog state.

---

## 4. Modal Dialogs Architecture

### 1. Edit Order Item Modal (`#editItemModal`)
- **Product Name:** Strictly `readonly` input displaying the base medication name.
- **Available Strength / Dosage Selector:** Dynamically populated with all catalog dosage variants sharing the same generic/brand family (e.g., *Losec 20mg* vs *Losec 40mg*).
- **Stock Information Banner:** Displays real-time available inventory and unit pricing for the selected variant.
- **Quantity Input:** Strictly clamped between `1` and `available_stock`. Prevents over-selling.
- **Error Display:** Real-time validation warning if the user attempts to enter a quantity exceeding available stock.

### 2. Select Discount Modal (`#discountModal`) — Per Reference Image
- **Header:** Title "Select Discount" with subtitle "This will be applied to all line items" and top-right close button.
- **Two-Column Layout:**
  - **Left Column (Discount Selection):**
    - **Government Discounts:**
      - **Senior:** 5% or 20% off (RA 9994 20% + VAT Exemption or 5% special OTC rate). Rate selector banner `[ 5% ] [ 20% ]` appears dynamically.
      - **PWD:** 5% or 20% off (RA 10754 20% + VAT Exemption or 5% rate).
      - **National Athlete:** 20% off (RA 10699).
      - **Solo Parent:** 10% off (RA 11861).
    - **Custom Discounts:**
      - **Percentage:** `% off item total` with dynamic percentage input.
      - **Amount:** `Fixed peso amount` with dynamic peso value input.
  - **Right Column (Customer Information):**
    - **Customer Name:** Input with placeholder `Full name as on ID`.
    - **Dynamic ID Number:** Changes label and placeholder based on active card (e.g. "Senior Citizen ID Number", "PWD ID Number", "National Athlete ID Number", "Solo Parent ID Number", or "Reference / Reason").
    - **TIN Number:** Optional tax identification input.
    - **Summary Callout:** Real-time feedback explaining statutory VAT exemption and applied rates.
- **Actions:** Full-width high-contrast `Apply Discount` button + subtle `Remove Active Discount` button when active.

### 3. Patient Type Modal (`#patientTypeModal`)
- **Patient Classification:** Dropdown toggle between `Outpatient` (walk-in retail client) and `Inpatient` (admitted hospital patient).
- **Integrated Patient Directory Lookup:** Real-time searchable table linked to the `patients` table (by Patient No, Name, Ward, and Bed).
- **Read-Only Master Preservation:** Selecting an inpatient associates their ID and clinical ward/bed details with the transaction without mutating or modifying the hospital master record.

### 4. Payment Settlement Modal (`#paymentModal`)
- **Payable Summary:** Shows the net amount due after all applied discounts and tax calculations.
- **Payment Method Selection:** `Cash`, `GCash`, `Debit / Credit Card`, or `Charge to Hospital Bill` (strictly for inpatients).
- **Amount Tendered & Change Due:** Automatic recalculation of change due upon tender input. Provides quick-cash shortcut buttons (Exact, ₱100, ₱500, ₱1,000).
- **Submission:** Submits idempotently via POST to `/pharmacy/pos/checkout`.

---

## 5. Subsystem Boundary & RBAC Compliance

1. **Custom RBAC (No Spatie):**
   - Access to `/pharmacy/pos` is guarded by the `pms.pos.access` permission and restricted to the `pharmacist` role.
   - Discount authorizations require the `pms.pos.discount` permission.
   - Voids and refunds require the `pms.pos.refund_void` permission.
2. **Patient Directory Read-Only Isolation:**
   - POS transactions link to `patients.id` as a foreign key for billing and auditing. No patient records are created, edited, or deleted from POS.
3. **Clinical Dispensing Segregation:**
   - Completing a POS transaction marks the financial transaction as `Completed`.
   - It **does NOT** automatically mark an inpatient prescription as `Dispensed`. Dispensing remains a clinical nursing/pharmacy workflow governed by `pms.dispense` and clinical verification.

---

## 6. Verification & Test Results

### Test Suite Execution
All automated feature and regression tests pass without errors:

1. **`PharmacyPmsWorkflowTest` (Feature & Workflow Test):**
   - Command: `php artisan test --filter=PharmacyPmsWorkflowTest`
   - Result: **9 passed, 0 failed, 123 assertions** (Duration: 1.25s)
   - Covered scenarios:
     - Pharmacist POS access and non-pharmacist 403 denial.
     - Outpatient walk-in cash and e-wallet checkout.
     - Patient type selection & inpatient association without master record mutation.
     - Statutory discounts: Senior (20% + VAT exempt), PWD (20% + VAT exempt), Solo Parent (10%).
     - Variant selection (Losec 40mg `MED-007B` vs 20mg `MED-007`) and overselling prevention.
     - Out-of-stock medicine purchase blocking.
     - Prescription-linked checkout and duplicate billing prevention.
     - Transaction voiding and refund audit logging.

2. **`MedicineAvailabilityTest` (Medication Stock Service Test):**
   - Command: `php artisan test --filter=MedicineAvailabilityTest`
   - Result: **8 passed, 0 failed, 38 assertions** (Duration: 0.78s)

3. **`PharmacyDispensingTest` (Clinical Dispensing Test):**
   - Command: `php artisan test --filter=PharmacyDispensingTest`
   - Result: **13 passed, 0 failed, 47 assertions** (Duration: 2.19s)

4. **Frontend Asset Build:**
   - Command: `npm run build`
   - Result: Built in 2.25s with 0 warnings or errors.

---

## 7. Files Modified & Created

| File Path | Action | Description |
| :--- | :--- | :--- |
| `resources/views/pharmacy/pos/index.blade.php` | Modified | Complete POS UI overhaul matching `docs/pos.html` structure with DTCS design tokens, dark mode, action toolbar, redesigned 2-column Discount modal, dedicated Add Product modal (`#addProductModal`), barcode Enter-to-add, and in-cart quantity indicator badges. |
| `app/Http/Controllers/Pharmacy/PosController.php` | Modified | Added statutory discount rates (`solo_parent`, `senior_citizen`, `senior_5`, `pwd_5`, `national_athlete`, `custom_percentage`, `custom_amount`), normalized `patient_type`, variant code stock matching, and patient directory integration. |
| `app/Models/PharmacyPosTransaction.php` | Modified | Added `patient_type` to `$fillable`. |
| `app/Services/Pharmacy/MockMedicationStockProvider.php` | Modified | Added dosage variant `MED-007B` and out-of-stock item `MED-018` to canonical mock catalog. |
| `database/migrations/2026_11_09_000001_add_patient_type_to_pharmacy_pos_transactions.php` | Created | Additive migration ensuring `patient_type` exists on `pharmacy_pos_transactions`. |
| `docs/PMS_POS_UI_ALIGNMENT_REPORT.md` | Modified | Comprehensive design alignment, discount modal reference redesign, and Add Product feature report. |

## 10. Add Product Functionality Implementation

The POS system now provides four complementary methods to add products into the order:
1. **Catalog Card Direct Click & `+` Icon**: Clicking any medicine card or its `+` button in the catalog grid instantly adds 1 unit (or increments the existing line item) up to available stock. The card dynamically updates to display the quantity currently in the order.
2. **Dedicated Add Product Button & Modal (`#addProductModal`)**: A clean `[ + Add Product ]` action button in the catalog panel header opens a modal dialog allowing pharmacists to quickly search/filter all medications, choose a medicine, inspect current stock and unit price, specify quantity with `[-]` and `[+]` steppers, and add directly to the order.
3. **Barcode Scanning & `Enter` Key Search**: Scanning a barcode with a handheld reader or typing a medicine name, code, or barcode into the search toolbar and pressing `Enter` automatically resolves the product and adds it to the order without mouse interaction.
4. **Inline Order Summary Stepper Controls**: Line items in the Order Summary feature inline `[-]` and `[+]` steppers, allowing fast adjustments to billed quantities without needing to open the full edit dialog.

