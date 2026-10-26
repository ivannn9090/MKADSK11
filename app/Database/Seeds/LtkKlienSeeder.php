<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * LtkKlienSeeder — Data dummy akun Klien untuk pengujian modul LTK.
 *
 * Password semua akun: password123
 *
 * Menghasilkan:
 *   - 5 akun klien TERVERIFIKASI (is_verified = 1)
 *   - 2 akun klien BELUM DIVERIFIKASI (is_verified = 0, pending TU)
 *
 * Jalankan: php spark db:seed LtkKlienSeeder
 */
class LtkKlienSeeder extends Seeder
{
    public function run(): void
    {
        $db   = \Config\Database::connect();
        $now  = date('Y-m-d H:i:s');
        $hash = '$2y$10$TVe3qNMJJO6ZisCh51clMOLrE7W3PS8Y2IK7M1bW/fC6N3PzqB8Ti'; // password123

        // ── Admin user id untuk verified_by ───────────────────────
        $adminId = $db->table('users')->where('role', 'admin')->get()->getRowArray()['id'] ?? 6;

        // ── Definisi akun klien ────────────────────────────────────
        $klienData = [
            // ── Terverifikasi ──────────────────────────────────────
            [
                'user' => [
                    'nama'    => 'Ir. Teguh Santoso, M.Si.',
                    'email'   => 'teguh.santoso@anri.go.id',
                    'jabatan' => 'Kepala Bidang Pengolahan Arsip',
                    'role'    => 'klien',
                    'status'  => 'aktif',
                ],
                'profil' => [
                    'unit_kerja'  => 'Deputi Bidang Tata Kelola Kearsipan Nasional',
                    'jabatan'     => 'Kepala Bidang Pengolahan Arsip',
                    'no_telepon'  => '08111234567',
                    'is_verified' => 1,
                    'verified_by' => $adminId,
                    'verified_at' => date('Y-m-d H:i:s', strtotime('-10 days')),
                ],
            ],
            [
                'user' => [
                    'nama'    => 'Dra. Sri Wahyuni, M.Hum.',
                    'email'   => 'sri.wahyuni@anri.go.id',
                    'jabatan' => 'Analis Kebijakan Muda',
                    'role'    => 'klien',
                    'status'  => 'aktif',
                ],
                'profil' => [
                    'unit_kerja'  => 'Deputi Bidang Penyelamatan, Pelestarian, dan Perlindungan Arsip',
                    'jabatan'     => 'Analis Kebijakan Muda',
                    'no_telepon'  => '08121234567',
                    'is_verified' => 1,
                    'verified_by' => $adminId,
                    'verified_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
                ],
            ],
            [
                'user' => [
                    'nama'    => 'Muhamad Fauzi, S.IP., M.AP.',
                    'email'   => 'fauzi.mka@anri.go.id',
                    'jabatan' => 'Perancang Peraturan Perundang-undangan',
                    'role'    => 'klien',
                    'status'  => 'aktif',
                ],
                'profil' => [
                    'unit_kerja'  => 'Sekretariat Utama ANRI',
                    'jabatan'     => 'Perancang Peraturan Perundang-undangan',
                    'no_telepon'  => '08561234567',
                    'is_verified' => 1,
                    'verified_by' => $adminId,
                    'verified_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                ],
            ],
            [
                'user' => [
                    'nama'    => 'Ratna Dewi Puspitasari, S.H.',
                    'email'   => 'ratna.dewi@anri.go.id',
                    'jabatan' => 'Analis Hukum',
                    'role'    => 'klien',
                    'status'  => 'aktif',
                ],
                'profil' => [
                    'unit_kerja'  => 'Deputi Bidang Sistem dan Informasi Kearsipan Nasional',
                    'jabatan'     => 'Analis Hukum',
                    'no_telepon'  => '08771234567',
                    'is_verified' => 1,
                    'verified_by' => $adminId,
                    'verified_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
                ],
            ],
            [
                'user' => [
                    'nama'    => 'Drs. Bambang Supriyadi',
                    'email'   => 'bambang.supriyadi@anri.go.id',
                    'jabatan' => 'Kepala Subdirektorat Akuisisi',
                    'role'    => 'klien',
                    'status'  => 'aktif',
                ],
                'profil' => [
                    'unit_kerja'  => 'Direktorat Akuisisi',
                    'jabatan'     => 'Kepala Subdirektorat Akuisisi',
                    'no_telepon'  => '08221234567',
                    'is_verified' => 1,
                    'verified_by' => $adminId,
                    'verified_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                ],
            ],

            // ── Belum Diverifikasi (pending) ───────────────────────
            [
                'user' => [
                    'nama'    => 'Yuliana Safitri, S.Sos.',
                    'email'   => 'yuliana.safitri@anri.go.id',
                    'jabatan' => 'Arsiparis Ahli Pertama',
                    'role'    => 'klien',
                    'status'  => 'aktif',
                ],
                'profil' => [
                    'unit_kerja'  => 'Direktorat Preservasi',
                    'jabatan'     => 'Arsiparis Ahli Pertama',
                    'no_telepon'  => '08971234567',
                    'is_verified' => 0,
                    'verified_by' => null,
                    'verified_at' => null,
                ],
            ],
            [
                'user' => [
                    'nama'    => 'Hendra Kurniawan, S.Kom.',
                    'email'   => 'hendra.kurniawan@anri.go.id',
                    'jabatan' => 'Pranata Komputer Ahli Muda',
                    'role'    => 'klien',
                    'status'  => 'aktif',
                ],
                'profil' => [
                    'unit_kerja'  => 'Direktorat Sistem Informasi Kearsipan',
                    'jabatan'     => 'Pranata Komputer Ahli Muda',
                    'no_telepon'  => '08131234567',
                    'is_verified' => 0,
                    'verified_by' => null,
                    'verified_at' => null,
                ],
            ],
        ];

        // ── Insert ─────────────────────────────────────────────────
        $inserted = 0;
        $skipped  = 0;

        foreach ($klienData as $item) {
            // Cek duplikat email
            if ($db->table('users')->where('email', $item['user']['email'])->countAllResults()) {
                $skipped++;
                continue;
            }

            // Insert user
            $db->table('users')->insert(array_merge($item['user'], [
                'password'             => $hash,
                'must_change_password' => 0,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]));
            $userId = $db->insertID();

            // Insert profil klien
            $db->table('ltk_klien_profil')->insert(array_merge($item['profil'], [
                'user_id'    => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            $inserted++;
        }

        echo "LtkKlienSeeder: {$inserted} akun klien berhasil ditambahkan, {$skipped} dilewati (email sudah ada).\n";
        echo "Password semua akun: password123\n";
    }
}
