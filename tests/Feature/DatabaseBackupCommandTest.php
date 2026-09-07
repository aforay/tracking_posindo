<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DatabaseBackupCommandTest extends TestCase
{
    protected string $testBackupDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->testBackupDir = storage_path('backups_test');
        if (!File::exists($this->testBackupDir)) {
            File::makeDirectory($this->testBackupDir, 0755, true, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->testBackupDir)) {
            File::deleteDirectory($this->testBackupDir);
        }
        parent::tearDown();
    }

    public function test_database_backup_command_creates_sql_file(): void
    {
        $this->artisan('db:backup', [
            '--path' => $this->testBackupDir,
            '--keep-days' => 7,
        ])->assertExitCode(0);

        $files = File::glob($this->testBackupDir . DIRECTORY_SEPARATOR . 'backup-*.sql');
        $this->assertNotEmpty($files, 'File backup sql harus dibuat di direktori tujuan');
        $this->assertGreaterThan(0, File::size($files[0]), 'Ukuran file backup tidak boleh kosong');
    }

    public function test_database_backup_command_creates_gzip_compressed_file(): void
    {
        $this->artisan('db:backup', [
            '--compress' => true,
            '--path' => $this->testBackupDir,
            '--keep-days' => 7,
        ])->assertExitCode(0);

        $files = File::glob($this->testBackupDir . DIRECTORY_SEPARATOR . 'backup-*.sql.gz');
        $this->assertNotEmpty($files, 'File backup sql.gz harus dibuat ketika opsi --compress aktif');
        $this->assertGreaterThan(0, File::size($files[0]), 'Ukuran file backup gz tidak boleh kosong');
    }

    public function test_old_backups_are_pruned_automatically(): void
    {
        // Create dummy old backup file simulated from 10 days ago
        $oldFile = $this->testBackupDir . DIRECTORY_SEPARATOR . 'backup-dummy-old.sql';
        File::put($oldFile, '-- old backup');
        touch($oldFile, now()->subDays(10)->getTimestamp());

        $this->assertTrue(File::exists($oldFile));

        // Run backup with retention 5 days
        $this->artisan('db:backup', [
            '--path' => $this->testBackupDir,
            '--keep-days' => 5,
        ])->assertExitCode(0);

        // Old file must have been deleted
        $this->assertFalse(File::exists($oldFile), 'File backup yang lebih tua dari 5 hari harus terhapus');
    }
}