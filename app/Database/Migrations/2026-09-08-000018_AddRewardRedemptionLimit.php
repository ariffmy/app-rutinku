<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRewardRedemptionLimit extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('rewards', [
            'redemption_limit' => [
                'type' => 'VARCHAR',
                'constraint' => 20,
                'default' => 'unlimited',
                'after' => 'image',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('rewards', 'redemption_limit');
    }
}
