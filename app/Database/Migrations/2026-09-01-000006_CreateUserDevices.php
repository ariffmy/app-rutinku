<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserDevices extends Migration
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
            'user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'device_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'device_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'is_trusted' => ['type' => 'BOOLEAN', 'default' => true],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'created_by_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash', 'user_devices_token_hash_unique');
        $this->forge->addKey(['user_id', 'is_trusted', 'revoked_at'], false, false, 'user_devices_active_index');
        $this->forge->addKey('last_used_at', false, false, 'user_devices_last_used_index');
        $this->forge->addKey('created_by_user_id', false, false, 'user_devices_creator_index');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('user_devices', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('user_devices', true);
    }
}
