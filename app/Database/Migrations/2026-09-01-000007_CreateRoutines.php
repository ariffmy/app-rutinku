<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoutines extends Migration
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
            'name' => ['type' => 'VARCHAR', 'constraint' => 120],
            'description' => ['type' => 'TEXT', 'null' => true],
            'type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'start_time' => ['type' => 'TIME', 'null' => true],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'is_active' => ['type' => 'BOOLEAN', 'default' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['child_user_id', 'is_active', 'sort_order'], false, false, 'routines_child_active_sort_index');
        $this->forge->addKey('start_time', false, false, 'routines_start_time_index');
        $this->forge->addForeignKey('child_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('routines', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('routines', true);
    }
}
