<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000028 — Admin role + otp_codes + app_settings
 * - Tambah value 'admin' ke ENUM role di tabel users
 * - Buat tabel otp_codes
 * - Buat tabel app_settings + seed default settings
 */
class AddAdminRoleAndSettings extends Migration
{
    public function up(): void
    {
        // 1. Modifikasi ENUM role di tabel users — tambah 'admin'
        $this->db->query("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('direktur','ketua_tim','anggota_tim','perencana_anggaran','admin')
            NOT NULL DEFAULT 'anggota_tim'
        ");

        // 2. Tabel otp_codes
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => false],
            'otp'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => false],
            'purpose'    => ['type' => 'ENUM', 'constraint' => ['register', 'reset_password'], 'default' => 'register'],
            'expires_at' => ['type' => 'DATETIME', 'null' => false],
            'used'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['email', 'purpose']);
        $this->forge->createTable('otp_codes', true);

        // 3. Tabel app_settings
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'setting_value' => ['type' => 'TEXT', 'null' => true],
            'setting_group' => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'general'],
            'label'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'description'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'updated_by'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('setting_key');
        $this->forge->createTable('app_settings', true);

        // 4. Seed default settings
        $defaults = [
            // SMTP
            ['setting_key' => 'smtp_host',       'setting_value' => '', 'setting_group' => 'smtp',    'label' => 'SMTP Host',              'description' => 'Contoh: smtp.gmail.com'],
            ['setting_key' => 'smtp_port',       'setting_value' => '', 'setting_group' => 'smtp',    'label' => 'SMTP Port',              'description' => 'Contoh: 587 (TLS) atau 465 (SSL)'],
            ['setting_key' => 'smtp_user',       'setting_value' => '', 'setting_group' => 'smtp',    'label' => 'SMTP Username',          'description' => 'Email pengirim'],
            ['setting_key' => 'smtp_pass',       'setting_value' => '', 'setting_group' => 'smtp',    'label' => 'SMTP Password',          'description' => 'Password / App Password'],
            ['setting_key' => 'smtp_crypto',     'setting_value' => '', 'setting_group' => 'smtp',    'label' => 'Enkripsi SMTP',          'description' => 'tls, ssl, atau none'],
            ['setting_key' => 'smtp_from_email', 'setting_value' => '', 'setting_group' => 'smtp',    'label' => 'Email Pengirim',         'description' => 'Alamat email asal'],
            ['setting_key' => 'smtp_from_name',  'setting_value' => '', 'setting_group' => 'smtp',    'label' => 'Nama Pengirim',          'description' => 'Nama yang tampil di email'],
            // General
            ['setting_key' => 'app_name',                'setting_value' => '', 'setting_group' => 'general', 'label' => 'Nama Aplikasi',          'description' => 'Nama tampilan aplikasi'],
            ['setting_key' => 'app_url',                 'setting_value' => '', 'setting_group' => 'general', 'label' => 'URL Aplikasi',           'description' => 'URL publik aplikasi'],
            ['setting_key' => 'registration_enabled',    'setting_value' => '0', 'setting_group' => 'general', 'label' => 'Registrasi Akun',        'description' => '1 = aktif, 0 = nonaktif'],
        ];

        $this->db->table('app_settings')->insertBatch($defaults);
    }

    public function down(): void
    {
        $this->forge->dropTable('app_settings', true);
        $this->forge->dropTable('otp_codes', true);

        $this->db->query("
            ALTER TABLE users
            MODIFY COLUMN role ENUM('direktur','ketua_tim','anggota_tim','perencana_anggaran')
            NOT NULL DEFAULT 'anggota_tim'
        ");
    }
}
