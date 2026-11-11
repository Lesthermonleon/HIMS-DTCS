<?php

namespace App\Http\Controllers\Diet;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDietPlanRequest;
use App\Models\ActivityLog;
use App\Models\DietPlan;
use App\Models\DietRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * DietPlanController — doctors create, revise, and complete therapeutic diet plans for their authorized patients.
 *
 * Authorization: every action is gated by DietPlanPolicy. Doctors only access and manage plans for their own
 * authorized patients' requests; Admin / Super Admin are read-only observers.
 */
class DietPlanController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DietPlan::class);

        /** @var User $user */
        $user = Auth::user();

        $search = $request->input('search');
        $status = $request->input('status');

        $pendingRequestsQuery = DietRequest::visibleTo($user)
            ->where('status', 'Pending')
            ->whereDoesntHave('dietPlan')
            ->with('patient', 'doctor');

        if ($search) {
            $pendingRequestsQuery->where(function ($q) use ($search) {
                $q->where('request_no', 'like', "%{$search}%")
                  ->orWhere('diet_type', 'like', "%{$search}%")
                  ->orWhereHas('patient', fn ($p) => $p->where('last_name', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('patient_no', 'like', "%{$search}%"));
            });
        }

        $pendingRequests = $pendingRequestsQuery->latest('requested_at')->get();

        $basePlansQuery = DietPlan::visibleTo($user);
        $pendingCount = DietRequest::visibleTo($user)
            ->where('status', 'Pending')
            ->whereDoesntHave('dietPlan')
            ->count();
        $totalPlansCount = (clone $basePlansQuery)->count();

        $tabCounts = [
            'all'       => $pendingCount + $totalPlansCount,
            'pending'   => $pendingCount,
            'Active'    => (clone $basePlansQuery)->where('status', 'Active')->count(),
            'Revised'   => (clone $basePlansQuery)->where('status', 'Revised')->count(),
            'Completed' => (clone $basePlansQuery)->where('status', 'Completed')->count(),
        ];

        $query = DietPlan::visibleTo($user)->with('dietRequest.patient', 'dietRequest.doctor', 'dietitian', 'createdBy');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('plan_details', 'like', "%{$search}%")
                  ->orWhereHas('dietRequest', fn ($r) => $r->where('request_no', 'like', "%{$search}%")
                        ->orWhere('diet_type', 'like', "%{$search}%")
                        ->orWhereHas('patient', fn ($p) => $p->where('last_name', 'like', "%{$search}%")
                            ->orWhere('first_name', 'like', "%{$search}%")
                            ->orWhere('patient_no', 'like', "%{$search}%")));
            });
        }

        if ($status && in_array($status, DietPlan::STATUSES, true)) {
            $query->where('status', $status);
        }

        $plans = $query->latest()->paginate(15)->withQueryString();

        return view('diet.plans.index', compact('plans', 'pendingRequests', 'tabCounts'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorize('create', DietPlan::class);

        /** @var User $user */
        $user = Auth::user();

        $selectedRequest = null;
        $isRequestLocked = false;

        if ($request->filled('diet_request_id')) {
            $preselectedId = $request->input('diet_request_id');
            if (! is_numeric($preselectedId)) {
                abort(404);
            }

            $selectedRequest = DietRequest::with(['patient', 'doctor'])->findOrFail((int) $preselectedId);
            $this->authorize('create', [DietPlan::class, $selectedRequest]);

            if ($selectedRequest->dietPlan()->exists()) {
                return redirect()->route('diet.plans.index')
                    ->with('error', 'A diet plan already exists for this request.')
                    ->withErrors(['diet_request_id' => 'A diet plan already exists for this request.']);
            }

            if ($selectedRequest->status !== 'Pending') {
                return redirect()->route('diet.plans.index')
                    ->with('error', 'Only Pending diet requests can receive a diet plan.')
                    ->withErrors(['diet_request_id' => 'Only Pending diet requests can receive a diet plan.']);
            }

            $isRequestLocked = true;
        }

        $pendingRequests = DietRequest::visibleTo($user)
            ->where('status', 'Pending')
            ->whereDoesntHave('dietPlan')
            ->with(['patient', 'doctor'])
            ->latest('requested_at')
            ->get()
            ->filter(fn (DietRequest $req) => $req->patient !== null && $req->patient->isAuthorizedForDoctor($user))
            ->values();

        if (! $isRequestLocked && old('diet_request_id')) {
            $selectedRequest = $pendingRequests->firstWhere('id', (int) old('diet_request_id'));
        }

        return view('diet.plans.create', compact('pendingRequests', 'selectedRequest', 'isRequestLocked'));
    }

    public function store(StoreDietPlanRequest $request): RedirectResponse
    {
        $this->authorize('create', DietPlan::class);

        $validated = $request->validated();
        $targetRequest = DietRequest::with('patient')->findOrFail($validated['diet_request_id']);
        $this->authorize('create', [DietPlan::class, $targetRequest]);

        try {
            $plan = DB::transaction(function () use ($validated) {
                $dietRequest = DietRequest::with('patient')
                    ->whereKey($validated['diet_request_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorize('create', [DietPlan::class, $dietRequest]);

                if ($dietRequest->status !== 'Pending') {
                    throw ValidationException::withMessages([
                        'diet_request_id' => 'Only Pending diet requests can receive a diet plan.',
                    ]);
                }
                if ($dietRequest->dietPlan()->lockForUpdate()->exists()) {
                    throw ValidationException::withMessages([
                        'diet_request_id' => 'A diet plan already exists for this request.',
                    ]);
                }

                $planData = collect($validated)->except(['diet_request_id', 'preselected_request_id'])->all();

                $plan = DietPlan::create(array_merge($planData, [
                    'diet_request_id' => $dietRequest->id,
                    'dietitian_id'    => Auth::id(),
                    'updated_by'      => Auth::id(),
                    'status'          => 'Active',
                ]));

                $dietRequest->update(['status' => 'Active']);

                return $plan;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages([
                'diet_request_id' => 'A diet plan already exists for this request.',
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000' || str_contains($e->getMessage(), 'diet_plans_diet_request_id_unique')) {
                throw ValidationException::withMessages([
                    'diet_request_id' => 'A diet plan already exists for this request.',
                ]);
            }
            throw $e;
        }

        $plan->load('dietRequest');
        $this->log('Diet Plan Created', "Diet plan [{$plan->plan_no}] was created for request [{$plan->dietRequest->request_no}].", $plan);

        if (filled($plan->assessment_notes)) {
            $this->log('Nutrition Assessment Recorded', "Nutrition assessment recorded on diet plan [{$plan->plan_no}].", $plan);
        }

        return redirect()->route('diet.plans.show', $plan)
                         ->with('success', 'Diet plan created successfully.');
    }

    public function show(DietPlan $dietPlan): View
    {
        $this->authorize('view', $dietPlan);

        $dietPlan->load('dietRequest.patient', 'dietRequest.doctor', 'dietitian', 'createdBy', 'updatedBy', 'completedBy', 'mealSchedules');

        return view('diet.plans.show', compact('dietPlan'));
    }

    public function edit(DietPlan $dietPlan): View
    {
        $this->authorize('update', $dietPlan);

        return view('diet.plans.edit', compact('dietPlan'));
    }

    public function update(StoreDietPlanRequest $request, DietPlan $dietPlan): RedirectResponse
    {
        $this->authorize('update', $dietPlan);

        $data = $request->validated();
        $assessmentChanged = ($data['assessment_notes'] ?? null) !== $dietPlan->assessment_notes;

        $dietPlan->update(array_merge($data, [
            'updated_by' => Auth::id(),
            'status'     => 'Revised',
        ]));

        $this->log('Diet Plan Updated', "Diet plan [{$dietPlan->plan_no}] was revised.", $dietPlan);

        if ($assessmentChanged) {
            $this->log('Nutrition Assessment Updated', "Nutrition assessment updated on diet plan [{$dietPlan->plan_no}].", $dietPlan);
        }

        return redirect()->route('diet.plans.show', $dietPlan)
                         ->with('success', 'Diet plan updated.');
    }

    public function destroy(DietPlan $dietPlan): RedirectResponse
    {
        $this->authorize('delete', $dietPlan);

        DB::transaction(function () use ($dietPlan) {
            $dietPlan->dietRequest->update(['status' => 'Pending']);
            $dietPlan->delete();
        });

        $this->log('Diet Plan Removed', "Diet plan [{$dietPlan->plan_no}] was removed and its request returned to Pending.", $dietPlan);

        return redirect()->route('diet.plans.index')
                         ->with('success', 'Diet plan removed.');
    }

    public function complete(DietPlan $dietPlan): RedirectResponse
    {
        $this->authorize('complete', $dietPlan);

        DB::transaction(function () use ($dietPlan) {
            $dietPlan->update([
                'status'       => 'Completed',
                'completed_by' => Auth::id(),
                'completed_at' => now(),
                'updated_by'   => Auth::id(),
            ]);
            $dietPlan->dietRequest->update(['status' => 'Completed']);
        });

        $this->log('Diet Plan Completed', "Diet plan [{$dietPlan->plan_no}] was marked as completed.", $dietPlan);

        return back()->with('success', 'Diet plan marked as completed.');
    }

    /** Print-friendly view for diet plan. */
    public function print(DietPlan $dietPlan): View
    {
        $this->authorize('view', $dietPlan);

        $dietPlan->load('dietRequest.patient', 'dietRequest.doctor', 'dietitian', 'createdBy');

        return view('diet.plans.print', compact('dietPlan'));
    }

    private function log(string $action, string $description, DietPlan $dietPlan): void
    {
        ActivityLog::create([
            'user_id'       => Auth::id(),
            'action'        => $action,
            'module'        => 'Diet & Nutrition',
            'description'   => $description,
            'loggable_type' => DietPlan::class,
            'loggable_id'   => $dietPlan->id,
            'ip_address'    => request()->ip(),
            'logged_at'     => now(),
        ]);
    }
}
