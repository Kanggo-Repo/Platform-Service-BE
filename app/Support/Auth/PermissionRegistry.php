<?php

namespace App\Support\Auth;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PermissionRegistry
{
    public static function modules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Akses halaman ringkasan utama sistem.',
                'permissions' => [
                    self::permission('dashboard.view', 'Lihat dashboard', 'Melihat ringkasan dan kartu utama dashboard.', ['view']),
                ],
            ],
            'materials' => [
                'label' => 'Material',
                'description' => 'Database material, histori, dan utilitas material.',
                'permissions' => [
                    self::permission('materials.view', 'Lihat material', 'Melihat daftar, detail, dan utilitas baca material.', ['view']),
                    self::permission('materials.create', 'Tambah material', 'Menambah data material baru.', ['create', 'view'], ['materials.view']),
                    self::permission('materials.update', 'Ubah material', 'Mengubah data material dan restore histori.', ['update', 'view'], ['materials.view']),
                    self::permission('materials.delete', 'Hapus material', 'Menghapus data material.', ['delete', 'view'], ['materials.view']),
                    self::permission('materials.import', 'Import material', 'Menjalankan proses import material.', ['import', 'view'], ['materials.view']),
                    self::permission('materials.export', 'Export material', 'Mengambil data material keluar sistem.', ['export', 'view'], ['materials.view']),
                    self::permission('materials.recycle-bin.view', 'Lihat recycle bin material', 'Melihat daftar material yang dihapus di recycle bin.', ['view'], ['materials.view']),
                    self::permission('materials.recycle-bin.restore', 'Restore material dari recycle bin', 'Mengembalikan material yang dihapus dari recycle bin.', ['restore', 'view'], ['materials.recycle-bin.view']),
                    self::permission('materials.recycle-bin.delete', 'Hapus permanen material', 'Menghapus material secara permanen dari recycle bin.', ['delete', 'view'], ['materials.recycle-bin.view']),
                    self::permission('materials.manage', 'Kelola penuh material', 'Akses penuh untuk semua aksi material.', ['manage', 'view', 'create', 'update', 'delete', 'import', 'export'], [
                        'materials.view',
                        'materials.create',
                        'materials.update',
                        'materials.delete',
                        'materials.import',
                        'materials.export',
                        'materials.recycle-bin.view',
                        'materials.recycle-bin.restore',
                        'materials.recycle-bin.delete',
                    ]),
                ],
            ],
            'stores' => [
                'label' => 'Toko',
                'description' => 'Master toko, lokasi toko, dan data material per lokasi.',
                'permissions' => [
                    self::permission('stores.view', 'Lihat toko', 'Melihat daftar, detail, lokasi, dan material toko.', ['view']),
                    self::permission('stores.create', 'Tambah toko', 'Menambah toko dan lokasi baru.', ['create', 'view'], ['stores.view']),
                    self::permission('stores.update', 'Ubah toko', 'Mengubah toko, lokasi, dan data terkait.', ['update', 'view'], ['stores.view']),
                    self::permission('stores.delete', 'Hapus toko', 'Menghapus toko atau lokasi terkait.', ['delete', 'view'], ['stores.view']),
                    self::permission('stores.manage', 'Kelola penuh toko', 'Akses penuh untuk semua aksi toko.', ['manage', 'view', 'create', 'update', 'delete'], ['stores.view', 'stores.create', 'stores.update', 'stores.delete']),
                ],
            ],
            'work-items' => [
                'label' => 'Item Pekerjaan',
                'description' => 'Master item pekerjaan dan analitik item pekerjaan.',
                'permissions' => [
                    self::permission('work-items.view', 'Lihat item pekerjaan', 'Melihat daftar, detail, dan analytics item pekerjaan.', ['view']),
                    self::permission('work-items.create', 'Tambah item pekerjaan', 'Menambah item pekerjaan baru.', ['create', 'view'], ['work-items.view']),
                    self::permission('work-items.update', 'Ubah item pekerjaan', 'Mengubah item pekerjaan.', ['update', 'view'], ['work-items.view']),
                    self::permission('work-items.delete', 'Hapus item pekerjaan', 'Menghapus item pekerjaan.', ['delete', 'view'], ['work-items.view']),
                    self::permission('work-items.manage', 'Kelola penuh item pekerjaan', 'Akses penuh untuk semua aksi item pekerjaan.', ['manage', 'view', 'create', 'update', 'delete'], ['work-items.view', 'work-items.create', 'work-items.update', 'work-items.delete']),
                ],
            ],
            'calculations' => [
                'label' => 'Perhitungan',
                'description' => 'Form perhitungan material, draft, preview, dan hasil perhitungan.',
                'permissions' => [
                    self::permission('calculations.view', 'Lihat perhitungan', 'Melihat log, detail, preview, dan data baca perhitungan.', ['view']),
                    self::permission('calculations.create', 'Buat perhitungan', 'Membuka form create, preview, compare, trace, dan simpan draft baru.', ['create', 'view'], ['calculations.view']),
                    self::permission('calculations.update', 'Ubah perhitungan', 'Mengubah draft atau hasil perhitungan yang sudah ada.', ['update', 'view'], ['calculations.view']),
                    self::permission('calculations.delete', 'Hapus perhitungan', 'Menghapus draft atau hasil perhitungan.', ['delete', 'view'], ['calculations.view']),
                    self::permission('calculations.export', 'Export perhitungan', 'Export hasil perhitungan ke file luar sistem.', ['export', 'view'], ['calculations.view']),
                    self::permission('calculations.manage', 'Kelola penuh perhitungan', 'Akses penuh untuk semua aksi perhitungan.', ['manage', 'view', 'create', 'update', 'delete', 'export'], [
                        'calculations.view',
                        'calculations.create',
                        'calculations.update',
                        'calculations.delete',
                        'calculations.export',
                    ]),
                    self::permission('projects.view', 'Lihat proyek (legacy)', 'Kompatibilitas lama untuk akses baca proyek/perhitungan.', ['legacy', 'view']),
                    self::permission('projects.manage', 'Kelola proyek (legacy)', 'Kompatibilitas lama untuk akses kelola proyek/perhitungan.', ['legacy', 'manage', 'view', 'create', 'update', 'delete', 'export'], [
                        'projects.view',
                        'calculations.view',
                        'calculations.create',
                        'calculations.update',
                        'calculations.delete',
                        'calculations.export',
                    ]),
                ],
            ],
            'units' => [
                'label' => 'Satuan',
                'description' => 'Master satuan dan utilitas satuan material.',
                'permissions' => [
                    self::permission('units.view', 'Lihat satuan', 'Melihat daftar dan detail satuan.', ['view']),
                    self::permission('units.create', 'Tambah satuan', 'Menambah satuan baru.', ['create', 'view'], ['units.view']),
                    self::permission('units.update', 'Ubah satuan', 'Mengubah data satuan.', ['update', 'view'], ['units.view']),
                    self::permission('units.delete', 'Hapus satuan', 'Menghapus data satuan.', ['delete', 'view'], ['units.view']),
                    self::permission('units.manage', 'Kelola penuh satuan', 'Akses penuh untuk semua aksi satuan.', ['manage', 'view', 'create', 'update', 'delete'], ['units.view', 'units.create', 'units.update', 'units.delete']),
                ],
            ],
            'recommendations' => [
                'label' => 'Rekomendasi',
                'description' => 'Konfigurasi rekomendasi material.',
                'permissions' => [
                    self::permission('recommendations.view', 'Lihat rekomendasi', 'Melihat halaman pengaturan rekomendasi.', ['view']),
                    self::permission('recommendations.update', 'Ubah rekomendasi', 'Mengubah rule rekomendasi material.', ['update', 'view'], ['recommendations.view']),
                    self::permission('recommendations.manage', 'Kelola penuh rekomendasi', 'Akses penuh untuk pengaturan rekomendasi.', ['manage', 'view', 'update'], ['recommendations.view', 'recommendations.update']),
                ],
            ],
            'work-taxonomy' => [
                'label' => 'Taxonomy Pekerjaan',
                'description' => 'Master lantai, area, bidang, dan grouping taxonomy.',
                'permissions' => [
                    self::permission('work-taxonomy.view', 'Lihat taxonomy', 'Melihat halaman taxonomy pekerjaan.', ['view']),
                    self::permission('work-taxonomy.create', 'Tambah taxonomy', 'Menambah lantai, area, atau bidang baru.', ['create', 'view'], ['work-taxonomy.view']),
                    self::permission('work-taxonomy.update', 'Ubah taxonomy', 'Mengubah taxonomy pekerjaan.', ['update', 'view'], ['work-taxonomy.view']),
                    self::permission('work-taxonomy.delete', 'Hapus taxonomy', 'Menghapus taxonomy pekerjaan.', ['delete', 'view'], ['work-taxonomy.view']),
                    self::permission('work-taxonomy.manage', 'Kelola penuh taxonomy', 'Akses penuh untuk semua aksi taxonomy pekerjaan.', ['manage', 'view', 'create', 'update', 'delete'], ['work-taxonomy.view', 'work-taxonomy.create', 'work-taxonomy.update', 'work-taxonomy.delete']),
                ],
            ],
            'store-search-radius' => [
                'label' => 'Radius Pencarian Toko',
                'description' => 'Pengaturan radius pencarian toko.',
                'permissions' => [
                    self::permission('store-search-radius.view', 'Lihat radius toko', 'Melihat halaman setting radius pencarian toko.', ['view']),
                    self::permission('store-search-radius.update', 'Ubah radius toko', 'Mengubah radius pencarian toko.', ['update', 'view'], ['store-search-radius.view']),
                    self::permission('store-search-radius.manage', 'Kelola penuh radius toko', 'Akses penuh untuk setting radius pencarian toko.', ['manage', 'view', 'update'], ['store-search-radius.view', 'store-search-radius.update']),
                ],
            ],
            'roles' => [
                'label' => 'Roles',
                'description' => 'Manajemen role dan matriks permission.',
                'permissions' => [
                    self::permission('roles.view', 'Lihat roles', 'Melihat daftar role dan permission.', ['view']),
                    self::permission('roles.create', 'Tambah role', 'Membuat role baru.', ['create', 'view'], ['roles.view']),
                    self::permission('roles.update', 'Ubah role', 'Mengubah role dan permission.', ['update', 'view'], ['roles.view']),
                    self::permission('roles.delete', 'Hapus role', 'Menghapus role non inti.', ['delete', 'view'], ['roles.view']),
                    self::permission('roles.manage', 'Kelola penuh roles', 'Akses penuh untuk semua aksi roles.', ['manage', 'view', 'create', 'update', 'delete'], ['roles.view', 'roles.create', 'roles.update', 'roles.delete']),
                ],
            ],
            'users' => [
                'label' => 'Users',
                'description' => 'Manajemen user, role assignment, dan pengaturan registrasi.',
                'permissions' => [
                    self::permission('users.view', 'Lihat user', 'Melihat daftar user dan pengaturan registrasi.', ['view']),
                    self::permission('users.create', 'Tambah user', 'Membuat user baru.', ['create', 'view'], ['users.view']),
                    self::permission('users.update', 'Ubah user', 'Mengubah profil dan status user.', ['update', 'view'], ['users.view']),
                    self::permission('users.delete', 'Hapus user', 'Menghapus user.', ['delete', 'view'], ['users.view']),
                    self::permission('users.assign-roles', 'Assign role user', 'Menetapkan atau mengubah role user.', ['assign-roles', 'view', 'update'], ['users.view', 'users.update']),
                    self::permission('users.manage', 'Kelola penuh users', 'Akses penuh untuk semua aksi users.', ['manage', 'view', 'create', 'update', 'delete', 'assign-roles'], ['users.view', 'users.create', 'users.update', 'users.delete', 'users.assign-roles']),
                ],
            ],
            'workers' => [
                'label' => 'Pekerja',
                'description' => 'Akses data pekerja.',
                'permissions' => [
                    self::permission('workers.view', 'Lihat pekerja', 'Melihat daftar pekerja.', ['view']),
                ],
            ],
            'skills' => [
                'label' => 'Keahlian',
                'description' => 'Akses data skill/keahlian.',
                'permissions' => [
                    self::permission('skills.view', 'Lihat keahlian', 'Melihat daftar keahlian.', ['view']),
                ],
            ],
            'logs' => [
                'label' => 'Logs',
                'description' => 'Akses halaman log dan diagnostik web.',
                'permissions' => [
                    self::permission('logs.view', 'Lihat logs', 'Melihat log aplikasi.', ['view']),
                ],
            ],
            'settings' => [
                'label' => 'Settings Legacy',
                'description' => 'Permission kompatibilitas lama untuk pengaturan sistem.',
                'permissions' => [
                    self::permission('settings.manage', 'Kelola settings (legacy)', 'Kompatibilitas lama untuk seluruh pengaturan.', ['legacy', 'manage', 'view', 'update', 'create', 'delete'], [
                        'recommendations.view',
                        'recommendations.update',
                        'work-taxonomy.view',
                        'work-taxonomy.create',
                        'work-taxonomy.update',
                        'work-taxonomy.delete',
                        'store-search-radius.view',
                        'store-search-radius.update',
                        'roles.view',
                        'roles.create',
                        'roles.update',
                        'roles.delete',
                        'users.view',
                        'users.create',
                        'users.update',
                        'users.delete',
                        'users.assign-roles',
                    ]),
                ],
            ],
        ];
    }

    public static function all(): array
    {
        return array_values(self::flat()->keys()->all());
    }

    public static function definitions(): array
    {
        return self::flat()->all();
    }

    public static function grouped(): array
    {
        $groups = [];

        foreach (self::modules() as $moduleKey => $module) {
            $groups[] = [
                'key' => $moduleKey,
                'label' => $module['label'],
                'description' => $module['description'],
                'permissions' => array_map(function (array $permission) use ($moduleKey, $module) {
                    return [
                        'module' => $moduleKey,
                        'module_label' => $module['label'],
                        'module_description' => $module['description'],
                        'name' => $permission['name'],
                        'label' => $permission['label'],
                        'description' => $permission['description'],
                        'grants' => $permission['grants'],
                        'implies' => $permission['implies'] ?? [],
                    ];
                }, $module['permissions']),
            ];
        }

        return $groups;
    }

    public static function expand(array $selectedPermissions): array
    {
        $flat = self::flat();
        $resolved = [];
        $stack = array_values(array_unique($selectedPermissions));

        while ($stack !== []) {
            $permission = array_shift($stack);

            if (! is_string($permission) || $permission === '' || isset($resolved[$permission])) {
                continue;
            }

            $resolved[$permission] = true;

            foreach ($flat[$permission]['implies'] ?? [] as $impliedPermission) {
                if (! isset($resolved[$impliedPermission])) {
                    $stack[] = $impliedPermission;
                }
            }
        }

        return array_values(array_keys($resolved));
    }

    public static function displayModuleFromPermissionName(string $permissionName): string
    {
        $module = Str::before($permissionName, '.');

        if ($module === '') {
            return Str::headline($permissionName);
        }

        return self::definitions()[$permissionName]['module_label'] ?? Str::headline($module);
    }

    private static function permission(string $name, string $label, string $description, array $grants, array $implies = []): array
    {
        return [
            'name' => $name,
            'label' => $label,
            'description' => $description,
            'grants' => array_values($grants),
            'implies' => array_values($implies),
        ];
    }

    private static function flat(): Collection
    {
        return collect(self::modules())->flatMap(function (array $module, string $moduleKey) {
            return collect($module['permissions'])->mapWithKeys(function (array $permission) use ($module, $moduleKey) {
                return [
                    $permission['name'] => [
                        'module' => $moduleKey,
                        'module_label' => $module['label'],
                        'module_description' => $module['description'],
                        'name' => $permission['name'],
                        'label' => $permission['label'],
                        'description' => $permission['description'],
                        'grants' => $permission['grants'],
                        'implies' => $permission['implies'] ?? [],
                    ],
                ];
            });
        });
    }
}
