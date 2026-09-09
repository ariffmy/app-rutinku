<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChildRewardGoals extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            // child_id references the same users.id used by the points ledger.
            'child_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'reward_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'started_at' => ['type' => 'DATETIME'],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['child_id', 'status']);
        $this->forge->addForeignKey('child_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('reward_id', 'rewards', 'id', 'CASCADE', 'RESTRICT');
        // MySQL DDL persists even if the subsequent ALTER fails. Preserve the
        // existing table and its rows when retrying that interrupted migration.
        $this->forge->createTable('child_reward_goals', true);

        if (isset($this->db->getIndexData('child_reward_goals')['child_reward_goals_one_active'])) {
            return;
        }

        $table = $this->db->protectIdentifiers($this->db->prefixTable('child_reward_goals'));
        if ($this->db->DBDriver === 'MySQLi') {
            // A generated expression cannot depend on child_id because its FK
            // has ON UPDATE CASCADE. Generate only a status marker instead.
            // NULL permits multiple historical goals; (child_id, 1) is unique.
            $this->db->query("ALTER TABLE {$table} ADD active_slot TINYINT GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) STORED, ADD UNIQUE KEY child_reward_goals_one_active (child_id, active_slot)");
        } else {
            $this->db->query("CREATE UNIQUE INDEX child_reward_goals_one_active ON {$table} (child_id) WHERE status = 'active'");
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('child_reward_goals', true);
    }
}
