<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTaskAttachmentsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'task_id'     => ['type' => 'INT', 'unsigned' => true],
            'nama_file'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_url'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'uploaded_by' => ['type' => 'INT', 'unsigned' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('task_id', 'tasks', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('uploaded_by', 'users', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('task_attachments');
    }

    public function down(): void
    {
        $this->forge->dropTable('task_attachments', true);
    }
}
