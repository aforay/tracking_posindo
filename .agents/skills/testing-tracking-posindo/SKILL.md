---
name: testing-tracking-posindo
description: How to run and end-to-end test the Laravel tracking_posindo dashboard locally (import via queue batches, smart filtering, mock NIPOS bot).
---

# Testing tracking_posindo locally

## Running the app
- No auth: the dashboard is at `http://127.0.0.1:8000/` with no login.
- Start web: `php artisan serve --host=127.0.0.1 --port=8000`
- Start worker (required for the queued/background import): `php artisan queue:work --queue=tracking,default --tries=3 --timeout=1800`
- Queue/DB: SQLite (`database/database.sqlite`), `QUEUE_CONNECTION=database`. `sqlite3` CLI is usually NOT installed —
  query the DB with a bootstrap script instead:
  `php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); ... DB::table("shipments")...'`

## Tracking bot / Chrome
- `TrackingBotService` uses Symfony Panther and `drivers/chromedriver.exe` (a Windows binary). On Linux it fails with
  "Could not start chrome. Exit code: 126" and silently falls back to **simulated** results (usually `DELIVERED`).
  Simulation returns instantly, so batches finish in seconds — good for testing the queue plumbing, but any assertion about
  *real* NIPOS statuses is untestable without a Linux chromedriver (set `PANTHER_CHROME_DRIVER_BINARY` to one to test for real).
- `last_scanned_at` (non-NULL) is the reliable signal that a shipment was actually run through the bot.

## Building import fixtures
Column layout is A..M with **E = RESI (index 4)** and **L = STATUS (index 11)**. CSV works everywhere
(PhpSpreadsheet Csv reader supports `listWorksheetInfo` and read filters; sheet name is `Worksheet`).
Generate with a small `php -r` + `fputcsv` script; ~1200 rows imports in <1s per chunk job.

## Queued import specifics
- Checkbox `background` ("Proses di Latar Belakang (Antrean)") is checked by default; import chunk size from
  `config/tracking.php` (`TRACKING_IMPORT_CHUNK_SIZE`, default 500).
- After submit the URL carries `?batch=<uuid>`; the page polls `GET /import-status/{batchId}` every 3s and reloads when finished.
- `job_batches.total_jobs` = ceil(rows/500) import jobs **plus** tracking jobs added later (25 resi each) — don't assert on a fixed number.
- The copied source file lands in `storage/app/imports` and is deleted in the batch `finally()`; leftovers there mean a batch never finished.

## Known gotchas
- `SpreadsheetRowMapper::map` stores a **blank** file status as `DELIVERED`, so `Shipment::needsTracking()` excludes those rows:
  rows with empty status may never be tracked even with the "Update Semua yang Belum DELIVERED" filter. Verify explicitly.
- The "Download Excel Bulan ..." button may alert `SyntaxError: Unexpected token 'P', "PK..." is not valid JSON` — the frontend
  parses the xlsx response as JSON. This exists on `main` too; the backend export itself works (covered by `php artisan test`).
- Typing a URL with `&` via keyboard automation into Chrome's omnibox can lose the `&`; prefer clicking the month nav links.

## Devin Secrets Needed
None — everything runs locally with no credentials.
