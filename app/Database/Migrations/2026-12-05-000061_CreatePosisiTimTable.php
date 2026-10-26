<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000061 — Posisi Tim dinamis
 * - Buat tabel posisi_tim (master posisi)
 * - Buat tabel user_posisi_tim (many-to-many user ↔ posisi)
 * - Migrasikan data lama dari users.posisi_tim (VARCHAR) ke tabel baru
 */
class CreatePosisiTimTable extends Migration
{
    public function up(): void
    {
        // ── Tabel master posisi_tim ──────────────────────────
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'nama' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'is_system' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'comment'    => '1 = bawaan sistem, tidak bisa dihapus',
            ],
            'urutan' => [
                'type'    => 'INT',
                'default' => 0,
                'comment' => 'Urutan tampil di dropdown',
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('nama');
        $this->forge->createTable('posisi_tim', true);

        // ── Tabel user_posisi_tim (many-to-many) ─────────────
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'posisi_tim_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true, 'default' => null],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'posisi_tim_id']);
        $this->forge->addKey('user_id');
        $this->forge->createTable('user_posisi_tim', true);

        // ── Seed posisi sistem bawaan ─────────────────────────
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $defaults = [
            'Ketua Tim', 'Wakil Ketua', 'Sekretaris', 'Bendahara',
            'Koordinator', 'Anggota', 'Staf Ahli', 'Konsultan', 'Pengawas',
        ];
        foreach ($defaults as $i => $nama) {
            $db->table('posisi_tim')->ignore(true)->insert([
                'nama'       => $nama,
                'is_system'  => 1,
                'urutan'     => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // ── Migrasi data lama: users.posisi_tim → user_posisi_tim ─
        $users = $db->table('users')
                    ->select('id, posisi_tim')
                    ->where('posisi_tim IS NOT NULL')
                    ->where('posisi_tim !=', '')
                    ->get()->getResultArray();

        foreach ($users as $u) {
            // Cari id posisi yang cocok
            $posisi = $db->table('posisi_tim')
                         ->where('nama', $u['posisi_tim'])
                         ->get()->getRowArray();

            if ($posisi) {
                $db->table('user_posisi_tim')->ignore(true)->insert([
                    'user_id'       => $u['id'],
                    'posisi_tim_id' => $posisi['id'],
                    'created_at'    => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('user_posisi_tim', true);
        $this->forge->dropTable('posisi_tim', true);
    }
}
