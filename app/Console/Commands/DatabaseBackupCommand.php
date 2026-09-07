<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:backup
                            {--compress : Kompres file backup menjadi format gzip (.sql.gz)}
                            {--keep-days=7 : Jumlah hari retensi file backup lama (otomatis dibersihkan)}
                            {--path= : Direktori khusus tempat menyimpan file backup}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export database MySQL secara otomatis ke folder storage/backups';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        $this->info("==================================================");
        $this->info("📦 MEMULAI PROSES BACKUP DATABASE POSINDO");
        $this->info("==================================================");

        $connection = config('database.default', 'mysql');
        $dbConfig = config("database.connections.{$connection}", []);

        $dbName = $dbConfig['database'] ?? 'tracking_posindo';
        $dbHost = $dbConfig['host'] ?? '127.0.0.1';
        $dbPort = $dbConfig['port'] ?? '3306';
        $dbUser = $dbConfig['username'] ?? 'root';
        $dbPass = $dbConfig['password'] ?? '';

        // 1. Tentukan Direktori Backup
        $backupDir = $this->option('path') ?: storage_path('backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true, true);
        }

        $safeDbName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$dbName);
        if (empty($safeDbName) || $safeDbName === '_') {
            $safeDbName = 'database';
        }

        $timestamp = date('Y-m-d_H-i-s');
        $isCompressed = $this->option('compress');
        $fileName = "backup-{$safeDbName}-{$timestamp}.sql";
        if ($isCompressed) {
            $fileName .= ".gz";
        }
        $targetFile = $backupDir . DIRECTORY_SEPARATOR . $fileName;

        $this->line("• Koneksi Database : <comment>{$connection}</comment> ({$dbHost}:{$dbPort} / {$dbName})");
        $this->line("• Lokasi Penyimpanan: <comment>{$targetFile}</comment>");

        // 2. Eksekusi Backup (mysqldump binary atau PDO Fallback)
        $dumpBinary = $this->findMysqldumpBinary();
        $success = false;

        if ($dumpBinary && $connection === 'mysql') {
            $this->line("• Menggunakan Binary: <info>{$dumpBinary}</info>");
            $success = $this->backupViaMysqldump($dumpBinary, $dbHost, $dbPort, $dbUser, $dbPass, $dbName, $targetFile, $isCompressed);
        }

        // Jika mysqldump tidak ada atau gagal, gunakan PDO PHP Fallback
        if (!$success) {
            $this->line("• Menjalankan Backup melalui <info>PHP PDO Native Exporter</info>...");
            $success = $this->backupViaPdo($targetFile, $isCompressed);
        }

        if (!$success || !File::exists($targetFile) || File::size($targetFile) === 0) {
            $this->error("❌ Gagal membuat backup database!");
            Log::error("Database backup failed for database: {$dbName}");
            return Command::FAILURE;
        }

        $duration = round(microtime(true) - $startTime, 2);
        $fileSize = $this->formatBytes(File::size($targetFile));

        $this->info("✅ Backup database berhasil dibuat!");
        $this->table(
            ['Parameter', 'Keterangan'],
            [
                ['File Backup', $fileName],
                ['Ukuran File', $fileSize],
                ['Waktu Eksekusi', "{$duration} detik"],
                ['Lokasi', $targetFile],
            ]
        );

        Log::info("Database backup created successfully: {$fileName} ({$fileSize}) in {$duration}s");

        // 3. Pembersihan Otomatis File Backup Lama (Pruning)
        $keepDays = (int)$this->option('keep-days');
        if ($keepDays > 0) {
            $this->pruneOldBackups($backupDir, $keepDays);
        }

        $this->info("==================================================");
        return Command::SUCCESS;
    }

    /**
     * Cari lokasi mysqldump binary di sistem
     */
    protected function findMysqldumpBinary(): ?string
    {
        // Cek jika dispesifikasikan di env
        $envPath = env('MYSQLDUMP_PATH');
        if (!empty($envPath) && File::exists($envPath)) {
            return $envPath;
        }

        // Cek path umum Windows (XAMPP / MySQL)
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'D:\\xampp\\mysql\\bin\\mysqldump.exe',
            'E:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 10.5\\bin\\mysqldump.exe',
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
        ];

        foreach ($candidates as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        // Cek jika ada di sistem PATH
        try {
            $checkProcess = new Process(['mysqldump', '--version']);
            $checkProcess->run();
            if ($checkProcess->isSuccessful()) {
                return 'mysqldump';
            }
        } catch (Throwable $e) {
            // Ignore
        }

        return null;
    }

    /**
     * Jalankan mysqldump via Process
     */
    protected function backupViaMysqldump(
        string $binary,
        string $host,
        string $port,
        string $user,
        string $password,
        string $database,
        string $targetFile,
        bool $compress
    ): bool {
        try {
            $command = [
                $binary,
                "--host={$host}",
                "--port={$port}",
                "--user={$user}",
                "--single-transaction",
                "--quick",
                "--routines",
                "--triggers",
            ];

            if (!empty($password)) {
                $command[] = "--password={$password}";
            }

            $command[] = $database;

            $process = new Process($command);
            $process->setTimeout(600); // 10 menit batas waktu

            if ($compress) {
                // Tulis langsung ke file gzip via gzopen
                $gz = gzopen($targetFile, 'w9');
                if (!$gz) {
                    throw new \RuntimeException("Tidak dapat membuka file gzip tujuan: {$targetFile}");
                }

                $process->run(function ($type, $buffer) use ($gz) {
                    if ($type === Process::OUT) {
                        gzwrite($gz, $buffer);
                    }
                });

                gzclose($gz);
            } else {
                // Tulis langsung ke file .sql
                $fp = fopen($targetFile, 'wb');
                if (!$fp) {
                    throw new \RuntimeException("Tidak dapat membuka file sql tujuan: {$targetFile}");
                }

                $process->run(function ($type, $buffer) use ($fp) {
                    if ($type === Process::OUT) {
                        fwrite($fp, $buffer);
                    }
                });

                fclose($fp);
            }

            if (!$process->isSuccessful()) {
                $this->warn("mysqldump menghasilkan warning/error: " . $process->getErrorOutput());
                // Jika file kosong, kembalikan false agar fallback ke PDO
                if (!File::exists($targetFile) || File::size($targetFile) === 0) {
                    return false;
                }
            }

            return true;
        } catch (Throwable $e) {
            $this->warn("Eksekusi mysqldump gagal: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback backup database menggunakan PHP PDO native
     */
    protected function backupViaPdo(string $targetFile, bool $compress): bool
    {
        try {
            $pdo = DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();

            $handle = $compress ? gzopen($targetFile, 'w9') : fopen($targetFile, 'wb');
            if (!$handle) return false;

            $write = function (string $data) use ($handle, $compress) {
                if ($compress) {
                    gzwrite($handle, $data);
                } else {
                    fwrite($handle, $data);
                }
            };

            $write("-- ========================================================\n");
            $write("-- POSINDO DATABASE DUMP (PHP PDO EXPORTER)\n");
            $write("-- Tanggal: " . date('Y-m-d H:i:s') . "\n");
            $write("-- Driver : {$driver}\n");
            $write("-- ========================================================\n\n");

            if ($driver === 'mysql') {
                $write("SET FOREIGN_KEY_CHECKS=0;\n");
                $write("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
                $write("SET NAMES utf8mb4;\n\n");

                $tables = DB::select('SHOW TABLES');
                $keyName = 'Tables_in_' . DB::connection()->getDatabaseName();

                foreach ($tables as $tObj) {
                    $table = $tObj->$keyName ?? array_values((array)$tObj)[0];

                    $write("-- --------------------------------------------------------\n");
                    $write("-- Struktur Tabel: `{$table}`\n");
                    $write("-- --------------------------------------------------------\n");
                    $write("DROP TABLE IF EXISTS `{$table}`;\n");

                    $create = DB::select("SHOW CREATE TABLE `{$table}`");
                    if (!empty($create)) {
                        $createStatement = $create[0]->{'Create Table'} ?? array_values((array)$create[0])[1];
                        $write($createStatement . ";\n\n");
                    }

                    // Dump Data
                    $rows = DB::table($table)->get();
                    if ($rows->count() > 0) {
                        $write("-- Data untuk tabel `{$table}`\n");
                        foreach ($rows->chunk(200) as $chunk) {
                            $write("INSERT INTO `{$table}` VALUES \n");
                            $rowStatements = [];
                            foreach ($chunk as $row) {
                                $vals = array_map(function ($val) use ($pdo) {
                                    if ($val === null) return 'NULL';
                                    return $pdo->quote((string)$val);
                                }, (array)$row);
                                $rowStatements[] = "(" . implode(", ", $vals) . ")";
                            }
                            $write(implode(",\n", $rowStatements) . ";\n");
                        }
                        $write("\n");
                    }
                }

                $write("SET FOREIGN_KEY_CHECKS=1;\n");
            } elseif ($driver === 'sqlite') {
                $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
                foreach ($tables as $t) {
                    $write("DROP TABLE IF EXISTS `{$t->name}`;\n");
                    $write("{$t->sql};\n");

                    $rows = DB::table($t->name)->get();
                    foreach ($rows->chunk(200) as $chunk) {
                        foreach ($chunk as $row) {
                            $cols = array_keys((array)$row);
                            $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), (array)$row);
                            $write("INSERT INTO `{$t->name}` (`" . implode("`, `", $cols) . "`) VALUES (" . implode(", ", $vals) . ");\n");
                        }
                    }
                    $write("\n");
                }
            }

            if ($compress) {
                gzclose($handle);
            } else {
                fclose($handle);
            }

            return true;
        } catch (Throwable $e) {
            $this->error("PDO Dump Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Hapus file backup yang lebih tua dari batas hari yang ditentukan
     */
    protected function pruneOldBackups(string $backupDir, int $keepDays): void
    {
        $cutoff = now()->subDays($keepDays)->getTimestamp();
        $files = File::glob($backupDir . DIRECTORY_SEPARATOR . 'backup-*');

        $deletedCount = 0;
        foreach ($files as $file) {
            if (File::isFile($file) && File::lastModified($file) < $cutoff) {
                File::delete($file);
                $deletedCount++;
            }
        }

        if ($deletedCount > 0) {
            $this->comment("🧹 Pembersihan: {$deletedCount} file backup lama (> {$keepDays} hari) berhasil dihapus.");
        }
    }

    /**
     * Format byte menjadi KB / MB
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' Bytes';
    }
}