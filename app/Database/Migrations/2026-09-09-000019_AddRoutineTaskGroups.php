<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRoutineTaskGroups extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('routine_tasks', [
            'task_group_token' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true, 'after' => 'routine_id'],
        ]);
        $this->forge->addKey('task_group_token');
        $this->forge->processIndexes('routine_tasks');
    }

    public function down(): void
    {
        $this->forge->dropKey('routine_tasks', 'routine_tasks_task_group_token');
        $this->forge->dropColumn('routine_tasks', 'task_group_token');
    }
}
