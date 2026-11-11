@extends('layouts.app')
@section('title', 'Diet Plan Details')
@section('page-title', 'Diet Plan: ' . $dietPlan->plan_no)
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('diet.plans.index') }}">Diet Plans</a></li>
    <li class="breadcrumb-item active">{{ $dietPlan->plan_no }}</li>
@endsection
@section('content')
@php
    $proteinKcal = $dietPlan->protein_grams ? $dietPlan->protein_grams * 4 : 0;
    $carbKcal = $dietPlan->carb_grams ? $dietPlan->carb_grams * 4 : 0;
    $fatKcal = $dietPlan->fat_grams ? $dietPlan->fat_grams * 9 : 0;
    $totalMacroKcal = $proteinKcal + $carbKcal + $fatKcal;

    $proteinPct = $totalMacroKcal > 0 ? round(($proteinKcal / $totalMacroKcal) * 100) : 0;
    $carbPct = $totalMacroKcal > 0 ? round(($carbKcal / $totalMacroKcal) * 100) : 0;
    $fatPct = $totalMacroKcal > 0 ? max(0, 100 - $proteinPct - $carbPct) : 0;
@endphp

<div class="row g-3">
    <div class="col-lg-8">
        {{-- Plan Details Card --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-medical me-2"></i>Therapeutic Plan Instructions</span>
                <span class="badge bg-{{ $dietPlan->statusBadge }}">{{ $dietPlan->status }}</span>
            </div>
            <div class="card-body">
                @if($dietPlan->assessment_notes)
                    <div class="mb-4" id="nutrition-assessment">
                        <h6 class="fw-bold text-secondary mb-2"><i class="bi bi-clipboard2-pulse me-1"></i>Nutrition Assessment &amp; Recommendations</h6>
                        <div class="p-3 rounded bg-light border-start border-success border-4" style="white-space: pre-line; font-size: 0.9rem;">{{ $dietPlan->assessment_notes }}</div>
                    </div>
                @endif

                <div class="mb-4">
                    <h6 class="fw-bold text-secondary mb-2">Instructions & Meal Guidelines</h6>
                    <div class="p-3 rounded bg-light border-start border-primary border-4" style="white-space: pre-line; font-size: 0.95rem;">
                        {{ $dietPlan->plan_details }}
                    </div>
                </div>

                @if($dietPlan->notes)
                    <div class="mb-0">
                        <h6 class="fw-bold text-secondary mb-2">Additional Plan Notes</h6>
                        <div class="p-3 rounded bg-light border-start border-secondary border-4" style="white-space: pre-line; font-size: 0.9rem;">
                            {{ $dietPlan->notes }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Meal Schedules Card --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-check me-2"></i>Meal Schedules</span>
                <span class="badge bg-info text-dark">{{ $dietPlan->mealSchedules->count() }} Scheduled Meal(s)</span>
            </div>
            <div class="card-body p-0">
                @if($dietPlan->mealSchedules->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Meal Date</th>
                                    <th>Meal Type</th>
                                    <th>Menu</th>
                                    <th>Calories</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($dietPlan->mealSchedules as $schedule)
                                    <tr>
                                        <td>{{ $schedule->meal_date?->format('M d, Y') }}</td>
                                        <td><span class="badge bg-secondary">{{ $schedule->meal_type }}</span></td>
                                        <td class="fw-semibold">{{ $schedule->menu }}</td>
                                        <td class="text-success fw-bold">{{ $schedule->calories }} kcal</td>
                                        <td>
                                            @if($schedule->is_served)
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Served</span>
                                            @else
                                                <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i>Pending</span>
                                            @endif
                                        </td>
                                        <td><small class="text-muted">{{ $schedule->notes ?? '—' }}</small></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-calendar-x fs-2 d-block mb-2"></i>
                        No explicit daily meal schedules defined for this diet plan. Follow the core instructions above.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Right column — Nutrients, metadata, actions --}}
    <div class="col-lg-4">
        {{-- Nutrition Calculator Card --}}
        <div class="card mb-3">
            <div class="card-header bg-success-subtle text-success-emphasis"><i class="bi bi-calculator me-2"></i>Nutritional & Caloric Targets</div>
            <div class="card-body">
                <div class="text-center mb-3">
                    <div class="text-muted small">Daily Energy Target</div>
                    <div class="fs-1 fw-bold text-success">{{ $dietPlan->total_calories ?? '0' }} <span style="font-size:1rem; font-weight:normal;">kcal</span></div>
                </div>

                <div class="row g-2 mb-3 text-center">
                    <div class="col-4">
                        <div class="p-2 bg-danger-subtle text-danger-emphasis rounded border border-danger-subtle">
                            <div class="small fw-bold">Protein</div>
                            <div class="fw-bold">{{ $dietPlan->protein_grams ?? '0' }}g</div>
                            <small class="text-muted">{{ $proteinKcal }} kcal</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-secondary-subtle text-secondary-emphasis rounded border border-secondary-subtle">
                            <div class="small fw-bold">Carbs</div>
                            <div class="fw-bold">{{ $dietPlan->carb_grams ?? '0' }}g</div>
                            <small class="text-muted">{{ $carbKcal }} kcal</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="p-2 bg-dark-subtle text-dark-emphasis rounded border border-dark-subtle">
                            <div class="small fw-bold">Fat</div>
                            <div class="fw-bold">{{ $dietPlan->fat_grams ?? '0' }}g</div>
                            <small class="text-muted">{{ $fatKcal }} kcal</small>
                        </div>
                    </div>
                </div>

                @if($totalMacroKcal > 0)
                    <div class="mb-1 d-flex justify-content-between small text-muted">
                        <span>Caloric Ratio:</span>
                        <span>Total: {{ $totalMacroKcal }} kcal</span>
                    </div>
                    <div class="progress mb-2 d-flex" style="height: 24px; border-radius: 6px; overflow: hidden; font-size: 0.72rem; font-weight: bold; color:#fff;">
                        @if($proteinPct > 0)
                            <div class="progress-bar-fill progress-bar text-center" data-width="{{ $proteinPct }}" role="progressbar" style="background-color: #E85C55; height:100%; display: flex; align-items: center; justify-content: center;" title="Protein: {{ $proteinPct }}%">P ({{ $proteinPct }}%)</div>
                        @endif
                        @if($carbPct > 0)
                            <div class="progress-bar-fill progress-bar text-center" data-width="{{ $carbPct }}" role="progressbar" style="background-color: #475569; height:100%; display: flex; align-items: center; justify-content: center;" title="Carbs: {{ $carbPct }}%">C ({{ $carbPct }}%)</div>
                        @endif
                        @if($fatPct > 0)
                            <div class="progress-bar-fill progress-bar text-center" data-width="{{ $fatPct }}" role="progressbar" style="background-color: #334155; height:100%; display: flex; align-items: center; justify-content: center;" title="Fat: {{ $fatPct }}%">F ({{ $fatPct }}%)</div>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between x-small text-muted" style="font-size:0.7rem;">
                        <span><i class="bi bi-circle-fill me-1" style="color:#E85C55;"></i>Protein (4kcal/g)</span>
                        <span><i class="bi bi-circle-fill me-1" style="color:#475569;"></i>Carbs (4kcal/g)</span>
                        <span><i class="bi bi-circle-fill me-1" style="color:#334155;"></i>Fat (9kcal/g)</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Plan Association & Timeline Card --}}
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-badge-fill me-2"></i>Patient & Clinical Link</div>
            <div class="card-body">
                <span class="text-muted small">Assigned Patient:</span>
                <h6 class="fw-bold mt-1 text-primary">
                    {{ $dietPlan->dietRequest->patient->last_name ?? '—' }}, {{ $dietPlan->dietRequest->patient->first_name ?? '' }} 
                    <small class="text-muted">({{ $dietPlan->dietRequest->patient->patient_no ?? '—' }})</small>
                </h6>

                <span class="text-muted small d-block mt-3">Diet Type:</span>
                <span class="badge bg-info text-dark" style="font-size:.78rem;">{{ $dietPlan->dietRequest->diet_type ?? '—' }}</span>

                <div class="row g-2 mt-2 small">
                    <div class="col-6">
                        <span class="text-muted d-block">Start Date</span>
                        <span class="fw-semibold">{{ $dietPlan->start_date?->format('M d, Y') }}</span>
                    </div>
                    <div class="col-6">
                        <span class="text-muted d-block">End Date</span>
                        <span class="fw-semibold">{{ $dietPlan->end_date ? $dietPlan->end_date->format('M d, Y') : 'Ongoing' }}</span>
                    </div>
                    <div class="col-12 mt-2">
                        <span class="text-muted d-block">Created By</span>
                        <span class="fw-semibold">{{ $dietPlan->createdBy->name ?? ($dietPlan->dietitian->name ?? ($dietPlan->dietRequest->doctor->name ?? '—')) }}</span>
                    </div>
                    @if($dietPlan->updatedBy)
                        <div class="col-12">
                            <span class="text-muted d-block">Last Updated By</span>
                            <span class="fw-semibold">{{ $dietPlan->updatedBy->name }}</span>
                            <small class="text-muted">({{ $dietPlan->updated_at?->format('M d, Y H:i') }})</small>
                        </div>
                    @endif
                    @if($dietPlan->completedBy)
                        <div class="col-12">
                            <span class="text-muted d-block">Completed By</span>
                            <span class="fw-semibold">{{ $dietPlan->completedBy->name }}</span>
                            <small class="text-muted">({{ $dietPlan->completed_at?->format('M d, Y H:i') }})</small>
                        </div>
                    @endif
                </div>
                <hr>
                <div class="d-flex justify-content-between">
                    <a href="{{ route('patients.show', $dietPlan->dietRequest->patient_id) }}" class="btn btn-xs btn-outline-secondary"><i class="bi bi-person-lines-fill me-1"></i>Patient Profile</a>
                    <a href="{{ route('diet.requests.show', $dietPlan->diet_request_id) }}" class="btn btn-xs btn-outline-primary"><i class="bi bi-journal-bookmark me-1"></i>Related Request</a>
                </div>
            </div>
        </div>

        {{-- Actions panel --}}
        <div class="card">
            <div class="card-header"><i class="bi bi-gear me-2"></i>Available Actions</div>
            <div class="card-body d-flex flex-column gap-2">
                @can('complete', $dietPlan)
                    <form method="POST" action="{{ route('diet.plans.complete', $dietPlan) }}">
                        @csrf @method('PATCH')
                        <button type="submit" class="btn btn-success w-100 text-start"><i class="bi bi-check-circle me-2"></i>Complete Diet Plan</button>
                    </form>
                @endcan
                @can('update', $dietPlan)
                    <a href="{{ route('diet.plans.edit', $dietPlan) }}" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-pencil me-2"></i>Edit Plan details</a>
                @endcan
                @can('delete', $dietPlan)
                    <form method="POST" action="{{ route('diet.plans.destroy', $dietPlan) }}" onsubmit="return confirm('DANGER: This will delete the diet plan and revert the request status back to Pending. Do you want to proceed?');">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger w-100 text-start"><i class="bi bi-trash me-2"></i>Delete/Revert Plan</button>
                    </form>
                @endcan
                <a href="{{ route('diet.plans.print', $dietPlan) }}" target="_blank" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-printer me-2"></i>Print Diet Plan</a>
                <a href="{{ route('diet.plans.index') }}" class="btn btn-outline-secondary w-100 text-start"><i class="bi bi-chevron-left me-2"></i>Back to Plans</a>
            </div>
        </div>
    </div>
</div>
@endsection
