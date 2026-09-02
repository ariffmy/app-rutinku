<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTaskCompletions extends Migration
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
            'child_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'routine_task_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'completion_date' => ['type' => 'DATE'],
            'completed_at' => ['type' => 'DATETIME'],
            'points_awarded' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(
            ['child_user_id', 'routine_task_id', 'completion_date'],
            'task_completions_child_task_date_unique',
        );
        $this->forge->addKey(
            ['child_user_id', 'completion_date'],
            false,
            false,
            'task_completions_child_date_index',
        );
        $this->forge->addKey('completed_at', false, false, 'task_completions_completed_at_index');
        $this->forge->addForeignKey('child_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('routine_task_id', 'routine_tasks', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('task_completions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('task_completions', true);
    }
}
