<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRoutineDays extends Migration
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
            'routine_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'day_of_week' => ['type' => 'TINYINT', 'constraint' => 1, 'unsigned' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['routine_id', 'day_of_week'], 'routine_days_routine_day_unique');
        $this->forge->addKey('day_of_week', false, false, 'routine_days_weekday_index');
        $this->forge->addForeignKey('routine_id', 'routines', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('routine_days', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('routine_days', true);
    }
}
