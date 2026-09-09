<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddParentApprovals extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('routines', [
            'requires_approval' => ['type' => 'BOOLEAN', 'default' => false, 'after' => 'assignment_scope'],
        ]);
        $this->forge->addColumn('routine_tasks', [
            'requires_approval' => ['type' => 'BOOLEAN', 'default' => false, 'after' => 'task_group_token'],
        ]);
        $this->forge->addColumn('task_completions', [
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'completed', 'after' => 'points_awarded'],
            'rejection_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'status'],
            'reviewed_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'rejection_reason'],
            'reviewed_by_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'reviewed_at'],
        ]);
        $this->forge->addKey(['status', 'completed_at'], false, false, 'task_completions_status_created_index');
        $this->forge->processIndexes('task_completions');
    }

    public function down(): void
    {
        $this->forge->dropColumn('task_completions', ['status', 'rejection_reason', 'reviewed_at', 'reviewed_by_user_id']);
        $this->forge->dropColumn('routine_tasks', 'requires_approval');
        $this->forge->dropColumn('routines', 'requires_approval');
    }
}
