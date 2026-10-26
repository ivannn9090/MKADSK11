<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * RolesSeeder — isi data dummy role + permissions.
 *
 * Jalankan: php spark db:seed RolesSeeder
 */
class RolesSeeder extends Seeder
{
    /** Daftar semua permission yang tersedia di sistem */
    public const ALL_PERMISSIONS = [
        // ── Kegiatan (On Budget & Off Budget) ──────────────────
        'kegiatan.view'         => 'Lihat Kegiatan',
        'kegiatan.create'       => 'Buat Kegiatan',
        'kegiatan.edit'         => 'Edit Kegiatan',
        'kegiatan.delete'       => 'Hapus Kegiatan',
        // ── Sub-Kegiatan / Task ────────────────────────────────
        'task.view'             => 'Lihat Sub-Kegiatan',
        'task.create'           => 'Buat Sub-Kegiatan',
        'task.edit'             => 'Edit Sub-Kegiatan',
        'task.delete'           => 'Hapus Sub-Kegiatan',
        'task.toggle'           => 'Ubah Status Sub-Kegiatan',
        // ── Anggaran ───────────────────────────────────────────
        'anggaran.view'         => 'Lihat Anggaran',
        'anggaran.input'        => 'Input Realisasi Anggaran',
        'anggaran.edit'         => 'Edit Realisasi Anggaran',
        'anggaran.delete'       => 'Hapus Realisasi Anggaran',
        'rincian.manage'        => 'Kelola Rincian Anggaran',
        'off_budget.view'       => 'Lihat Off Budget',
        'off_budget.manage'     => 'Kelola Sumber Off Budget',
        // ── RPD & RO ───────────────────────────────────────────
        'rpd.view'              => 'Lihat RPD',
        'rpd.input'             => 'Input RPD',
        'rpd.edit'              => 'Edit / Input RPD',
        'ro.view'               => 'Lihat Rumpun Output',
        'ro.edit'               => 'Edit Rumpun Output',
        'ro.delete'             => 'Hapus Rumpun Output',
        'ro.assign'             => 'Assign Kegiatan ke RO',
        // ── Laporan ────────────────────────────────────────────
        'laporan.view'          => 'Lihat Laporan',
        'laporan.download'      => 'Download Laporan',
        // ── Pengguna & Tim ─────────────────────────────────────
        'users.view'            => 'Lihat Pengguna',
        'users.manage'          => 'Kelola Pengguna',
        'teams.view'            => 'Lihat Tim',
        'teams.manage'          => 'Kelola Tim',
        // ── Sistem ─────────────────────────────────────────────
        'audit.view'            => 'Lihat Audit Trail',
        'settings.manage'       => 'Kelola Pengaturan',
        // ── SERASI ─────────────────────────────────────────────
        'serasi.view'           => 'Lihat SERASI',
        'serasi.manage'         => 'Kelola SERASI',
        // ── Schedule ───────────────────────────────────────────
        'schedule.view'         => 'Lihat Schedule',
        'schedule.manage'       => 'Kelola Schedule',
        // ── Telaah Konsep (LTK) ────────────────────────────────
        'ltk.view'              => 'Lihat Daftar Permohonan',
        'ltk.input'             => 'Input Permohonan dari SRIKANDI',
        'ltk.verifikasi'        => 'Verifikasi & Kelola Permohonan',
        'ltk.antrian'           => 'Kelola Antrian Telaah',
        'ltk.tugaskan'          => 'Tugaskan PIC Telaah',
        'ltk.pic'               => 'Terima & Kerjakan Tugas Telaah',
        'ltk.laporan'           => 'Lihat Laporan Telaah',
        'ltk.admin'             => 'Pengaturan Sistem Telaah',
    ];

    private array $roles = [
        [
            'slug'      => 'direktur',
            'label'     => 'Direktur',
            'warna'     => '#1e40af',
            'is_system' => 1,
            'perms'     => ['*'],   // semua permission
        ],
        [
            'slug'      => 'ketua_tim',
            'label'     => 'Ketua Tim',
            'warna'     => '#16a34a',
            'is_system' => 1,
            'perms'     => [
                'kegiatan.view','kegiatan.edit',
                'task.view','task.create','task.edit','task.delete','task.toggle',
                'anggaran.view','anggaran.input','anggaran.edit',
                'off_budget.view',
                'rpd.view',
                'ro.view',
                'laporan.view','laporan.download',
                'teams.view',
                'schedule.view','schedule.manage',
                'serasi.view',
            ],
        ],
        [
            'slug'      => 'anggota_tim',
            'label'     => 'Anggota Tim',
            'warna'     => '#0891b2',
            'is_system' => 1,
            'perms'     => [
                'kegiatan.view',
                'task.view','task.create','task.edit','task.toggle',
                'anggaran.view','anggaran.input',
                'off_budget.view',
                'rpd.view',
                'ro.view',
                'laporan.view',
                'schedule.view',
                'serasi.view',
            ],
        ],
        [
            'slug'      => 'perencana_anggaran',
            'label'     => 'Perencana Anggaran',
            'warna'     => '#d97706',
            'is_system' => 1,
            'perms'     => [
                'kegiatan.view','kegiatan.create','kegiatan.edit',
                'task.view',
                'anggaran.view','anggaran.input','anggaran.edit',
                'rincian.manage',
                'off_budget.view','off_budget.manage',
                'rpd.view','rpd.input','rpd.edit',
                'ro.view','ro.edit','ro.assign',
                'laporan.view','laporan.download',
                'schedule.view',
            ],
        ],
        [
            'slug'      => 'tu',
            'label'     => 'Tata Usaha',
            'warna'     => '#dc2626',
            'is_system' => 1,
            'perms'     => [
                'kegiatan.view','kegiatan.create','kegiatan.edit',
                'task.view',
                'anggaran.view',
                'off_budget.view',
                'rpd.view','rpd.edit',
                'ro.view','ro.assign',
                'laporan.view',
                'users.view','users.manage',
                'teams.view','teams.manage',
                'schedule.view','schedule.manage',
                'serasi.view',
                // LTK — TU mengelola seluruh alur telaah
                'ltk.view','ltk.input','ltk.verifikasi','ltk.antrian','ltk.tugaskan','ltk.laporan',
            ],
        ],
        // ── Role Dummy tambahan ──────────────────────────────────
        [
            'slug'      => 'supervisor',
            'label'     => 'Supervisor',
            'warna'     => '#7c3aed',
            'is_system' => 0,
            'perms'     => [
                'kegiatan.view','kegiatan.edit',
                'task.view','task.edit','task.toggle',
                'anggaran.view',
                'laporan.view','laporan.download',
                'teams.view',
            ],
        ],
        [
            'slug'      => 'auditor',
            'label'     => 'Auditor',
            'warna'     => '#475569',
            'is_system' => 0,
            'perms'     => [
                'kegiatan.view',
                'task.view',
                'anggaran.view',
                'rincian.manage',
                'laporan.view','laporan.download',
                'audit.view',
            ],
        ],
        [
            'slug'      => 'staf_keuangan',
            'label'     => 'Staf Keuangan',
            'warna'     => '#059669',
            'is_system' => 0,
            'perms'     => [
                'kegiatan.view',
                'anggaran.view','anggaran.input','anggaran.edit',
                'rincian.manage',
                'rpd.view','rpd.input',
                'laporan.view','laporan.download',
            ],
        ],
        [
            'slug'      => 'pic_telaah',
            'label'     => 'PIC Telaah Konsep',
            'warna'     => '#0891b2',
            'is_system' => 1,
            'perms'     => [
                'schedule.view',
                'serasi.view',
                // LTK — PIC mengerjakan tugas telaah
                'ltk.view','ltk.pic','ltk.laporan',
            ],
        ],
        [
            'slug'      => 'staf',
            'label'     => 'Staf',
            'warna'     => '#6b7280',
            'is_system' => 0,
            'perms'     => [
                'kegiatan.view','kegiatan.create','kegiatan.edit',
                'task.view','task.create','task.edit','task.toggle',
                'anggaran.view','anggaran.input','anggaran.edit',
                'rincian.manage',
                'off_budget.view','off_budget.manage',
                'rpd.view',
                'ro.view',
                'laporan.view','laporan.download',
                'teams.view',
                'schedule.view','schedule.manage',
                'serasi.view',
                'ltk.view','ltk.laporan',
            ],
        ],
        [
            'slug'      => 'klien',
            'label'     => 'Klien',
            'warna'     => '#64748b',
            'is_system' => 1,
            'perms'     => [],
        ],
    ];

    public function run(): void
    {
        $db = \Config\Database::connect();
        $allPerms = array_keys(self::ALL_PERMISSIONS);

        foreach ($this->roles as $role) {
            // Upsert role
            $exists = $db->table('roles')->where('slug', $role['slug'])->get()->getFirstRow('array');
            $now    = date('Y-m-d H:i:s');

            if ($exists) {
                $db->table('roles')->where('slug', $role['slug'])->update([
                    'label'      => $role['label'],
                    'warna'      => $role['warna'],
                    'is_system'  => $role['is_system'],
                    'updated_at' => $now,
                ]);
            } else {
                $db->table('roles')->insert([
                    'slug'       => $role['slug'],
                    'label'      => $role['label'],
                    'warna'      => $role['warna'],
                    'is_system'  => $role['is_system'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // Hapus permissions lama, isi ulang
            $db->table('role_permissions')->where('role_slug', $role['slug'])->delete();

            $perms = $role['perms'] === ['*'] ? $allPerms : $role['perms'];
            foreach ($perms as $p) {
                $db->table('role_permissions')->insert([
                    'role_slug'  => $role['slug'],
                    'permission' => $p,
                ]);
            }
        }
    }
}
