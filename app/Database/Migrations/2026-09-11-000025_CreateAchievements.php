<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAchievements extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 50],
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500],
            'icon' => ['type' => 'VARCHAR', 'constraint' => 50],
            'condition_type' => ['type' => 'VARCHAR', 'constraint' => 50],
            'condition_value' => ['type' => 'INT', 'unsigned' => true],
            'points_reward' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code', 'achievements_code_unique');
        $this->forge->addKey(['is_active', 'condition_type']);
        $this->forge->createTable('achievements', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'child_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'achievement_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'earned_at' => ['type' => 'DATETIME'],
            'points_awarded' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['child_id', 'achievement_id'], 'child_achievements_child_achievement_unique');
        $this->forge->addKey(['child_id', 'earned_at']);
        $this->forge->addForeignKey('child_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('achievement_id', 'achievements', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('child_achievements', true);

        if ($this->db->table('achievements')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            $this->db->table('achievements')->insertBatch([
                ['code' => 'FIRST_TASK', 'name' => 'First Step', 'description' => 'Selesaikan rutin pertama', 'icon' => 'check', 'condition_type' => 'task_count', 'condition_value' => 1, 'points_reward' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'FIRST_PERFECT_DAY', 'name' => 'Perfect Start', 'description' => 'Capai Perfect Day pertama', 'icon' => 'crown', 'condition_type' => 'perfect_day_count', 'condition_value' => 1, 'points_reward' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '7_DAY_STREAK', 'name' => 'On Fire', 'description' => 'Capai streak 7 hari', 'icon' => 'fire', 'condition_type' => 'streak', 'condition_value' => 7, 'points_reward' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '50_TASKS', 'name' => 'Routine Builder', 'description' => 'Selesaikan 50 rutin', 'icon' => 'check', 'condition_type' => 'task_count', 'condition_value' => 50, 'points_reward' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '10_PERFECT_DAYS', 'name' => 'Perfect Ten', 'description' => 'Capai 10 Perfect Day', 'icon' => 'crown', 'condition_type' => 'perfect_day_count', 'condition_value' => 10, 'points_reward' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => '1000_POINTS_EARNED', 'name' => 'Collector', 'description' => 'Kumpul 1,000 mata', 'icon' => 'star', 'condition_type' => 'earned_points', 'condition_value' => 1000, 'points_reward' => 0, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('child_achievements', true);
        $this->forge->dropTable('achievements', true);
    }
}
