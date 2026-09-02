<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoutineTasks extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'routine_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 160],
            'description' => ['type' => 'TEXT', 'null' => true],
            'task_time' => ['type' => 'TIME', 'null' => true],
            'points' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'is_required' => ['type' => 'BOOLEAN', 'default' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['routine_id', 'is_active', 'sort_order'], false, false, 'routine_tasks_routine_active_sort_index');
        $this->forge->addKey('task_time', false, false, 'routine_tasks_time_index');
        $this->forge->addForeignKey('routine_id', 'routines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('routine_tasks', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('routine_tasks', true);
    }
}
