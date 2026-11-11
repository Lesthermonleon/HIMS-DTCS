<?php

namespace App\Services\Backup;

interface BackupRunnerInterface
{
    /**
     * Execute a database dump to the specified destination path.
     * Returns an array with ['success' => bool, 'error' => ?string, 'file_size' => ?int, 'checksum' => ?string].
     */
    public function dump(string $destinationAbsolutePath): array;
}
