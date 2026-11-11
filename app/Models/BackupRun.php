<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupRun extends Model
{
    protected $table = 'backup_runs';

    protected $fillable = [
        'trigger_type',
        'status',
        'file_path',
        'file_size',
        'checksum_sha256',
        'started_at',
        'finished_at',
        'error_summary',
        'pruned_at',
        'triggered_by',
    ];

    protected $casts = [
        'file_size'   => 'integer',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'pruned_at'   => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function getFormattedSizeAttribute(): string
    {
        if (! $this->file_size) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($this->file_size, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
