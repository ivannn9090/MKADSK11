<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'nama'       => 'Dr. Budi Santoso',
                'email'      => 'direktur@instansi.go.id',
                'password'   => password_hash('password123', PASSWORD_BCRYPT),
                'jabatan'    => 'Direktur Utama',
                'role'       => 'direktur',
                'status'     => 'aktif',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama'       => 'Siti Rahayu, S.E.',
                'email'      => 'ketua.tim@instansi.go.id',
                'password'   => password_hash('password123', PASSWORD_BCRYPT),
                'jabatan'    => 'Kepala Seksi',
                'role'       => 'ketua_tim',
                'status'     => 'aktif',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama'       => 'Andi Prasetyo',
                'email'      => 'anggota@instansi.go.id',
                'password'   => password_hash('password123', PASSWORD_BCRYPT),
                'jabatan'    => 'Staf Pelaksana',
                'role'       => 'anggota_tim',
                'status'     => 'aktif',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama'       => 'Dewi Kusuma, S.T.',
                'email'      => 'anggota2@instansi.go.id',
                'password'   => password_hash('password123', PASSWORD_BCRYPT),
                'jabatan'    => 'Staf Analis',
                'role'       => 'anggota_tim',
                'status'     => 'aktif',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            [
                'nama'       => 'Rini Anggraeni, M.Ak.',
                'email'      => 'perencana@instansi.go.id',
                'password'   => password_hash('password123', PASSWORD_BCRYPT),
                'jabatan'    => 'Perencana Anggaran',
                'role'       => 'perencana_anggaran',
                'status'     => 'aktif',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ],
        ];

        $this->db->table('users')->insertBatch($users);
    }
}
