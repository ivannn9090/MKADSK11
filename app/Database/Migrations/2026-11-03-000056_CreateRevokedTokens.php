<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Tabel revoked_tokens — menyimpan JWT yang sudah di-logout sebelum expired.
 * Digunakan untuk mencegah reuse token setelah logout (token invalidation).
 *
 * Catatan: CI4 FileCache digunakan sebagai alternatif jika cache Redis tersedia,
 * namun tabel DB dipilih agar konsisten tanpa dependency tambahan.
 */
class CreateRevokedTokens extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // SHA-256 hash dari token — tidak simpan token mentah
            'token_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            // exp dari payload JWT — untuk keperluan cleanup
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
            'revoked_at' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'default' => date('Y-m-d H:i:s'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey('expires_at');   // untuk cleanup query

        $this->forge->createTable('revoked_tokens', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('revoked_tokens', true);
    }
}
