<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class AddRewardCategory extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('rewards', ['category' => ['type' => 'VARCHAR', 'constraint' => 80, 'default' => 'Lain-lain']]);
    }
    public function down(): void
    {
        $this->forge->dropColumn('rewards', 'category');
    }
}
