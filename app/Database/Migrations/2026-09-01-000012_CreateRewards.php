<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRewards extends Migration
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
            'family_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'title' => ['type' => 'VARCHAR', 'constraint' => 160],
            'description' => ['type' => 'TEXT', 'null' => true],
            'points_required' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'image' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(
            ['family_id', 'is_active'],
            false,
            false,
            'rewards_family_active_index',
        );
        $this->forge->addForeignKey('family_id', 'families', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('rewards', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('rewards', true);
    }
}
