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
        try {
            $request->validate([
                'file' => 'required|file|mimes:zip|max:512000', // 500MB max
            ]);

            $uploadedFile = $request->file('file');
            $tempPath = $uploadedFile->store('temp', 'local');
            $zipPath = storage_path("app/private/{$tempPath}");

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
                return response()->json(['message' => 'File ZIP tidak valid atau rusak.'], 422);
            }

            // --- Validate manifest ---
            $manifestContent = $zip->getFromName('manifest.json');
            if (!$manifestContent) {
                $zip->close();
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
                return response()->json(['message' => 'File backup tidak valid: manifest.json tidak ditemukan.'], 422);
            }

            $manifest = json_decode($manifestContent, true);
            if (!$manifest || ($manifest['app'] ?? '') !== 'nulumbung') {
                $zip->close();
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
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
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
                throw $e; // Rethrow to outer catch
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
            if (Storage::disk('local')->exists($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }

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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal melakukan restore: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Preview a backup ZIP (read manifest without restoring).
     */
    public function preview(Request $request)
    {
        try {
            \Log::info('Backup preview started', [
                'file_exists' => $request->hasFile('file'),
                'file_size' => $request->file('file')?->getSize(),
                'memory_limit' => ini_get('memory_limit'),
                'upload_max' => ini_get('upload_max_filesize'),
            ]);

            $request->validate([
                'file' => 'required|file|mimes:zip|max:5242880', // 5GB limit in validation
            ]);

            $uploadedFile = $request->file('file');
            \Log::info('File validated', ['name' => $uploadedFile->getClientOriginalName()]);

            $tempPath = $uploadedFile->store('temp', 'local');
            $zipPath = storage_path("app/private/{$tempPath}");
            \Log::info('File stored', ['path' => $zipPath]);

            if (!file_exists($zipPath)) {
                return response()->json(['message' => 'File tidak tersimpan di server.'], 500);
            }

            $zip = new ZipArchive();
            $res = $zip->open($zipPath);
            \Log::info('Zip open result', ['result' => $res]);

            if ($res !== true) {
                if (Storage::disk('local')->exists($tempPath)) {
                    Storage::disk('local')->delete($tempPath);
                }
                return response()->json(['message' => 'File ZIP tidak valid atau tidak bisa dibuka (Error code: '.$res.').'], 422);
            }

            $manifestContent = $zip->getFromName('manifest.json');
            $zip->close();
            \Log::info('Manifest read', ['success' => (bool)$manifestContent]);
            
            if (Storage::disk('local')->exists($tempPath)) {
                Storage::disk('local')->delete($tempPath);
            }

            if (!$manifestContent) {
                return response()->json(['message' => 'Bukan file backup Nulumbung yang valid: manifest.json tidak ditemukan.'], 422);
            }

            $manifest = json_decode($manifestContent, true);
            if (!$manifest || ($manifest['app'] ?? '') !== 'nulumbung') {
                return response()->json(['message' => 'Bukan file backup Nulumbung yang valid: identitas aplikasi tidak cocok.'], 422);
            }

            return response()->json($manifest);
        } catch (\Exception $e) {
            \Log::error('Backup preview failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'message' => 'Gagal membaca file backup: ' . $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
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
