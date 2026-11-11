@extends('layouts.app')

@section('title', 'Database Backup Operations & Recovery')
@section('page-title', 'Super Admin — Backup (restore handled offline by authorized IT support)')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('super-admin.dashboard') }}">Dashboard</a></li>
    <li class="breadcrumb-item active" aria-current="page">Database Backup</li>
@endsection

@section('content')
<div class="container-fluid px-0">
    {{-- Header Banner & Action Buttons --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-database-fill-check text-success me-2"></i> Database Backup & Data Integrity</h5>
                <p class="text-muted small mb-0">
                    Protected database backup executions utilizing <code>mysqldump</code> with SHA256 cryptographic verification. Backup files are stored securely on private server storage and are never exposed via unauthenticated endpoints.
                </p>
            </div>
            <div class="d-flex gap-2">
                <form action="{{ route('super-admin.backup.prune') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary rounded-pill px-3">
                        <i class="bi bi-trash3 me-1"></i> Prune Expired Backups
                    </button>
                </form>
                <form action="{{ route('super-admin.backup.trigger') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary rounded-pill px-3">
                        <i class="bi bi-play-circle me-1"></i> Trigger Backup Now
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="small fw-semibold text-muted">Total Backup Runs</div>
                <h4 class="fw-bold mb-0 text-dark">{{ $stats['total'] }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="small fw-semibold text-muted">Successful Dumps</div>
                <h4 class="fw-bold mb-0 text-success">{{ $stats['successful'] }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="small fw-semibold text-muted">Failed Dumps</div>
                <h4 class="fw-bold mb-0 text-danger">{{ $stats['failed'] }}</h4>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm text-center py-2">
                <div class="small fw-semibold text-muted">Latest Job Outcome</div>
                <div class="mt-1">
                    @if($stats['last_run'])
                        <span class="badge {{ $stats['last_run']->status === 'success' ? 'bg-success text-white' : 'bg-danger text-white' }} px-3 py-1 rounded-pill">
                            {{ strtoupper($stats['last_run']->status) }}
                        </span>
                    @else
                        <span class="text-muted small">None</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Run History Table --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom py-3">
            <h6 class="m-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i> Backup Run Log & Cryptographic Verification</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase">
                        <tr>
                            <th class="ps-3">Run ID</th>
                            <th>Trigger</th>
                            <th>Status</th>
                            <th>File Name & Storage Location</th>
                            <th>Size</th>
                            <th>SHA256 Checksum</th>
                            <th>Timestamp</th>
                            <th class="pe-3">Summary / Error</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($backupRuns as $run)
                            <tr>
                                <td class="ps-3 font-monospace text-primary fw-bold">#{{ str_pad($run->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td><span class="badge bg-light text-dark border text-capitalize">{{ $run->trigger_type }}</span></td>
                                <td>
                                    @if($run->status === 'success')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Success</span>
                                    @elseif($run->status === 'running')
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">Running</span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Failed</span>
                                    @endif
                                </td>
                                <td>
                                    @if($run->file_path)
                                        <div class="fw-semibold text-dark">{{ basename($run->file_path) }}</div>
                                        <div class="text-muted font-monospace" style="font-size: 10px;">{{ $run->file_path }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="font-monospace fw-semibold">{{ $run->formatted_size }}</td>
                                <td class="font-monospace text-muted" style="font-size: 11px;">
                                    @if($run->checksum_sha256)
                                        <span title="{{ $run->checksum_sha256 }}">{{ substr($run->checksum_sha256, 0, 16) }}...</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-muted">{{ $run->created_at ? $run->created_at->format('M d, Y H:i:s') : '—' }}</td>
                                <td class="pe-3 text-muted text-truncate" style="max-width: 250px;">
                                    {{ $run->error_summary ?? ($run->pruned_at ? 'Pruned on ' . $run->pruned_at->format('M d, Y') : 'Active backup archive') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    No database backup records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($backupRuns->hasPages())
                <div class="p-3 border-top">
                    {{ $backupRuns->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
