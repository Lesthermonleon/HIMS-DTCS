<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ClinicalCode;
use App\Models\ClinicalCodeSystem;
use App\Models\ClinicalCodeVersion;
use App\Services\ClinicalCodes\ClinicalCodeImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClinicalCodeController extends Controller
{
    public function __construct(
        protected ClinicalCodeImportService $importService
    ) {}

    /**
     * Display clinical code systems and version catalogue.
     */
    public function index(): View
    {
        $systems = ClinicalCodeSystem::with(['activeVersion', 'licenseVerifiedBy'])->get();
        $versions = ClinicalCodeVersion::with(['system', 'importedBy', 'publishedBy'])
            ->orderBy('id', 'desc')
            ->paginate(15);

        return view('super_admin.clinical_codes.index', compact('systems', 'versions'));
    }

    /**
     * Verify licensing credentials for a clinical code system.
     */
    public function verifyLicense(Request $request, ClinicalCodeSystem $system): RedirectResponse
    {
        $validated = $request->validate([
            'license_status'    => ['required', 'in:verified,unverified'],
            'license_reference' => ['required_if:license_status,verified', 'nullable', 'string', 'max:255'],
            'jurisdiction'      => ['required_if:license_status,verified', 'nullable', 'string', 'max:100'],
            'license_notes'     => ['nullable', 'string', 'max:1000'],
        ]);

        $isVerified = ($validated['license_status'] === 'verified');

        $system->update([
            'license_status'      => $validated['license_status'],
            'license_reference'   => $validated['license_reference'] ?? null,
            'jurisdiction'        => $validated['jurisdiction'] ?? null,
            'license_notes'       => $validated['license_notes'] ?? null,
            'license_verified_by' => $isVerified ? Auth::id() : null,
            'license_verified_at' => $isVerified ? now() : null,
        ]);

        try {
            ActivityLog::create([
                'user_id'     => Auth::id(),
                'action'      => 'Clinical Code Licensing Updated',
                'module'      => ActivityLog::MODULE_CLINICAL_CODES,
                'description' => sprintf(
                    'Licensing status for [%s] set to [%s] by Super Admin. Reference: %s (Jurisdiction: %s).',
                    $system->name,
                    $validated['license_status'],
                    $validated['license_reference'] ?? 'N/A',
                    $validated['jurisdiction'] ?? 'N/A'
                ),
                'severity'    => ActivityLog::SEVERITY_WARNING,
                'result'      => ActivityLog::RESULT_SUCCESS,
                'logged_at'   => now(),
            ]);
        } catch (\Throwable $e) {}

        return back()->with('success', "Licensing credentials for [{$system->system_key}] updated successfully.");
    }

    /**
     * Upload & stage a new dataset version from CSV.
     */
    public function stageVersion(Request $request, ClinicalCodeSystem $system): RedirectResponse
    {
        $request->validate([
            'version_label'  => ['required', 'string', 'max:50'],
            'effective_date' => ['nullable', 'date'],
            'dataset_file'   => ['required', 'file', 'mimes:csv,txt', 'max:10240'], // 10MB max
        ]);

        $file = $request->file('dataset_file');
        $originalFilename = $file->getClientOriginalName();
        $tempPath = $file->getRealPath();

        try {
            $version = $this->importService->validateAndStageCsv(
                $system,
                $tempPath,
                $originalFilename,
                $request->input('version_label'),
                $request->input('effective_date'),
                Auth::id()
            );

            if ($version->invalid_rows > 0 || $version->duplicate_rows > 0) {
                return back()->with('warning', sprintf(
                    'Dataset staged as [%s], but contains %d invalid and %d duplicate row(s). Validation report must be clean before publishing.',
                    $version->version_label,
                    $version->invalid_rows,
                    $version->duplicate_rows
                ));
            }

            return back()->with('success', "Dataset successfully staged as [{$version->version_label}] with {$version->valid_rows} valid codes. Ready for publication.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Dataset staging failed: ' . $e->getMessage());
        }
    }

    /**
     * Publish a staged version.
     */
    public function publish(Request $request, ClinicalCodeVersion $version): RedirectResponse
    {
        try {
            $this->importService->publishVersion($version, Auth::id());
            return back()->with('success', "Version [{$version->version_label}] successfully published as the active catalog for {$version->system->name}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Publication failed: ' . $e->getMessage());
        }
    }

    /**
     * Rollback an active version.
     */
    public function rollback(Request $request, ClinicalCodeVersion $version): RedirectResponse
    {
        $validated = $request->validate([
            'rollback_reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        try {
            $this->importService->rollbackVersion($version, $validated['rollback_reason'], Auth::id());
            return back()->with('success', "Version [{$version->version_label}] rolled back successfully.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Rollback failed: ' . $e->getMessage());
        }
    }
}
