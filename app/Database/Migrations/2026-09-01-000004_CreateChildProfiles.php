<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateChildProfiles extends Migration
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
            'user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'avatar' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'date_of_birth' => ['type' => 'DATE', 'null' => true],
            'is_ranking_eligible' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('user_id', 'child_profiles_user_unique');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('child_profiles', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('child_profiles', true);
    }
}
