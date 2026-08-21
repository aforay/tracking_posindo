<?php
require __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$filePath = __DIR__ . '/../POS_INPROSES_DUMMY_2026.xlsx';
if (file_exists($filePath)) {
    $reader = IOFactory::createReaderForFile($filePath);
    $spreadsheet = $reader->load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    echo "DUMMY FILE HEADERS:\n";
    print_r($sheet->rangeToArray('A1:M3'));
}

$largeFile = __DIR__ . '/../POS INPROSES ZAHERBA 2026.xlsx';
if (file_exists($largeFile)) {
    $reader = IOFactory::createReaderForFile($largeFile);
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($largeFile);
    echo "LARGE FILE SHEETS:\n";
    print_r($spreadsheet->getSheetNames());
    $sheet = $spreadsheet->getSheet(0);
    echo "LARGE FILE SHEET 0 HEADERS:\n";
    print_r($sheet->rangeToArray('A1:M3'));
}
