<?php

namespace App\Services\Backup;

use Symfony\Component\Process\Process;

class MysqldumpBackupRunner implements BackupRunnerInterface
{
    public function dump(string $destinationAbsolutePath): array
    {
        $connectionName = config('database.default', 'mysql');
        $dbConfig = config("database.connections.{$connectionName}", []);

        $host     = $dbConfig['host'] ?? '127.0.0.1';
        $port     = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'] ?? '';
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';

        $binary = env('DB_DUMP_BINARY_PATH', 'mysqldump');

        // Create temporary defaults-extra-file so password is not exposed in CLI arguments
        $tempCnf = tempnam(sys_get_temp_dir(), 'hims_my_');
        $cnfContent = "[client]\nuser=\"{$username}\"\npassword=\"{$password}\"\nhost=\"{$host}\"\nport=\"{$port}\"\n";
        file_put_contents($tempCnf, $cnfContent);

        try {
            $cmd = [
                $binary,
                "--defaults-extra-file={$tempCnf}",
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                $database,
                "--result-file={$destinationAbsolutePath}",
            ];

            $process = new Process($cmd);
            $process->setTimeout(600); // 10 minutes timeout
            $process->run();

            if (! $process->isSuccessful()) {
                if (file_exists($destinationAbsolutePath)) {
                    @unlink($destinationAbsolutePath);
                }
                return [
                    'success'   => false,
                    'error'     => 'mysqldump failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()),
                    'file_size' => null,
                    'checksum'  => null,
                ];
            }

            if (! file_exists($destinationAbsolutePath) || filesize($destinationAbsolutePath) === 0) {
                if (file_exists($destinationAbsolutePath)) {
                    @unlink($destinationAbsolutePath);
                }
                return [
                    'success'   => false,
                    'error'     => 'mysqldump produced an empty or missing output file.',
                    'file_size' => null,
                    'checksum'  => null,
                ];
            }

            $size = filesize($destinationAbsolutePath);
            $hash = hash_file('sha256', $destinationAbsolutePath);

            return [
                'success'   => true,
                'error'     => null,
                'file_size' => $size,
                'checksum'  => $hash,
            ];
        } catch (\Throwable $e) {
            if (file_exists($destinationAbsolutePath)) {
                @unlink($destinationAbsolutePath);
            }
            return [
                'success'   => false,
                'error'     => 'Backup process exception: ' . $e->getMessage(),
                'file_size' => null,
                'checksum'  => null,
            ];
        } finally {
            if (file_exists($tempCnf)) {
                @unlink($tempCnf);
            }
        }
    }
}
