<?php

namespace App\Http\Controllers\Diet;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDietRequestRequest;
use App\Models\ActivityLog;
use App\Models\DietRequest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * DietRequestController — doctors request therapeutic diets for patients.
 *
 * Authorization: every action is gated by DietRequestPolicy (ownership + status), not just route middleware.
 */
class DietRequestController extends Controller
{
    private const DIET_TYPES = ['Diabetic', 'Low-Sodium', 'Renal', 'Cardiac', 'High-Protein', 'Low-Fat', 'Liquid', 'Soft', 'Regular'];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DietRequest::class);

        /** @var User $user */
        $user = Auth::user();

        $query = DietRequest::visibleTo($user)->with('patient', 'doctor', 'dietPlan');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                  ->orWhere('diet_type', 'like', "%{$search}%")
                  ->orWhereHas('patient', fn($p) => $p->where('last_name', 'like', "%{$search}%"));
            });
        }

        if ($s = $request->input('status')) { $query->where('status', $s); }

        $dietRequests = $query->latest()->paginate(15)->withQueryString();
        $statuses = DietRequest::STATUSES;

        return view('diet.requests.index', compact('dietRequests', 'statuses'));
    }

    public function create(): View
    {
        $this->authorize('create', DietRequest::class);

        /** @var User $user */
        $user = Auth::user();

        $patients  = Patient::authorizedForDoctor($user)->orderBy('last_name')->get(['id', 'patient_no', 'first_name', 'last_name']);
        $dietTypes = self::DIET_TYPES;

        return view('diet.requests.create', compact('patients', 'dietTypes'));
    }

    public function store(StoreDietRequestRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $patient = Patient::findOrFail($request->validated('patient_id'));
        $this->authorize('create', [DietRequest::class, $patient]);

        $dietRequest = DB::transaction(function () use ($request, $user) {
            return DietRequest::create(array_merge($request->validated(), [
                'request_no'   => $this->nextRequestNo(),
                'doctor_id'    => $user->id,
                'status'       => 'Pending',
                'requested_at' => now(),
            ]));
        });

        $this->log('Diet Request Created', "Diet request [{$dietRequest->request_no}] ({$dietRequest->diet_type}) was submitted.", $dietRequest);

        return redirect()->route('diet.requests.index')
                         ->with('success', 'Diet request submitted successfully.');
    }

    public function show(DietRequest $dietRequest): View
    {
        $this->authorize('view', $dietRequest);

        $dietRequest->load('patient', 'doctor', 'dietPlan.dietitian', 'dietPlan.mealSchedules');

        return view('diet.requests.show', compact('dietRequest'));
    }

    public function edit(DietRequest $dietRequest): View
    {
        $this->authorize('update', $dietRequest);

        /** @var User $user */
        $user = Auth::user();

        $patients  = Patient::authorizedForDoctor($user)->orderBy('last_name')->get(['id', 'patient_no', 'first_name', 'last_name']);
        $dietTypes = self::DIET_TYPES;

        return view('diet.requests.edit', compact('dietRequest', 'patients', 'dietTypes'));
    }

    public function update(StoreDietRequestRequest $request, DietRequest $dietRequest): RedirectResponse
    {
        $this->authorize('update', $dietRequest);

        $patient = Patient::findOrFail($request->validated('patient_id'));
        $this->authorize('create', [DietRequest::class, $patient]);

        $dietRequest->update($request->validated());

        $this->log('Diet Request Updated', "Diet request [{$dietRequest->request_no}] was updated.", $dietRequest);

        return redirect()->route('diet.requests.show', $dietRequest)
                         ->with('success', 'Diet request updated.');
    }

    public function destroy(DietRequest $dietRequest): RedirectResponse
    {
        $this->authorize('delete', $dietRequest);

        $dietRequest->update(['status' => 'Cancelled']);

        $this->log('Diet Request Cancelled', "Diet request [{$dietRequest->request_no}] was cancelled.", $dietRequest);

        return redirect()->route('diet.requests.index')
                         ->with('success', 'Diet request cancelled.');
    }

    /** Collision-safe sequential request number. */
    private function nextRequestNo(): string
    {
        $prefix = 'DR-' . date('Y') . '-';
        $n = DietRequest::count() + 1;
        do {
            $no = $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while (DietRequest::where('request_no', $no)->exists());

        return $no;
    }

    private function log(string $action, string $description, DietRequest $dietRequest): void
    {
        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => $action,
            'module'        => 'Diet & Nutrition',
            'description'   => $description,
            'loggable_type' => DietRequest::class,
            'loggable_id'   => $dietRequest->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);
    }
}
