@extends('layouts.app')
@section('title', 'Result Detail')
@section('page-title', 'Lab Result Detail')
@section('content')
<div class="card" style="max-width:600px;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-journal-medical me-2"></i>Result Information</span>
        <span class="badge bg-{{ $labResult->statusBadge }} fs-6">{{ $labResult->status }}</span>
    </div>
    <div class="card-body">
        <dl class="row small">
            <dt class="col-5 text-muted">Test</dt><dd class="col-7 fw-semibold">{{ $labResult->requestItem->labTest->name }}</dd>
            <dt class="col-5 text-muted">Category</dt><dd class="col-7">{{ $labResult->requestItem->labTest->category->name }}</dd>
            <dt class="col-5 text-muted">Request No</dt><dd class="col-7">{{ $labResult->requestItem->labRequest->request_no }}</dd>
            <dt class="col-5 text-muted">Patient</dt><dd class="col-7">{{ $labResult->requestItem->labRequest->patient->full_name }}</dd>
            <dt class="col-5 text-muted">Result Value</dt><dd class="col-7 fw-bold text-primary fs-5">{{ $labResult->result_value }} {{ $labResult->requestItem->labTest->unit }}</dd>
            <dt class="col-5 text-muted">Normal Range</dt><dd class="col-7">{{ $labResult->requestItem->labTest->normal_range }}</dd>
            <dt class="col-5 text-muted">Remarks</dt><dd class="col-7">{{ $labResult->remarks ?? '—' }}</dd>
            <dt class="col-5 text-muted">Technologist</dt><dd class="col-7">{{ $labResult->technologist?->name ?? '—' }}</dd>
            <dt class="col-5 text-muted">Validated By</dt><dd class="col-7">{{ $labResult->validatedBy?->name ?? '—' }} {{ $labResult->validated_at ? '('.$labResult->validated_at->format('M d, Y H:i').')' : '' }}</dd>
            <dt class="col-5 text-muted">Released By</dt><dd class="col-7">{{ $labResult->releasedBy?->name ?? '—' }} {{ $labResult->released_at ? '('.$labResult->released_at->format('M d, Y H:i').')' : '' }}</dd>
        </dl>
    </div>
    <div class="card-footer d-flex gap-2">
        @if($labResult->status==='Encoded' && auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.results.verify'))
            <form method="POST" action="{{ route('lab.results.validate', $labResult) }}">
                @csrf @method('PATCH')
                <button class="btn btn-primary"><i class="bi bi-shield-check me-1"></i>Validate</button>
            </form>
        @endif
        @if($labResult->status==='Validated' && auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.results.release'))
            <form method="POST" action="{{ route('lab.results.release', $labResult) }}">
                @csrf @method('PATCH')
                <button class="btn btn-success"><i class="bi bi-send me-1"></i>Release</button>
            </form>
        @endif
        @if($labResult->status !== 'Released' && auth()->user()?->hasRole('med-tech') && auth()->user()->hasPermission('lis.results.enter'))
            <a href="{{ route('lab.results.edit', $labResult) }}" class="btn btn-outline-warning"><i class="bi bi-pencil me-1"></i>Edit</a>
        @endif
        <a href="{{ route('lab.results.index') }}" class="btn btn-outline-secondary ms-auto"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
@endsection
