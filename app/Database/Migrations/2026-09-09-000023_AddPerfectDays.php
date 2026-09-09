<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPerfectDays extends Migration
{
    public function up(): void
    {
        // is_required is supplied by AddRequiredRoutines; preserve existing choices.
        $this->db->resetDataCache();
        if (! $this->db->fieldExists('perfect_day_eligible', 'routines')) {
            $this->forge->addColumn('routines', [
                'perfect_day_eligible' => ['type' => 'BOOLEAN', 'default' => true, 'null' => false],
            ]);
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'child_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'perfect_date' => ['type' => 'DATE'],
            'completed_tasks' => ['type' => 'INT', 'unsigned' => true],
            'required_tasks' => ['type' => 'INT', 'unsigned' => true],
            'bonus_points' => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['child_id', 'perfect_date'], 'perfect_days_child_date_unique');
        $this->forge->addForeignKey('child_id', 'users', 'id', 'CASCADE', 'RESTRICT');
        $this->forge->createTable('perfect_days', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('perfect_days', true);
        $table = $this->db->protectIdentifiers($this->db->prefixTable('routines'));
        $this->db->query("ALTER TABLE {$table} DROP COLUMN perfect_day_eligible");
        $this->db->resetDataCache();
    }
}
