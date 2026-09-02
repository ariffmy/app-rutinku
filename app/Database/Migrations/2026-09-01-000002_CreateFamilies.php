<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFamilies extends Migration
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
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('families', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('families', true);
    }
}
