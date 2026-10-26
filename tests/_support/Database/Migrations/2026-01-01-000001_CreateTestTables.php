<?php

namespace Tests\Support\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Membuat semua tabel minimal yang dibutuhkan untuk OtpModelTest dan AuthControllerTest.
 * Dirancang agar kompatibel dengan SQLite (database test default CodeIgniter 4).
 *
 * SQLite tidak mendukung:
 * - UNSIGNED pada kolom biasa (hanya PK AUTOINCREMENT)
 * - ALTER TABLE ADD CONSTRAINT FOREIGN KEY
 * - ENUM
 *
 * Forge CI4 menambahkan DBPrefix secara otomatis, sehingga tabel yang dibuat
 * melalui Forge akan memiliki prefix yang sesuai (misalnya 'db_').
 * Jangan gunakan $this->db->query("CREATE TABLE ...") langsung karena tidak
 * menerapkan prefix.
 */
class CreateTestTables extends Migration
{
    protected $DBGroup = 'tests';

    public function up(): void
    {
        // ── otp_codes ──────────────────────────────────────────────────
        if (! $this->db->tableExists('otp_codes')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'auto_increment' => true],
                'email'      => ['type' => 'VARCHAR', 'constraint' => 150],
                'otp'        => ['type' => 'VARCHAR', 'constraint' => 64],
                'purpose'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'register'],
                'expires_at' => ['type' => 'DATETIME'],
                'used'       => ['type' => 'INT', 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('otp_codes');
        }

        // ── users ──────────────────────────────────────────────────────
        if (! $this->db->tableExists('users')) {
            $this->forge->addField([
                'id'                   => ['type' => 'INT', 'auto_increment' => true],
                'nama'                 => ['type' => 'VARCHAR', 'constraint' => 150],
                'email'                => ['type' => 'VARCHAR', 'constraint' => 150],
                'password'             => ['type' => 'VARCHAR', 'constraint' => 255],
                'jabatan'              => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
                'posisi_tim'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'role'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'anggota_tim'],
                'status'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'aktif'],
                'must_change_password' => ['type' => 'INT', 'default' => 0],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('email');
            $this->forge->createTable('users');
        }

        // ── user_roles ─────────────────────────────────────────────────
        if (! $this->db->tableExists('user_roles')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'auto_increment' => true],
                'user_id'    => ['type' => 'INT'],
                'role_slug'  => ['type' => 'VARCHAR', 'constraint' => 50],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('user_roles');
        }

        // ── roles ──────────────────────────────────────────────────────
        if (! $this->db->tableExists('roles')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'auto_increment' => true],
                'slug'       => ['type' => 'VARCHAR', 'constraint' => 50],
                'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('slug');
            $this->forge->createTable('roles');
        }

        // ── role_permissions ───────────────────────────────────────────
        if (! $this->db->tableExists('role_permissions')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'auto_increment' => true],
                'role_slug'  => ['type' => 'VARCHAR', 'constraint' => 50],
                'permission' => ['type' => 'VARCHAR', 'constraint' => 100],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('role_permissions');
        }

        // ── app_settings ───────────────────────────────────────────────
        if (! $this->db->tableExists('app_settings')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'auto_increment' => true],
                'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100],
                'setting_value' => ['type' => 'TEXT', 'null' => true],
                'setting_group' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'label'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'description'   => ['type' => 'TEXT', 'null' => true],
                'updated_by'    => ['type' => 'INT', 'null' => true],
                'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('setting_key');
            $this->forge->createTable('app_settings');
        }

        // ── audit_log ─────────────────────────────────────────────────
        if (! $this->db->tableExists('audit_log')) {
            $this->forge->addField([
                'id'             => ['type' => 'INT', 'auto_increment' => true],
                'user_id'        => ['type' => 'INT', 'null' => true],
                'aktivitas'      => ['type' => 'VARCHAR', 'constraint' => 255],
                'tabel_terkait'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'record_id'      => ['type' => 'INT', 'null' => true],
                'data_sebelum'   => ['type' => 'TEXT', 'null' => true],
                'data_sesudah'   => ['type' => 'TEXT', 'null' => true],
                'ip_address'     => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
                'waktu'          => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('audit_log');
        }

        // ── revoked_tokens ─────────────────────────────────────────────
        if (! $this->db->tableExists('revoked_tokens')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'auto_increment' => true],
                'token'      => ['type' => 'TEXT'],
                'user_id'    => ['type' => 'INT', 'null' => true],
                'expires_at' => ['type' => 'INT', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('revoked_tokens');
        }

        // ── posisi_tim ─────────────────────────────────────────────────
        if (! $this->db->tableExists('posisi_tim')) {
            $this->forge->addField([
                'id'         => ['type' => 'INT', 'auto_increment' => true],
                'nama'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'urutan'     => ['type' => 'INT', 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('posisi_tim');
        }

        // ── user_posisi_tim ────────────────────────────────────────────
        if (! $this->db->tableExists('user_posisi_tim')) {
            $this->forge->addField([
                'id'            => ['type' => 'INT', 'auto_increment' => true],
                'user_id'       => ['type' => 'INT'],
                'posisi_tim_id' => ['type' => 'INT'],
                'created_at'    => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('user_posisi_tim');
        }

        // ── ltk_klien_profil ──────────────────────────────────────────
        if (! $this->db->tableExists('ltk_klien_profil')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'auto_increment' => true],
                'user_id'     => ['type' => 'INT'],
                'unit_kerja'  => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
                'jabatan'     => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
                'no_telepon'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'is_verified' => ['type' => 'INT', 'default' => 0],
                'verified_at' => ['type' => 'DATETIME', 'null' => true],
                'verified_by' => ['type' => 'INT', 'null' => true],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
                'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->createTable('ltk_klien_profil');
        }
    }

    public function down(): void
    {
        $tables = [
            'ltk_klien_profil', 'user_posisi_tim', 'posisi_tim',
            'revoked_tokens', 'audit_log', 'app_settings',
            'role_permissions', 'roles', 'user_roles', 'users', 'otp_codes',
        ];
        foreach ($tables as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
