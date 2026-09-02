<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFamilyUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'family_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['family_id', 'user_id'], 'family_users_family_user_unique');
        $this->forge->addKey('user_id', false, false, 'family_users_user_index');
        $this->forge->addForeignKey('family_id', 'families', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('family_users', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('family_users', true);
    }
}
