<?php

namespace App\Services\Settings;

use App\Models\ActivityLog;
use App\Models\SettingHistory;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    protected const CACHE_PREFIX = 'hims_setting:';
    protected const CACHE_TTL    = 3600; // 1 hour

    /**
     * In-memory cache for current request lifecycle.
     */
    protected array $memo = [];

    /**
     * Cache enabled toggle (can be disabled during tests).
     */
    protected bool $cacheEnabled = true;

    public function setCacheEnabled(bool $enabled): void
    {
        $this->cacheEnabled = $enabled;
        if (! $enabled) {
            $this->flushCache();
        }
    }

    /**
     * Flush both in-memory and store caches.
     */
    public function flushCache(): void
    {
        $this->memo = [];
        try {
            foreach (array_keys(SettingsRegistry::definitions()) as $key) {
                Cache::forget(self::CACHE_PREFIX . $key);
            }
        } catch (\Throwable $e) {
            // Ignore cache backend errors
        }
    }

    /**
     * Retrieve a setting value with graceful fallback.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        if (! SettingsRegistry::exists($key)) {
            return $default;
        }

        $registryDefault = SettingsRegistry::getDefault($key);

        if ($this->cacheEnabled) {
            try {
                $cached = Cache::get(self::CACHE_PREFIX . $key);
                if ($cached !== null) {
                    $this->memo[$key] = $cached;
                    return $cached;
                }
            } catch (\Throwable $e) {
                // Ignore cache failure
            }
        }

        $def = SettingsRegistry::definitions()[$key] ?? [];
        $type = $def['type'] ?? 'string';

        try {
            $record = SystemSetting::where('setting_key', $key)->first();
            $rawVal = $record ? $record->value : $registryDefault;
        } catch (\Throwable $e) {
            // DB error or table not yet migrated — fallback safely
            $rawVal = $registryDefault;
        }

        $val = match ($type) {
            'integer', 'integer_nullable' => is_null($rawVal) || $rawVal === '' ? null : (int) $rawVal,
            'boolean'                      => (bool) $rawVal,
            'array'                        => is_array($rawVal) ? $rawVal : (is_string($rawVal) ? json_decode($rawVal, true) ?? [] : []),
            default                        => is_null($rawVal) ? null : (string) $rawVal,
        };

        $this->memo[$key] = $val;

        if ($this->cacheEnabled) {
            try {
                Cache::put(self::CACHE_PREFIX . $key, $val, self::CACHE_TTL);
            } catch (\Throwable $e) {
                // Ignore cache failure
            }
        }

        return $val ?? $default;
    }

    /**
     * Retrieve all settings for a specific scope.
     */
    public function getForScope(string $scope): array
    {
        $result = [];
        foreach (SettingsRegistry::definitions() as $key => $def) {
            if ($def['scope'] === $scope) {
                $result[$key] = $this->get($key);
            }
        }
        return $result;
    }

    /**
     * Update a single setting with scope authorization, history, and audit.
     */
    public function set(
        string $key,
        mixed $value,
        string $expectedScope,
        ?int $userId = null,
        ?string $reason = null,
        ?string $ip = null
    ): void {
        if (! SettingsRegistry::exists($key)) {
            throw new \InvalidArgumentException("Unknown setting key: [{$key}].");
        }

        $defScope = SettingsRegistry::getScope($key);
        if ($defScope !== $expectedScope) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                "Setting [{$key}] belongs to scope [{$defScope}] and cannot be modified under scope [{$expectedScope}]."
            );
        }

        $oldValue = $this->get($key);

        // Normalize boolean strings
        if (is_string($value) && in_array(strtolower($value), ['true', '1', 'on'], true)) {
            $value = true;
        } elseif (is_string($value) && in_array(strtolower($value), ['false', '0', 'off'], true)) {
            $value = false;
        }

        $type = SettingsRegistry::definitions()[$key]['type'] ?? 'string';
        $dbVal = match ($type) {
            'array'   => is_array($value) ? json_encode($value) : (string) $value,
            'boolean' => $value ? '1' : '0',
            default   => is_null($value) ? null : (string) $value,
        };

        DB::transaction(function () use ($key, $expectedScope, $value, $dbVal, $oldValue, $userId, $reason, $ip) {
            SystemSetting::updateOrCreate(
                ['setting_key' => $key],
                [
                    'scope'      => $expectedScope,
                    'value'      => $dbVal,
                    'updated_by' => $userId,
                ]
            );

            // Record history if value changed
            if ($oldValue !== $value) {
                SettingHistory::create([
                    'setting_key' => $key,
                    'scope'       => $expectedScope,
                    'old_value'   => is_array($oldValue) ? json_encode($oldValue) : (is_null($oldValue) ? null : (string) $oldValue),
                    'new_value'   => is_array($value) ? json_encode($value) : (is_null($value) ? null : (string) $value),
                    'changed_by'  => $userId,
                    'reason'      => $reason,
                    'ip_address'  => $ip,
                ]);

                try {
                    ActivityLog::create([
                        'user_id'     => $userId,
                        'action'      => 'Setting Updated',
                        'module'      => $expectedScope === SettingsRegistry::SCOPE_SUPER_ADMIN
                            ? ActivityLog::MODULE_SYSTEM_SETTINGS
                            : ActivityLog::MODULE_HOSPITAL_SETTINGS,
                        'description' => sprintf(
                            'Setting [%s] updated under scope [%s]. Reason: %s',
                            $key,
                            $expectedScope,
                            $reason ?: 'Routine administrative update'
                        ),
                        'ip_address'  => $ip,
                        'severity'    => ActivityLog::SEVERITY_INFO,
                        'result'      => ActivityLog::RESULT_SUCCESS,
                        'logged_at'   => now(),
                    ]);
                } catch (\Throwable $e) {
                    // Ignore audit failure
                }
            }
        });

        // Invalidate cache
        $this->memo[$key] = $value;
        try {
            Cache::forget(self::CACHE_PREFIX . $key);
        } catch (\Throwable $e) {
            // Ignore cache failure
        }
    }

    /**
     * Update multiple settings for a scope with validation.
     */
    public function updateMany(
        array $values,
        string $scope,
        ?int $userId = null,
        ?string $reason = null,
        ?string $ip = null
    ): void {
        // Enforce scope boundary: reject any keys that don't belong to $scope
        foreach (array_keys($values) as $key) {
            if (! SettingsRegistry::exists($key)) {
                throw new \InvalidArgumentException("Unknown setting key: [{$key}].");
            }
            $targetScope = SettingsRegistry::getScope($key);
            if ($targetScope !== $scope) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    "Setting [{$key}] belongs to scope [{$targetScope}] and cannot be modified by [{$scope}]."
                );
            }
        }

        // Validate values against registry rules for keys being updated
        $rules = array_intersect_key(SettingsRegistry::getRulesForScope($scope), $values);
        $validator = Validator::make(\Illuminate\Support\Arr::undot($values), $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        foreach ($values as $key => $value) {
            $this->set($key, $value, $scope, $userId, $reason, $ip);
        }
    }
}
