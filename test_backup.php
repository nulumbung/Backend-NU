<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Running backup...\n";
    $exitCode = \Illuminate\Support\Facades\Artisan::call('backup:run', ['--only-db' => false]);
    echo "Artisan call exit code: " . $exitCode . "\n";
    echo \Illuminate\Support\Facades\Artisan::output();
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
