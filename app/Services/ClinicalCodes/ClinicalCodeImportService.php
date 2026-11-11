<?php

namespace App\Services\ClinicalCodes;

use App\Models\ActivityLog;
use App\Models\ClinicalCode;
use App\Models\ClinicalCodeSystem;
use App\Models\ClinicalCodeVersion;
use Illuminate\Support\Facades\DB;

class ClinicalCodeImportService
{
    /**
     * Validate CSV file and stage a new version of clinical codes.
     */
    public function validateAndStageCsv(
        ClinicalCodeSystem $system,
        string $filePath,
        string $originalFilename,
        string $versionLabel,
        ?string $effectiveDate = null,
        ?int $userId = null
    ): ClinicalCodeVersion {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("CSV file not found at [{$filePath}].");
        }

        $sha256 = hash_file('sha256', $filePath);

        // Check if identical file was already imported for this system
        $existing = ClinicalCodeVersion::where('system_id', $system->id)
            ->where('source_sha256', $sha256)
            ->first();
        if ($existing) {
            throw new \InvalidArgumentException("This identical dataset file was already imported as version [{$existing->version_label}].");
        }

        $handle = fopen($filePath, 'r');
        if (! $handle) {
            throw new \RuntimeException("Unable to open dataset file for reading.");
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            throw new \InvalidArgumentException("The imported CSV file is empty.");
        }

        // Normalize header column names
        $headerMap = [];
        foreach ($header as $idx => $col) {
            $normalized = strtolower(trim($col));
            if (in_array($normalized, ['code', 'concept_code', 'id', 'loinc_num', 'cpt_code'], true)) {
                $headerMap['code'] = $idx;
            }
            if (in_array($normalized, ['display', 'display_name', 'description', 'title', 'long_common_name', 'name'], true)) {
                $headerMap['display'] = $idx;
            }
        }

        if (! isset($headerMap['code']) || ! isset($headerMap['display'])) {
            fclose($handle);
            throw new \InvalidArgumentException("CSV must contain 'code' and 'display' (or 'description') columns.");
        }

        $total = 0;
        $valid = 0;
        $invalid = 0;
        $duplicates = 0;
        $seenCodes = [];
        $stagedCodes = [];
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || (count($row) === 1 && trim((string)$row[0]) === '')) {
                continue;
            }
            $total++;

            $codeVal = trim((string) ($row[$headerMap['code']] ?? ''));
            $dispVal = trim((string) ($row[$headerMap['display']] ?? ''));

            if ($codeVal === '' || $dispVal === '') {
                $invalid++;
                if (count($errors) < 20) {
                    $errors[] = "Row {$total}: Missing required code or display text.";
                }
                continue;
            }

            // Syntax validation by system key
            $formatValid = match ($system->system_key) {
                'CPT'    => (bool) preg_match('/^\d{4}[0-9FTU]$/i', $codeVal),
                'LOINC'  => (bool) preg_match('/^\d{1,7}-\d$/', $codeVal),
                default  => (bool) preg_match('/^[A-Z0-9\.\-\/]{2,40}$/i', $codeVal),
            };

            if (! $formatValid) {
                $invalid++;
                if (count($errors) < 20) {
                    $errors[] = "Row {$total}: Invalid {$system->system_key} code format [{$codeVal}].";
                }
                continue;
            }

            if (isset($seenCodes[$codeVal])) {
                $duplicates++;
                if (count($errors) < 20) {
                    $errors[] = "Row {$total}: Duplicate code [{$codeVal}] within file.";
                }
                continue;
            }

            $seenCodes[$codeVal] = true;
            $valid++;
            $stagedCodes[] = [
                'system_id' => $system->id,
                'code'      => $codeVal,
                'display'   => mb_substr($dispVal, 0, 500),
            ];
        }
        fclose($handle);

        return DB::transaction(function () use ($system, $versionLabel, $effectiveDate, $originalFilename, $sha256, $total, $valid, $invalid, $duplicates, $errors, $userId, $stagedCodes) {
            $version = ClinicalCodeVersion::create([
                'system_id'         => $system->id,
                'version_label'     => $versionLabel,
                'effective_date'    => $effectiveDate,
                'status'            => 'staged',
                'source_filename'   => $originalFilename,
                'source_sha256'     => $sha256,
                'total_rows'        => $total,
                'valid_rows'        => $valid,
                'invalid_rows'      => $invalid,
                'duplicate_rows'    => $duplicates,
                'validation_report' => [
                    'errors'    => $errors,
                    'is_clean'  => ($invalid === 0 && $duplicates === 0),
                ],
                'imported_by'       => $userId,
            ]);

            // Bulk insert valid codes in chunks of 500
            foreach (array_chunk($stagedCodes, 500) as $chunk) {
                foreach ($chunk as &$row) {
                    $row['version_id'] = $version->id;
                }
                ClinicalCode::insert($chunk);
            }

            try {
                ActivityLog::create([
                    'user_id'     => $userId,
                    'action'      => 'Clinical Code Version Staged',
                    'module'      => ActivityLog::MODULE_CLINICAL_CODES,
                    'description' => sprintf(
                        'Staged clinical code version [%s] for %s (%d total, %d valid, %d invalid, %d duplicates).',
                        $versionLabel,
                        $system->name,
                        $total,
                        $valid,
                        $invalid,
                        $duplicates
                    ),
                    'severity'    => ActivityLog::SEVERITY_INFO,
                    'result'      => ActivityLog::RESULT_SUCCESS,
                    'logged_at'   => now(),
                ]);
            } catch (\Throwable $e) {}

            return $version;
        });
    }

    /**
     * Publish a staged version after verifying license status and zero errors.
     */
    public function publishVersion(ClinicalCodeVersion $version, ?int $userId = null): void
    {
        $system = $version->system;

        if (! $system->isVerified()) {
            throw new \DomainException(
                "Cannot publish version for [{$system->name}] because its licensing status is 'unverified'. Super Admin must verify licensing credentials first."
            );
        }

        if ($version->invalid_rows > 0 || $version->duplicate_rows > 0) {
            throw new \DomainException(
                "Cannot publish version with validation errors ({$version->invalid_rows} invalid, {$version->duplicate_rows} duplicates). Clean the dataset first."
            );
        }

        DB::transaction(function () use ($version, $system, $userId) {
            // Supersede previous active version if any
            if ($system->active_version_id) {
                ClinicalCodeVersion::where('id', $system->active_version_id)
                    ->update(['status' => 'superseded']);
            }

            $version->update([
                'status'       => 'published',
                'published_by' => $userId,
                'published_at' => now(),
            ]);

            $system->update(['active_version_id' => $version->id]);

            try {
                ActivityLog::create([
                    'user_id'     => $userId,
                    'action'      => 'Clinical Code Version Published',
                    'module'      => ActivityLog::MODULE_CLINICAL_CODES,
                    'description' => sprintf(
                        'Published version [%s] for %s with %d active codes.',
                        $version->version_label,
                        $system->name,
                        $version->valid_rows
                    ),
                    'severity'    => ActivityLog::SEVERITY_INFO,
                    'result'      => ActivityLog::RESULT_SUCCESS,
                    'logged_at'   => now(),
                ]);
            } catch (\Throwable $e) {}
        });
    }

    /**
     * Rollback a published version.
     */
    public function rollbackVersion(ClinicalCodeVersion $version, string $reason, ?int $userId = null): void
    {
        $system = $version->system;

        DB::transaction(function () use ($version, $system, $reason, $userId) {
            $version->update([
                'status'          => 'rolled_back',
                'rolled_back_at'  => now(),
                'rollback_reason' => $reason,
            ]);

            // Restore previous published or superseded version if one exists
            $previous = ClinicalCodeVersion::where('system_id', $system->id)
                ->where('id', '!=', $version->id)
                ->whereIn('status', ['superseded', 'published'])
                ->orderBy('id', 'desc')
                ->first();

            if ($previous) {
                $previous->update(['status' => 'published']);
                $system->update(['active_version_id' => $previous->id]);
            } else {
                $system->update(['active_version_id' => null]);
            }

            try {
                ActivityLog::create([
                    'user_id'     => $userId,
                    'action'      => 'Clinical Code Version Rolled Back',
                    'module'      => ActivityLog::MODULE_CLINICAL_CODES,
                    'description' => sprintf(
                        'Rolled back version [%s] for %s. Reason: %s',
                        $version->version_label,
                        $system->name,
                        $reason
                    ),
                    'severity'    => ActivityLog::SEVERITY_WARNING,
                    'result'      => ActivityLog::RESULT_SUCCESS,
                    'logged_at'   => now(),
                ]);
            } catch (\Throwable $e) {}
        });
    }
}
