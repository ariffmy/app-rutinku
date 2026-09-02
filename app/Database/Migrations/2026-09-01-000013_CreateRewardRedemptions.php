<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRewardRedemptions extends Migration
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
            'reward_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'child_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'points_used' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
            'requested_at' => ['type' => 'DATETIME'],
            'approved_at' => ['type' => 'DATETIME', 'null' => true],
            'rejected_at' => ['type' => 'DATETIME', 'null' => true],
            'approved_by_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'rejected_by_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(
            ['child_user_id', 'status'],
            false,
            false,
            'reward_redemptions_child_status_index',
        );
        $this->forge->addKey(
            ['reward_id', 'status'],
            false,
            false,
            'reward_redemptions_reward_status_index',
        );
        $this->forge->addKey('requested_at', false, false, 'reward_redemptions_requested_at_index');
        $this->forge->addForeignKey('reward_id', 'rewards', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('child_user_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->addForeignKey('approved_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('rejected_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('reward_redemptions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('reward_redemptions', true);
    }
}
