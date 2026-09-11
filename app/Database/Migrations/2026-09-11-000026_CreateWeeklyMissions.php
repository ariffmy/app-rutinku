<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWeeklyMissions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'family_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 160],
            'description' => ['type' => 'VARCHAR', 'constraint' => 1000, 'null' => true],
            'mission_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'target_value' => ['type' => 'INT', 'unsigned' => true],
            'bonus_points' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'start_date' => ['type' => 'DATE'],
            'end_date' => ['type' => 'DATE'],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['family_id', 'start_date', 'end_date'], false, false, 'weekly_missions_family_dates_index');
        $this->forge->addForeignKey('family_id', 'families', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('weekly_missions', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'mission_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'child_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'progress' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['mission_id', 'child_id'], 'child_weekly_missions_mission_child_unique');
        $this->forge->addKey(['child_id', 'status'], false, false, 'child_weekly_missions_child_status_index');
        $this->forge->addForeignKey('mission_id', 'weekly_missions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('child_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('child_weekly_missions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('child_weekly_missions', true);
        $this->forge->dropTable('weekly_missions', true);
    }
}
