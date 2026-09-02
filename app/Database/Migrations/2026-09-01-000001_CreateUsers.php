<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsers extends Migration
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
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'email' => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'username' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'role' => ['type' => 'VARCHAR', 'constraint' => 20],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'last_login_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('email', 'users_email_unique');
        $this->forge->addUniqueKey('username', 'users_username_unique');
        $this->forge->addKey(['role', 'is_active'], false, false, 'users_role_active_index');
        $this->forge->createTable('users', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
