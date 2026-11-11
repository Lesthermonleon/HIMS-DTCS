<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CatalogChangeHistory;
use App\Models\CatalogItemState;
use App\Models\CatalogService;
use App\Models\LabTest;
use App\Models\LabTestCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /**
     * Display master catalog interface.
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'services'); // 'services' or 'lab_tests'

        // Master Clinical Services
        $serviceQuery = CatalogService::query()->with(['createdBy', 'updatedBy']);
        if ($request->filled('module')) {
            $serviceQuery->where('module', $request->query('module'));
        }
        if ($request->filled('status')) {
            $serviceQuery->where('status', $request->query('status'));
        }
        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $serviceQuery->where(fn($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
        }
        $services = $serviceQuery->orderBy('code')->paginate(15, ['*'], 'services_page');

        // Master LIS Diagnostic Tests (from existing lab_tests table + catalog_item_states lifecycle)
        $labTestQuery = LabTest::with(['category']);
        if ($request->filled('lab_search')) {
            $term = '%' . $request->query('lab_search') . '%';
            $labTestQuery->where(fn($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
        }
        $labTests = $labTestQuery->orderBy('code')->paginate(15, ['*'], 'tests_page');

        // Attach lifecycle state from catalog_item_states side table
        $labTestIds = $labTests->pluck('id')->all();
        $sideStates = CatalogItemState::where('entity_type', 'lab_test')
            ->whereIn('entity_id', $labTestIds)
            ->get()
            ->keyBy('entity_id');

        $categories = LabTestCategory::orderBy('name')->get();
        $recentHistories = CatalogChangeHistory::with('changedBy')
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();

        return view('super_admin.catalog.index', compact(
            'tab',
            'services',
            'labTests',
            'sideStates',
            'categories',
            'recentHistories'
        ));
    }

    /**
     * Store a new clinical service definition.
     */
    public function storeService(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code'           => ['required', 'string', 'max:30', 'unique:catalog_services,code', 'regex:/^[A-Z0-9\-_]+$/i'],
            'name'           => ['required', 'string', 'max:255'],
            'module'         => ['required', 'in:LIS,RIS,PMS,SORS,DNMS'],
            'category'       => ['nullable', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'base_price'     => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'effective_from' => ['nullable', 'date'],
            'effective_to'   => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        $service = DB::transaction(function () use ($validated) {
            $svc = CatalogService::create([
                'code'           => strtoupper($validated['code']),
                'name'           => $validated['name'],
                'module'         => $validated['module'],
                'category'       => $validated['category'] ?? null,
                'description'    => $validated['description'] ?? null,
                'base_price'     => $validated['base_price'] ?? null,
                'status'         => 'active',
                'effective_from' => $validated['effective_from'] ?? now()->toDateString(),
                'effective_to'   => $validated['effective_to'] ?? null,
                'version'        => 1,
                'created_by'     => Auth::id(),
                'updated_by'     => Auth::id(),
            ]);

            CatalogChangeHistory::create([
                'entity_type'    => 'service',
                'entity_id'      => $svc->id,
                'version'        => 1,
                'action'         => 'created',
                'after_snapshot' => $svc->toArray(),
                'reason'         => 'Initial service registration in master catalog',
                'changed_by'     => Auth::id(),
            ]);

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Catalog Service Created',
                'module'      => ActivityLog::MODULE_MASTER_CATALOG,
                'description' => sprintf('Master service definition [%s: %s] created for module %s.', $svc->code, $svc->name, $svc->module),
                'severity'    => ActivityLog::SEVERITY_INFO,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);

            return $svc;
        });

        return redirect()->route('super-admin.catalog.index', ['tab' => 'services'])
            ->with('success', "Service [{$service->code}] successfully added to catalog.");
    }

    /**
     * Update an existing clinical service definition.
     */
    public function updateService(Request $request, CatalogService $service): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'category'       => ['nullable', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'base_price'     => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'effective_from' => ['nullable', 'date'],
            'effective_to'   => ['nullable', 'date', 'after_or_equal:effective_from'],
            'reason'         => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($service->status === 'retired') {
            return back()->with('error', 'Retired catalog services cannot be edited.');
        }

        DB::transaction(function () use ($service, $validated) {
            $before = $service->toArray();
            $newVersion = $service->version + 1;

            $service->update([
                'name'           => $validated['name'],
                'category'       => $validated['category'] ?? null,
                'description'    => $validated['description'] ?? null,
                'base_price'     => $validated['base_price'] ?? null,
                'effective_from' => $validated['effective_from'] ?? $service->effective_from,
                'effective_to'   => $validated['effective_to'] ?? $service->effective_to,
                'version'        => $newVersion,
                'updated_by'     => Auth::id(),
            ]);

            CatalogChangeHistory::create([
                'entity_type'     => 'service',
                'entity_id'       => $service->id,
                'version'         => $newVersion,
                'action'          => 'updated',
                'before_snapshot' => $before,
                'after_snapshot'  => $service->fresh()->toArray(),
                'reason'          => $validated['reason'],
                'changed_by'      => Auth::id(),
            ]);

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Catalog Service Updated',
                'module'      => ActivityLog::MODULE_MASTER_CATALOG,
                'description' => sprintf('Master service definition [%s] updated to version %d. Reason: %s', $service->code, $newVersion, $validated['reason']),
                'severity'    => ActivityLog::SEVERITY_INFO,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);
        });

        return redirect()->route('super-admin.catalog.index', ['tab' => 'services'])
            ->with('success', "Service [{$service->code}] successfully updated.");
    }

    /**
     * Change status of a clinical service (activate, deactivate, retire).
     */
    public function updateServiceStatus(Request $request, CatalogService $service): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive,retired'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        if ($service->status === 'retired') {
            return back()->with('error', 'Retired catalog entries are terminal and cannot be reactivated.');
        }

        DB::transaction(function () use ($service, $validated) {
            $before = $service->toArray();
            $newVersion = $service->version + 1;

            $service->update([
                'status'     => $validated['status'],
                'version'    => $newVersion,
                'updated_by' => Auth::id(),
            ]);

            CatalogChangeHistory::create([
                'entity_type'     => 'service',
                'entity_id'       => $service->id,
                'version'         => $newVersion,
                'action'          => $validated['status'],
                'before_snapshot' => $before,
                'after_snapshot'  => $service->fresh()->toArray(),
                'reason'          => $validated['reason'],
                'changed_by'      => Auth::id(),
            ]);

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Catalog Service Status Changed',
                'module'      => ActivityLog::MODULE_MASTER_CATALOG,
                'description' => sprintf('Master service [%s] status changed to [%s]. Reason: %s', $service->code, $validated['status'], $validated['reason']),
                'severity'    => ActivityLog::SEVERITY_INFO,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);
        });

        return redirect()->route('super-admin.catalog.index', ['tab' => 'services'])
            ->with('success', "Service [{$service->code}] status updated to {$validated['status']}.");
    }

    /**
     * Change status or update lifecycle metadata for an existing LIS lab_tests entry.
     */
    public function updateLabTestLifecycle(Request $request, LabTest $labTest): RedirectResponse
    {
        $validated = $request->validate([
            'status'     => ['required', 'in:active,inactive,retired'],
            'base_price' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'reason'     => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $state = CatalogItemState::firstOrCreate(
            ['entity_type' => 'lab_test', 'entity_id' => $labTest->id],
            ['status' => $labTest->is_active ? 'active' : 'inactive', 'version' => 1]
        );

        if ($state->status === 'retired') {
            return back()->with('error', 'Retired diagnostic tests cannot be reactivated.');
        }

        DB::transaction(function () use ($labTest, $state, $validated) {
            $before = [
                'is_active'  => $labTest->is_active,
                'price'      => $labTest->price,
                'side_state' => $state->toArray(),
            ];

            $newVersion = $state->version + 1;

            // Mirror is_active on live lab_tests table
            $newIsActive = ($validated['status'] === 'active');
            $updateData = ['is_active' => $newIsActive];
            if (isset($validated['base_price'])) {
                $updateData['price'] = $validated['base_price'];
            }
            $labTest->update($updateData);

            $state->update([
                'status'     => $validated['status'],
                'version'    => $newVersion,
                'retired_at' => ($validated['status'] === 'retired') ? now() : null,
                'updated_by' => Auth::id(),
            ]);

            CatalogChangeHistory::create([
                'entity_type'     => 'lab_test',
                'entity_id'       => $labTest->id,
                'version'         => $newVersion,
                'action'          => $validated['status'],
                'before_snapshot' => $before,
                'after_snapshot'  => ['lab_test' => $labTest->fresh()->toArray(), 'state' => $state->fresh()->toArray()],
                'reason'          => $validated['reason'],
                'changed_by'      => Auth::id(),
            ]);

            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Diagnostic Test Lifecycle Updated',
                'module'      => ActivityLog::MODULE_MASTER_CATALOG,
                'description' => sprintf('Lab diagnostic test [%s: %s] updated to [%s]. Reason: %s', $labTest->code, $labTest->name, $validated['status'], $validated['reason']),
                'severity'    => ActivityLog::SEVERITY_INFO,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);
        });

        return back()->with('success', "Diagnostic test [{$labTest->code}] updated successfully.");
    }

    /**
     * Display Clinical Services configuration.
     * Manages outpatient consultation types, clinical encounter rules, and department assignments.
     */
    public function clinicalServices(Request $request): View
    {
        $consultationTypes = [
            ['code' => 'CLIN-CONS-01', 'name' => 'In-Person General Consultation', 'type' => 'In-Person Consultation', 'department' => 'General Medicine', 'default_duration' => 30, 'base_fee' => 500.00, 'status' => 'active'],
            ['code' => 'CLIN-FLUP-02', 'name' => 'Outpatient Follow-up Visit',     'type' => 'Follow-up Visit',         'department' => 'General Medicine', 'default_duration' => 20, 'base_fee' => 350.00, 'status' => 'active'],
            ['code' => 'CLIN-DIAG-03', 'name' => 'Post-Diagnostic Clinical Review',  'type' => 'Diagnostic Review',       'department' => 'General Medicine', 'default_duration' => 25, 'base_fee' => 400.00, 'status' => 'active'],
            ['code' => 'CLIN-SPEC-04', 'name' => 'Specialist Clinical Assessment',  'type' => 'Specialist Assessment',   'department' => 'General Medicine', 'default_duration' => 45, 'base_fee' => 800.00, 'status' => 'active'],
            ['code' => 'CLIN-EMRG-05', 'name' => 'Emergency Triage Consultation',   'type' => 'Emergency Consultation',  'department' => 'Emergency Department', 'default_duration' => 15, 'base_fee' => 650.00, 'status' => 'active'],
        ];

        $clinicalDepts = collect([
            ['name' => 'General Medicine', 'code' => 'GEN-MED', 'type' => 'Clinical'],
            ['name' => 'Nutrition & Dietetics', 'code' => 'DNMS-NUT', 'type' => 'Clinical'],
            ['name' => 'Emergency Department', 'code' => 'EMR-ED', 'type' => 'Emergency'],
        ]);

        return view('super_admin.services.clinical', compact('consultationTypes', 'clinicalDepts'));
    }

    /**
     * Display Diagnostic Services configuration.
     * Manages LIS Laboratory test catalog and RIS Radiology imaging procedures.
     */
    public function diagnosticServices(Request $request): View
    {
        $tab = $request->query('tab', 'laboratory'); // 'laboratory' or 'radiology'

        // LIS Tests
        $labTestQuery = LabTest::with(['category']);
        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $labTestQuery->where(fn($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
        }
        $labTests = $labTestQuery->orderBy('code')->paginate(15, ['*'], 'lab_page');
        $categories = LabTestCategory::orderBy('name')->get();

        // Attach lifecycle state from catalog_item_states
        $labTestIds = $labTests->pluck('id')->all();
        $sideStates = CatalogItemState::where('entity_type', 'lab_test')
            ->whereIn('entity_id', $labTestIds)
            ->get()
            ->keyBy('entity_id');

        // RIS Modalities & Live Request Volume
        $risModalities = [
            'X-Ray' => [
                'name'        => 'General Radiography (X-Ray)',
                'code'        => 'RAD-XR',
                'description' => 'Plain diagnostic radiography for chest, skeletal, abdominal, and extremity examinations',
                'turnaround'  => '2–4 Hours',
                'count'       => \App\Models\RadiologyRequest::where('modality', 'X-Ray')->count(),
            ],
            'CT Scan' => [
                'name'        => 'Computed Tomography (CT Scan)',
                'code'        => 'RAD-CT',
                'description' => 'Cross-sectional axial imaging of head, neck, chest, abdomen, and pelvis with contrast protocols',
                'turnaround'  => '4–8 Hours',
                'count'       => \App\Models\RadiologyRequest::where('modality', 'CT Scan')->count(),
            ],
            'MRI' => [
                'name'        => 'Magnetic Resonance Imaging (MRI)',
                'code'        => 'RAD-MRI',
                'description' => 'High-resolution multi-planar neuro, musculoskeletal, spinal, and soft-tissue diagnostic imaging',
                'turnaround'  => '12–24 Hours',
                'count'       => \App\Models\RadiologyRequest::where('modality', 'MRI')->count(),
            ],
            'Ultrasound' => [
                'name'        => 'Diagnostic Ultrasonography',
                'code'        => 'RAD-US',
                'description' => 'Non-invasive sonographic evaluations of abdomen, pelvis, vascular, thyroid, and obstetric targets',
                'turnaround'  => '2–4 Hours',
                'count'       => \App\Models\RadiologyRequest::where('modality', 'Ultrasound')->count(),
            ],
            'Mammography' => [
                'name'        => 'Diagnostic & Screening Mammography',
                'code'        => 'RAD-MAM',
                'description' => 'Specialized low-dose breast tissue radiography and surveillance imaging',
                'turnaround'  => '24 Hours',
                'count'       => \App\Models\RadiologyRequest::where('modality', 'Mammography')->count(),
            ],
            'Fluoroscopy' => [
                'name'        => 'Fluoroscopic Real-Time Imaging',
                'code'        => 'RAD-FL',
                'description' => 'Real-time dynamic fluoroscopy for barium swallows, gastrointestinal, and arthrographic studies',
                'turnaround'  => 'Same Day',
                'count'       => \App\Models\RadiologyRequest::where('modality', 'Fluoroscopy')->count(),
            ],
        ];

        // RIS Imaging Services from Catalog (if any exist)
        $risQuery = CatalogService::where('module', 'RIS')->with(['createdBy', 'updatedBy']);
        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $risQuery->where(fn($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
        }
        $risServices = $risQuery->orderBy('code')->paginate(15, ['*'], 'ris_page');

        return view('super_admin.services.diagnostic', compact('tab', 'labTests', 'categories', 'sideStates', 'risModalities', 'risServices'));
    }

    /**
     * Display Treatment Services configuration.
     * Manages Pharmacy dispensing services (PMS), Surgical/OR procedures (SORS), and Dietary care regimens (DNMS).
     */
    public function treatmentServices(Request $request): View
    {
        $tab = $request->query('tab', 'surgery'); // 'surgery', 'pharmacy', 'dietary'

        $moduleMap = [
            'surgery' => 'SORS',
            'pharmacy' => 'PMS',
            'dietary' => 'DNMS',
        ];
        $activeModule = $moduleMap[$tab] ?? 'SORS';

        $treatmentQuery = CatalogService::where('module', $activeModule)->with(['createdBy', 'updatedBy']);
        if ($request->filled('search')) {
            $term = '%' . $request->query('search') . '%';
            $treatmentQuery->where(fn($q) => $q->where('code', 'like', $term)->orWhere('name', 'like', $term));
        }
        $services = $treatmentQuery->orderBy('code')->paginate(15, ['*'], 'treatment_page');
        $operatingRooms = \App\Models\OperatingRoom::orderBy('name')->get();

        return view('super_admin.services.treatment', compact('tab', 'services', 'operatingRooms', 'activeModule'));
    }
}
