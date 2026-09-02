<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePointTransactions extends Migration
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
            'type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'points' => ['type' => 'INT', 'constraint' => 11],
            'reference_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'reference_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'transaction_date' => ['type' => 'DATE'],
            'created_by_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(
            ['child_user_id', 'transaction_date'],
            false,
            false,
            'point_transactions_child_date_index',
        );
        $this->forge->addKey(
            ['child_user_id', 'type', 'transaction_date'],
            false,
            false,
            'point_transactions_child_type_date_index',
        );
        $this->forge->addKey(
            ['reference_type', 'reference_id'],
            false,
            false,
            'point_transactions_reference_index',
        );
        $this->forge->addUniqueKey(
            ['type', 'reference_type', 'reference_id'],
            'point_transactions_type_reference_unique',
        );
        $this->forge->addForeignKey('child_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('point_transactions', true);

        $existingCompletions = $this->db->table('task_completions')->get()->getResultArray();
        if ($existingCompletions !== []) {
            $rows = array_map(static fn (array $completion): array => [
                'child_user_id' => (int) $completion['child_user_id'],
                'type' => 'task',
                'points' => (int) $completion['points_awarded'],
                'reference_type' => 'task_completion',
                'reference_id' => (int) $completion['id'],
                'description' => 'Task completion (Phase 4 backfill)',
                'transaction_date' => $completion['completion_date'],
                'created_by_user_id' => (int) $completion['child_user_id'],
                'created_at' => $completion['created_at'] ?? $completion['completed_at'],
                'updated_at' => $completion['updated_at'] ?? $completion['completed_at'],
            ], $existingCompletions);
            $this->db->table('point_transactions')->insertBatch($rows);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('point_transactions', true);
    }
}
