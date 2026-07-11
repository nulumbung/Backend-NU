<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class BackupController extends Controller
{
    /**
     * Disk used to store backup files. Disk root = storage/app/private
     * (see config/filesystems.php -> 'local').
     */
    private const DISK = 'local';

    /**
     * Prefix & extension used to identify backup files created by this app.
     */
    private const FILE_PREFIX = 'nulumbung_backup_';
    private const FILE_EXT = '.zip';

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
     * Order in which tables must be restored (dependencies first).
     */
    protected array $restoreOrder = [
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

    /**
     * GET /api/backup
     * List available backup files.
     */
    public function index(): JsonResponse
    {
        try {
            $this->ensureBackupDirectoryExists();

            $files = collect(Storage::disk(self::DISK)->files())
                ->filter(fn (string $path) => $this->isBackupFile($path))
                ->map(function (string $path) {
                    $fileName = basename($path);

                    return [
                        'file_name'     => $fileName,
                        'file_size'     => $this->formatBytes(Storage::disk(self::DISK)->size($path)),
                        'created_at'    => Carbon::createFromTimestamp(
                            Storage::disk(self::DISK)->lastModified($path)
                        )->format('Y-m-d H:i:s'),
                        'download_link' => url('/api/backup/download/' . rawurlencode($fileName)),
                    ];
                })
                ->sortByDesc('created_at')
                ->values();

            return response()->json($files);
        } catch (Throwable $e) {
            Log::error('Backup index failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal mengambil daftar backup.',
            ], 500);
        }
    }

    /**
     * POST /api/backup
     * Create a new backup ZIP file and store it to storage/app/private.
     * Returns JSON only (never a download response).
     */
    public function store(Request $request): JsonResponse
    {
        $fileName = self::FILE_PREFIX . now()->format('Y-m-d_His') . self::FILE_EXT;

        Log::info('Backup creation started', ['file_name' => $fileName]);

        try {
            $this->ensureBackupDirectoryExists();

            $zipPath = Storage::disk(self::DISK)->path($fileName);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                Log::error('Backup creation failed: unable to open zip', ['file_name' => $fileName]);

                return response()->json(['message' => 'Gagal membuat file backup.'], 500);
            }

            $manifest = [
                'app'         => 'nulumbung',
                'version'     => '1.0',
                'created_at'  => now()->toIso8601String(),
                'created_by'  => $this->currentUserIdentifier($request),
                'tables'      => [],
                'files_count' => 0,
            ];

            foreach ($this->tables as $table) {
                try {
                    if (!$this->tableExists($table)) {
                        continue;
                    }

                    $data = DB::table($table)->get()->toArray();
                    $jsonContent = json_encode(
                        $data,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
                    $zip->addFromString("database/{$table}.json", $jsonContent);

                    $manifest['tables'][$table] = count($data);
                } catch (Throwable $e) {
                    Log::error('Backup: failed to export table', [
                        'table' => $table,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $uploadPath = storage_path('app/public/uploads');
            if (is_dir($uploadPath)) {
                foreach ($this->getFilesRecursive($uploadPath) as $file) {
                    $relativePath = str_replace($uploadPath . DIRECTORY_SEPARATOR, '', $file);
                    $relativePath = str_replace('\\', '/', $relativePath);
                    $zip->addFile($file, "uploads/{$relativePath}");
                    $manifest['files_count']++;
                }
            }

            $zip->addFromString(
                'manifest.json',
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            $zip->close();

            Log::info('Backup creation finished', [
                'file_name'   => $fileName,
                'tables'      => count($manifest['tables']),
                'files_count' => $manifest['files_count'],
            ]);

            return response()->json([
                'message'   => 'Backup berhasil dibuat.',
                'file_name' => $fileName,
            ]);
        } catch (Throwable $e) {
            Log::error('Backup creation failed', [
                'file_name' => $fileName,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal membuat file backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/backup/download/{fileName}
     * Download a previously created backup. Never generates a new backup.
     */
    public function download(string $fileName): StreamedResponse|JsonResponse
    {
        try {
            $fileName = basename($fileName); // prevent path traversal

            if (!$this->isBackupFile($fileName) || !Storage::disk(self::DISK)->exists($fileName)) {
                Log::error('Backup download failed: file not found', ['file_name' => $fileName]);

                return response()->json(['message' => 'File backup tidak ditemukan.'], 404);
            }

            Log::info('Backup download requested', ['file_name' => $fileName]);

            return Storage::disk(self::DISK)->download($fileName);
        } catch (Throwable $e) {
            Log::error('Backup download failed', [
                'file_name' => $fileName,
                'error'     => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Gagal mengunduh file backup.'], 500);
        }
    }

    /**
     * DELETE /api/backup/{fileName}
     * Delete a backup file.
     */
    public function destroy(string $fileName): JsonResponse
    {
        $fileName = basename($fileName); // prevent path traversal

        try {
            if (!$this->isBackupFile($fileName) || !Storage::disk(self::DISK)->exists($fileName)) {
                Log::error('Backup delete failed: file not found', ['file_name' => $fileName]);

                return response()->json(['message' => 'File backup tidak ditemukan.'], 404);
            }

            Storage::disk(self::DISK)->delete($fileName);

            Log::info('Backup deleted', ['file_name' => $fileName]);

            return response()->json([
                'message' => 'Backup berhasil dihapus.',
            ]);
        } catch (Throwable $e) {
            Log::error('Backup delete failed', [
                'file_name' => $fileName,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json(['message' => 'Gagal menghapus file backup.'], 500);
        }
    }

    /**
     * POST /api/backup/restore
     * Restore application data & files from an uploaded backup ZIP.
     */
    public function restore(Request $request): JsonResponse
    {
        $tempPath = null; // only set (and cleaned up) when restoring from an uploaded file

        Log::info('Backup restore started', [
            'mode' => $request->hasFile('file') ? 'uploaded_file' : 'server_file_name',
        ]);

        try {
            $request->validate([
                'file_name' => 'nullable|string',
                'file'      => 'nullable|file|mimes:zip|max:512000', // 500MB max
            ]);

            if (!$request->hasFile('file') && !$request->filled('file_name')) {
                Log::error('Backup restore failed: neither file nor file_name provided');

                return response()->json(['message' => 'Tidak ada file backup yang dipilih untuk restore.'], 422);
            }

            if ($request->hasFile('file')) {
                // Restore from a freshly uploaded ZIP.
                $uploadedFile = $request->file('file');
                $tempPath = $uploadedFile->store('temp', self::DISK);
                // FIX: use the disk's own path resolver instead of hardcoding
                // storage_path("app/private/{$tempPath}") which is fragile and
                // breaks if the disk root ever changes.
                $zipPath = Storage::disk(self::DISK)->path($tempPath);
            } else {
                // Restore from a backup file that already exists on the server.
                $fileName = basename($request->input('file_name'));

                if (!$this->isBackupFile($fileName) || !Storage::disk(self::DISK)->exists($fileName)) {
                    Log::error('Backup restore failed: server file not found', ['file_name' => $fileName]);

                    return response()->json(['message' => 'File backup tidak ditemukan di server.'], 404);
                }

                $zipPath = Storage::disk(self::DISK)->path($fileName);
            }

            $zip = new ZipArchive();
            if ($zip->open($zipPath) !== true) {
                Log::error('Backup restore failed: invalid zip', ['temp_path' => $tempPath]);
                $this->cleanupTemp($tempPath);

                return response()->json(['message' => 'File ZIP tidak valid atau rusak.'], 422);
            }

            $manifestContent = $zip->getFromName('manifest.json');
            if (!$manifestContent) {
                $zip->close();
                Log::error('Backup restore failed: manifest.json missing', ['temp_path' => $tempPath]);
                $this->cleanupTemp($tempPath);

                return response()->json(['message' => 'File backup tidak valid: manifest.json tidak ditemukan.'], 422);
            }

            $manifest = json_decode($manifestContent, true);
            if (!$manifest || ($manifest['app'] ?? '') !== 'nulumbung') {
                $zip->close();
                Log::error('Backup restore failed: manifest identity mismatch', ['temp_path' => $tempPath]);
                $this->cleanupTemp($tempPath);

                return response()->json(['message' => 'File backup tidak valid: bukan backup Nulumbung.'], 422);
            }

            // NOTE: we deliberately do NOT wrap this in DB::beginTransaction()/commit().
            // TRUNCATE is a DDL statement in MySQL, and DDL always triggers an
            // implicit commit — even inside an explicit transaction. That silently
            // ends the transaction on the first truncate(), so the later DB::commit()
            // fails with "There is no active transaction". A transaction here would
            // be a no-op anyway, so we skip it and just make sure FK checks are
            // always restored via try/finally.
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=0');

                foreach ($this->restoreOrder as $table) {
                    $jsonContent = $zip->getFromName("database/{$table}.json");
                    if ($jsonContent === false || !$this->tableExists($table)) {
                        continue;
                    }

                    $records = json_decode($jsonContent, true);
                    if (!is_array($records)) {
                        continue;
                    }

                    DB::table($table)->truncate();

                    foreach (array_chunk($records, 100) as $chunk) {
                        $insertData = array_map(fn ($record) => (array) $record, $chunk);

                        try {
                            DB::table($table)->insert($insertData);
                        } catch (Throwable $e) {
                            Log::error('Backup restore: bulk insert failed, retrying row by row', [
                                'table' => $table,
                                'error' => $e->getMessage(),
                            ]);

                            foreach ($insertData as $row) {
                                try {
                                    DB::table($table)->insert($row);
                                } catch (Throwable $innerE) {
                                    Log::error('Backup restore: failed to insert row', [
                                        'table' => $table,
                                        'error' => $innerE->getMessage(),
                                        'row'   => $row,
                                    ]);
                                }
                            }
                        }
                    }
                }
            } catch (Throwable $e) {
                $zip->close();
                $this->cleanupTemp($tempPath);

                throw $e;
            } finally {
                try {
                    DB::statement('SET FOREIGN_KEY_CHECKS=1');
                } catch (Throwable $ignored) {
                    Log::error('Backup restore: failed to re-enable FK checks', ['error' => $ignored->getMessage()]);
                }
            }

            $uploadPath = storage_path('app/public/uploads');
            $filesRestored = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (str_starts_with($entryName, 'uploads/') && !str_ends_with($entryName, '/')) {
                    $relativePath = substr($entryName, strlen('uploads/'));
                    $destPath = $uploadPath . '/' . $relativePath;

                    File::ensureDirectoryExists(dirname($destPath));

                    $content = $zip->getFromIndex($i);
                    if ($content !== false) {
                        file_put_contents($destPath, $content);
                        $filesRestored++;
                    }
                }
            }

            $zip->close();
            $this->cleanupTemp($tempPath);

            $tablesRestored = count(array_filter($manifest['tables'] ?? [], fn ($count) => $count > 0));

            Log::info('Backup restore finished', [
                'tables_restored' => $tablesRestored,
                'files_restored'  => $filesRestored,
            ]);

            return response()->json([
                'message' => 'Restore berhasil!',
                'details' => [
                    'tables_restored' => $tablesRestored,
                    'files_restored'  => $filesRestored,
                    'backup_date'     => $manifest['created_at'] ?? null,
                    'backup_by'       => $manifest['created_by'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            $this->cleanupTemp($tempPath);

            Log::error('Backup restore failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal melakukan restore: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/backup/preview
     * Read manifest.json from an uploaded ZIP without restoring anything.
     */
    public function preview(Request $request): JsonResponse
    {
        $tempPath = null;

        Log::info('Backup preview started', [
            'has_file' => $request->hasFile('file'),
            'file_size' => $request->file('file')?->getSize(),
        ]);

        try {
            $request->validate([
                'file' => 'required|file|mimes:zip|max:512000',
            ]);

            $uploadedFile = $request->file('file');
            $tempPath = $uploadedFile->store('temp', self::DISK);
            // FIX: same disk-path issue as restore() above.
            $zipPath = Storage::disk(self::DISK)->path($tempPath);

            if (!file_exists($zipPath)) {
                Log::error('Backup preview failed: temp file missing after store', ['temp_path' => $tempPath]);

                return response()->json(['message' => 'File tidak tersimpan di server.'], 500);
            }

            $zip = new ZipArchive();
            $result = $zip->open($zipPath);

            if ($result !== true) {
                Log::error('Backup preview failed: cannot open zip', ['temp_path' => $tempPath, 'code' => $result]);
                $this->cleanupTemp($tempPath);

                return response()->json([
                    'message' => "File ZIP tidak valid atau tidak bisa dibuka (Error code: {$result}).",
                ], 422);
            }

            $manifestContent = $zip->getFromName('manifest.json');
            $zip->close();
            $this->cleanupTemp($tempPath);

            if (!$manifestContent) {
                Log::error('Backup preview failed: manifest.json missing');

                return response()->json(['message' => 'Bukan file backup Nulumbung yang valid: manifest.json tidak ditemukan.'], 422);
            }

            $manifest = json_decode($manifestContent, true);
            if (!$manifest || ($manifest['app'] ?? '') !== 'nulumbung') {
                Log::error('Backup preview failed: manifest identity mismatch');

                return response()->json(['message' => 'Bukan file backup Nulumbung yang valid: identitas aplikasi tidak cocok.'], 422);
            }

            Log::info('Backup preview finished', ['tables' => array_keys($manifest['tables'] ?? [])]);

            return response()->json($manifest);
        } catch (Throwable $e) {
            $this->cleanupTemp($tempPath);

            Log::error('Backup preview failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Gagal membaca file backup: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ensure the private backup directory exists before writing any file.
     */
    private function ensureBackupDirectoryExists(): void
    {
        File::ensureDirectoryExists(Storage::disk(self::DISK)->path(''));
    }

    /**
     * Safely resolve a "created by" identifier without risking null property access.
     */
    private function currentUserIdentifier(Request $request): string
    {
        return $request->user()?->email ?? 'system';
    }

    /**
     * Delete the temporary uploaded file, logging any failure.
     */
    private function cleanupTemp(?string $tempPath): void
    {
        if (!$tempPath) {
            return;
        }

        try {
            if (Storage::disk(self::DISK)->exists($tempPath)) {
                Storage::disk(self::DISK)->delete($tempPath);
            }
        } catch (Throwable $e) {
            Log::error('Backup: failed to cleanup temp file', [
                'temp_path' => $tempPath,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a given path/filename matches our backup file naming convention.
     */
    private function isBackupFile(string $path): bool
    {
        $fileName = basename($path);

        return str_starts_with($fileName, self::FILE_PREFIX)
            && str_ends_with($fileName, self::FILE_EXT);
    }

    /**
     * Check if a table exists / is queryable in the database.
     */
    private function tableExists(string $table): bool
    {
        try {
            DB::select("SELECT 1 FROM `{$table}` LIMIT 1");

            return true;
        } catch (Throwable $e) {
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
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->getFilesRecursive($path));
            } else {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Format bytes into a human readable string, e.g. "3.5 MB".
     */
    private function formatBytes(int $bytes, int $decimals = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return round($bytes / (1024 ** $power), $decimals) . ' ' . $units[$power];
    }
}