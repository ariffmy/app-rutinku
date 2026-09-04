<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRoutineGroups extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('routines', [
            'group_token' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'assignment_scope' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'legacy'],
        ]);
        $this->forge->addKey('group_token');
        $this->forge->processIndexes('routines');
    }

    public function down(): void
    {
        $this->forge->dropKey('routines', 'routines_group_token');
        $this->forge->dropColumn('routines', ['group_token', 'assignment_scope']);
    }
}
