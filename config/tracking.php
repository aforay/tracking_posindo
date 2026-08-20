<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ukuran Chunk Import
    |--------------------------------------------------------------------------
    |
    | Jumlah baris Excel yang dibaca & disimpan per job antrean. Nilai kecil
    | menjaga pemakaian memori tetap stabil saat memproses 40.000+ baris.
    |
    */

    'import_chunk_size' => (int) env('TRACKING_IMPORT_CHUNK_SIZE', 500),

    /*
    |--------------------------------------------------------------------------
    | Ukuran Chunk Tracking Bot
    |--------------------------------------------------------------------------
    |
    | Jumlah resi yang dilacak bot dalam satu job. Setiap job membuka dan
    | menutup satu instance browser headless.
    |
    */

    'track_chunk_size' => (int) env('TRACKING_TRACK_CHUNK_SIZE', 25),

    /*
    |--------------------------------------------------------------------------
    | Nama Antrean
    |--------------------------------------------------------------------------
    */

    'queue' => env('TRACKING_QUEUE', 'tracking'),

    /*
    |--------------------------------------------------------------------------
    | Batas Tracking Mode Sinkron
    |--------------------------------------------------------------------------
    |
    | Saat import dijalankan tanpa antrean, hanya sebanyak ini resi yang
    | dilacak agar request tidak timeout.
    |
    */

    'sync_track_limit' => (int) env('TRACKING_SYNC_LIMIT', 100),

];
