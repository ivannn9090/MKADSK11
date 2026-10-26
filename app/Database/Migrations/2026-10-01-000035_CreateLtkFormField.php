<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * LTK — Field-field yang terdapat dalam satu template form.
 * opsi_pilihan (JSON) digunakan untuk tipe dropdown/radio/checkbox.
 */
class CreateLtkFormField extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'               => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'form_template_id' => ['type' => 'INT', 'unsigned' => true, 'null' => false],
            'label'            => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'field_name'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => false],
            'tipe'             => [
                'type'       => 'ENUM',
                'constraint' => ['text','textarea','number','date','dropdown','radio','checkbox','file','pdf'],
                'null'       => false,
            ],
            'placeholder'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'opsi_pilihan'     => ['type' => 'JSON', 'null' => true],
            'is_required'      => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 0],
            'urutan'           => ['type' => 'SMALLINT', 'unsigned' => true, 'null' => false, 'default' => 0],
            'is_aktif'         => ['type' => 'TINYINT', 'constraint' => 1, 'null' => false, 'default' => 1],
            'created_at'       => ['type' => 'DATETIME', 'null' => false, 'default' => false],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('form_template_id');
        $this->forge->createTable('ltk_form_field', true);

        $this->db->query('ALTER TABLE ltk_form_field MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
        $this->db->query('ALTER TABLE ltk_form_field ADD CONSTRAINT fk_lff_template FOREIGN KEY (form_template_id) REFERENCES ltk_form_template(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE ltk_form_field DROP FOREIGN KEY fk_lff_template');
        $this->forge->dropTable('ltk_form_field', true);
    }
}
