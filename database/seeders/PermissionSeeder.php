<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        // --- Create Roles ---
        $roles = [
            ['name' => 'superadmin', 'display_name' => 'Super Admin', 'description' => 'Akses penuh ke semua fitur sistem tanpa batasan.', 'is_system' => true],
            ['name' => 'admin', 'display_name' => 'Admin', 'description' => 'Mengelola semua konten, banom, dan pengaturan umum.', 'is_system' => true],
            ['name' => 'editor', 'display_name' => 'Editor', 'description' => 'Mengelola berita, kategori, agenda, dan multimedia.', 'is_system' => true],
            ['name' => 'redaksi', 'display_name' => 'Redaksi', 'description' => 'Mengelola dan mereview berita/post.', 'is_system' => true],
            ['name' => 'user', 'display_name' => 'User', 'description' => 'Pengguna biasa dengan akses terbatas.', 'is_system' => true],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(['name' => $roleData['name']], $roleData);
        }

        // --- Create Permissions ---
        $permissions = [
            // Konten
            ['name' => 'manage-posts', 'display_name' => 'Kelola Berita/Post', 'group' => 'Konten', 'description' => 'Membuat, mengedit, dan menghapus berita.'],
            ['name' => 'manage-categories', 'display_name' => 'Kelola Kategori', 'group' => 'Konten', 'description' => 'Membuat, mengedit, dan menghapus kategori.'],
            ['name' => 'manage-multimedia', 'display_name' => 'Kelola Multimedia', 'group' => 'Konten', 'description' => 'Membuat, mengedit, dan menghapus galeri multimedia.'],
            ['name' => 'manage-histories', 'display_name' => 'Kelola Sejarah', 'group' => 'Konten', 'description' => 'Membuat, mengedit, dan menghapus halaman sejarah.'],

            // Organisasi
            ['name' => 'manage-agendas', 'display_name' => 'Kelola Agenda', 'group' => 'Organisasi', 'description' => 'Membuat, mengedit, dan menghapus agenda kegiatan.'],
            ['name' => 'manage-banoms', 'display_name' => 'Kelola Banom', 'group' => 'Organisasi', 'description' => 'Membuat, mengedit, dan menghapus badan otonom.'],

            // Media & Promosi
            ['name' => 'manage-live-streams', 'display_name' => 'Kelola Live Stream', 'group' => 'Media & Promosi', 'description' => 'Mengelola siaran langsung/live streaming.'],
            ['name' => 'manage-ads', 'display_name' => 'Kelola Iklan', 'group' => 'Media & Promosi', 'description' => 'Mengelola slot dan konten iklan/advertisement.'],
            ['name' => 'manage-newsletters', 'display_name' => 'Kelola Newsletter', 'group' => 'Media & Promosi', 'description' => 'Mengelola daftar subscriber newsletter.'],

            // Sistem
            ['name' => 'view-dashboard', 'display_name' => 'Lihat Dashboard', 'group' => 'Sistem', 'description' => 'Melihat statistik dan dashboard admin.'],
            ['name' => 'manage-users', 'display_name' => 'Kelola Pengguna', 'group' => 'Sistem', 'description' => 'Membuat, mengedit, dan menghapus akun pengguna.'],
            ['name' => 'manage-settings', 'display_name' => 'Kelola Pengaturan', 'group' => 'Sistem', 'description' => 'Mengubah pengaturan situs dan konfigurasi sistem.'],
            ['name' => 'manage-roles', 'display_name' => 'Kelola Role & Permission', 'group' => 'Sistem', 'description' => 'Mengelola role dan hak akses pengguna.'],
            ['name' => 'upload-files', 'display_name' => 'Upload File', 'group' => 'Sistem', 'description' => 'Mengupload file gambar dan media.'],
        ];

        foreach ($permissions as $permData) {
            Permission::updateOrCreate(['name' => $permData['name']], $permData);
        }

        // --- Assign Default Permissions to Roles ---
        $rolePermissions = [
            'superadmin' => Permission::pluck('id')->toArray(), // All permissions
            'admin' => Permission::whereIn('name', [
                'manage-posts', 'manage-categories', 'manage-multimedia', 'manage-histories',
                'manage-agendas', 'manage-banoms',
                'manage-live-streams', 'manage-ads', 'manage-newsletters',
                'view-dashboard', 'manage-users', 'manage-settings', 'upload-files',
            ])->pluck('id')->toArray(),
            'editor' => Permission::whereIn('name', [
                'manage-posts', 'manage-categories', 'manage-multimedia',
                'manage-agendas', 'manage-live-streams', 'manage-ads',
                'view-dashboard', 'upload-files',
            ])->pluck('id')->toArray(),
            'redaksi' => Permission::whereIn('name', [
                'manage-posts', 'manage-ads',
                'view-dashboard', 'upload-files',
            ])->pluck('id')->toArray(),
            'user' => [],
        ];

        foreach ($rolePermissions as $roleName => $permIds) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->sync($permIds);
            }
        }
    }
}
