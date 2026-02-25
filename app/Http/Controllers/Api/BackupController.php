<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * Tables to backup (application data only, excludes framework tables).
     */
    protected array $tables = [
        'users',
        'posts',
        'categories',
        'agendas',
        'banoms',
        'multimedia',
        'histories',
        'settings',
        'newsletters',
        'advertisements',
        'live_streams',
        'comments',
        'roles',
        'permissions',
        'role_permission',
        'user_login_devices',
    ];

    /**
     * Download a full backup as ZIP.
     */
    public function download()
    {
        $timestamp = now()->format('Y-m-d_His');
        $filename = "nulumbung_backup_{$timestamp}.zip";
        $zipPath = storage_path("app/private/{$filename}");

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json(['message' => 'Gagal membuat file backup.'], 500);
        }

        // --- Export database tables as JSON ---
        $manifest = [
            'app' => 'nulumbung',
            'version' => '1.0',
            'created_at' => now()->toIso8601String(),
            'created_by' => auth()->user()->email ?? 'system',
            'tables' => [],
            'files_count' => 0,
        ];

        foreach ($this->tables as $table) {
            try {
                if (!$this->tableExists($table)) {
                    continue;
                }

                $data = DB::table($table)->get()->toArray();
                $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $zip->addFromString("database/{$table}.json", $jsonContent);

                $manifest['tables'][$table] = count($data);
            } catch (\Exception $e) {
                // Skip tables that fail (may not exist yet)
                continue;
            }
        }

        // --- Include uploaded files ---
        $uploadPath = storage_path('app/public/uploads');
        if (is_dir($uploadPath)) {
            $files = $this->getFilesRecursive($uploadPath);
            foreach ($files as $file) {
                $relativePath = str_replace($uploadPath . DIRECTORY_SEPARATOR, '', $file);
                $relativePath = str_replace('\\', '/', $relativePath); // Normalize for ZIP
                $zip->addFile($file, "uploads/{$relativePath}");
                $manifest['files_count']++;
            }
        }

        // --- Add manifest ---
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        // Return as download and delete temp file after
        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Restore from a backup ZIP file.
     */
    public function restore(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:512000', // 500MB max
        ]);

        $uploadedFile = $request->file('file');
        $tempPath = $uploadedFile->store('temp', 'local');
        $zipPath = storage_path("app/private/{$tempPath}");

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            Storage::disk('local')->delete($tempPath);
            return response()->json(['message' => 'File ZIP tidak valid atau rusak.'], 422);
        }

        // --- Validate manifest ---
        $manifestContent = $zip->getFromName('manifest.json');
        if (!$manifestContent) {
            $zip->close();
            Storage::disk('local')->delete($tempPath);
            return response()->json(['message' => 'File backup tidak valid: manifest.json tidak ditemukan.'], 422);
        }

        $manifest = json_decode($manifestContent, true);
        if (!$manifest || ($manifest['app'] ?? '') !== 'nulumbung') {
            $zip->close();
            Storage::disk('local')->delete($tempPath);
            return response()->json(['message' => 'File backup tidak valid: bukan backup Nulumbung.'], 422);
        }

        // --- Restore database ---
        DB::beginTransaction();
        try {
            // Determine restore order (dependencies first)
            $restoreOrder = [
                'roles',
                'permissions',
                'role_permission',
                'users',
                'categories',
                'posts',
                'agendas',
                'banoms',
                'multimedia',
                'histories',
                'settings',
                'newsletters',
                'advertisements',
                'live_streams',
                'comments',
                'user_login_devices',
            ];

            // Disable FK checks during restore
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($restoreOrder as $table) {
                $jsonContent = $zip->getFromName("database/{$table}.json");
                if ($jsonContent === false) {
                    continue; // Table not in backup, skip
                }

                if (!$this->tableExists($table)) {
                    continue; // Table doesn't exist in current DB
                }

                $records = json_decode($jsonContent, true);
                if (!is_array($records)) {
                    continue;
                }

                // Truncate and re-insert
                DB::table($table)->truncate();

                // Insert in chunks to avoid memory issues
                foreach (array_chunk($records, 100) as $chunk) {
                    // Convert stdClass-like arrays to proper arrays
                    $insertData = array_map(function ($record) {
                        return (array) $record;
                    }, $chunk);

                    try {
                        DB::table($table)->insert($insertData);
                    } catch (\Exception $e) {
                        // Try one-by-one for problematic records
                        foreach ($insertData as $row) {
                            try {
                                DB::table($table)->insert($row);
                            } catch (\Exception $innerE) {
                                // Skip individual records that fail
                                continue;
                            }
                        }
                    }
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            try { DB::statement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Exception $ignored) {}
            $zip->close();
            Storage::disk('local')->delete($tempPath);
            return response()->json([
                'message' => 'Gagal restore database: ' . $e->getMessage(),
            ], 500);
        }

        // --- Restore uploaded files ---
        $uploadPath = storage_path('app/public/uploads');
        $filesRestored = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (strpos($entryName, 'uploads/') === 0 && substr($entryName, -1) !== '/') {
                $relativePath = substr($entryName, strlen('uploads/'));
                $destPath = $uploadPath . '/' . $relativePath;

                // Ensure directory exists
                $dir = dirname($destPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    file_put_contents($destPath, $content);
                    $filesRestored++;
                }
            }
        }

        $zip->close();
        Storage::disk('local')->delete($tempPath);

        $tablesRestored = count(array_filter($manifest['tables'] ?? [], fn($count) => $count > 0));

        return response()->json([
            'message' => 'Restore berhasil!',
            'details' => [
                'tables_restored' => $tablesRestored,
                'files_restored' => $filesRestored,
                'backup_date' => $manifest['created_at'] ?? null,
                'backup_by' => $manifest['created_by'] ?? null,
            ],
        ]);
    }

    /**
     * Preview a backup ZIP (read manifest without restoring).
     */
    public function preview(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:zip|max:512000',
        ]);

        $uploadedFile = $request->file('file');
        $tempPath = $uploadedFile->store('temp', 'local');
        $zipPath = storage_path("app/private/{$tempPath}");

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            Storage::disk('local')->delete($tempPath);
            return response()->json(['message' => 'File ZIP tidak valid.'], 422);
        }

        $manifestContent = $zip->getFromName('manifest.json');
        $zip->close();
        Storage::disk('local')->delete($tempPath);

        if (!$manifestContent) {
            return response()->json(['message' => 'Bukan file backup Nulumbung yang valid.'], 422);
        }

        $manifest = json_decode($manifestContent, true);
        if (!$manifest || ($manifest['app'] ?? '') !== 'nulumbung') {
            return response()->json(['message' => 'Bukan file backup Nulumbung yang valid.'], 422);
        }

        return response()->json($manifest);
    }

    /**
     * Check if a table exists in the database.
     */
    private function tableExists(string $table): bool
    {
        try {
            DB::select("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all files recursively from a directory.
     */
    private function getFilesRecursive(string $dir): array
    {
        $files = [];
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $files = array_merge($files, $this->getFilesRecursive($path));
            } else {
                $files[] = $path;
            }
        }
        return $files;
    }
}
