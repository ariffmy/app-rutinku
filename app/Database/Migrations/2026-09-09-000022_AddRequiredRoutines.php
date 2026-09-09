<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRequiredRoutines extends Migration
{
    public function up(): void
    {
        $this->db->resetDataCache();
        if (! $this->db->fieldExists('is_required', 'routines')) {
            $this->forge->addColumn('routines', [
                'is_required' => ['type' => 'BOOLEAN', 'default' => true, 'null' => false],
            ]);
        }
    }

    public function down(): void
    {
        // Native DROP COLUMN preserves incoming foreign keys when using SQLite;
        // rebuilding this parent table through Forge can retarget those keys.
        $table = $this->db->protectIdentifiers($this->db->prefixTable('routines'));
        $this->db->query("ALTER TABLE {$table} DROP COLUMN is_required");
        $this->db->resetDataCache();
    }
}
