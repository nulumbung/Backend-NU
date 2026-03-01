<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;
use ZipArchive;

$tempPath = 'temp/test_backup.zip';
$zipPath = storage_path("app/private/{$tempPath}");

echo "Zip Path: " . $zipPath . "\n";
echo "Dir: " . dirname($zipPath) . "\n";

if (!is_dir(dirname($zipPath))) {
    mkdir(dirname($zipPath), 0755, true);
    echo "Created directory: " . dirname($zipPath) . "\n";
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    $zip->addFromString('manifest.json', json_encode(['app' => 'nulumbung', 'version' => '1.0']));
    $zip->close();
    echo "Successfully created test zip.\n";
} else {
    echo "Failed to create test zip.\n";
}

$zip = new ZipArchive();
if ($zip->open($zipPath) === TRUE) {
    $content = $zip->getFromName('manifest.json');
    echo "Content: " . $content . "\n";
    $zip->close();
} else {
    echo "Failed to open test zip.\n";
}

unlink($zipPath);
echo "Cleaned up.\n";
