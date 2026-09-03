<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTaskSchedules extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('routine_tasks', [
            'duration_minutes' => ['type' => 'INT', 'default' => 15],
            'schedule_type' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'inherit'],
            'start_date' => ['type' => 'DATE', 'null' => true],
            'repeat_days' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('routine_tasks', ['duration_minutes', 'schedule_type', 'start_date', 'repeat_days']);
    }
}
