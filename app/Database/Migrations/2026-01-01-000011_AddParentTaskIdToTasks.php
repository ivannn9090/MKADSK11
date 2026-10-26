<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParentTaskIdToTasks extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('tasks', [
            'parent_task_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'kegiatan_id',
            ],
        ]);

        // Foreign key: subtask merujuk ke task induk, CASCADE delete supaya hapus task induk ikut hapus subtasknya
        $this->db->query('ALTER TABLE tasks ADD CONSTRAINT fk_tasks_parent FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->db->query('ALTER TABLE tasks DROP FOREIGN KEY fk_tasks_parent');
        $this->forge->dropColumn('tasks', 'parent_task_id');
    }
}
