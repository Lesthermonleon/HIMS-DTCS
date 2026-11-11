<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionSuperAdminCommand extends Command
{
    protected $signature = 'hims:super-admin:provision';

    protected $description = 'Provision a Super Administrator account interactively with strict security and auditing.';

    public function handle(): int
    {
        $this->info('====================================================');
        $this->info(' DTCS HIMS — Super Administrator Account Provisioning');
        $this->info('====================================================');

        if (! $this->input->isInteractive()) {
            $this->error('Super Admin provisioning requires an interactive terminal for secure credential input.');
            return self::FAILURE;
        }

        $superAdminRole = Role::where('slug', 'super_admin')->first();
        if (! $superAdminRole) {
            $this->error('The "super_admin" role does not exist in the database. Please run database/schema/super_admin_seeder.sql first.');
            return self::FAILURE;
        }

        $email = trim((string) $this->ask('Enter Administrator Email Address'));
        $emailValidator = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($emailValidator->fails()) {
            $this->error($emailValidator->errors()->first('email'));
            return self::FAILURE;
        }

        // Check if soft-deleted
        $trashedUser = User::onlyTrashed()->where('email', $email)->first();
        if ($trashedUser) {
            $this->error('An account with this email is currently archived/soft-deleted. Restore the account before provisioning.');
            return self::FAILURE;
        }

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            if ($existingUser->hasRole('super_admin')) {
                $this->warn("Account [{$email}] is already a Super Administrator.");
                return self::SUCCESS;
            }

            if ($existingUser->roles()->exists()) {
                $currentRoles = $existingUser->roles->pluck('name')->implode(', ');
                $this->warn("Account [{$email}] already has existing roles: [{$currentRoles}].");
                if (! $this->confirm('Do you want to elevate this existing user to Super Administrator?', false)) {
                    $this->info('Provisioning aborted by user.');
                    return self::SUCCESS;
                }
            }

            $user = $existingUser;
            $isNew = false;
        } else {
            $name = trim((string) $this->ask('Enter Full Name'));
            if (empty($name)) {
                $this->error('Full name is required.');
                return self::FAILURE;
            }

            $user = new User([
                'name'      => $name,
                'email'     => $email,
                'is_active' => true,
            ]);
            $isNew = true;
        }

        $password = (string) $this->secret('Enter Password (hidden)');
        $confirmPassword = (string) $this->secret('Confirm Password (hidden)');

        if ($password !== $confirmPassword) {
            $this->error('Passwords do not match.');
            return self::FAILURE;
        }

        $pwValidator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::defaults()]]
        );

        if ($pwValidator->fails()) {
            foreach ($pwValidator->errors()->all() as $err) {
                $this->error($err);
            }
            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $password, $superAdminRole, $isNew) {
            $user->password = Hash::make($password);
            $user->is_active = true;
            $user->email_verified_at = now();
            $user->save();

            // Attach super_admin role if not already attached
            if (! $user->hasRole('super_admin')) {
                $user->roles()->syncWithoutDetaching([$superAdminRole->id]);
            }

            try {
                ActivityLog::create([
                    'user_id'     => $user->id,
                    'action'      => $isNew ? 'Super Admin Created' : 'Super Admin Role Granted',
                    'module'      => ActivityLog::MODULE_SECURITY,
                    'description' => sprintf(
                        'Super Administrator account [%s] (ID: %d) was provisioned via CLI console command.',
                        $user->email,
                        $user->id
                    ),
                    'ip_address'  => '127.0.0.1 (CLI)',
                    'severity'    => ActivityLog::SEVERITY_CRITICAL,
                    'result'      => ActivityLog::RESULT_SUCCESS,
                    'logged_at'   => now(),
                ]);
            } catch (\Throwable $e) {}
        });

        $this->info('');
        $this->info("✓ Super Administrator account [{$user->email}] successfully provisioned.");
        $this->info('You may now log in via the DTCS HIMS portal with email OTP verification.');
        return self::SUCCESS;
    }
}
