<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuditLogs extends Migration
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
            'user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'target_user_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 100],
            'auditable_type' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'auditable_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'old_values' => ['type' => 'TEXT', 'null' => true],
            'new_values' => ['type' => 'TEXT', 'null' => true],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'created_at'], false, false, 'audit_logs_user_created_index');
        $this->forge->addKey(['target_user_id', 'created_at'], false, false, 'audit_logs_target_created_index');
        $this->forge->addKey(['auditable_type', 'auditable_id'], false, false, 'audit_logs_auditable_index');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('target_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('audit_logs', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('audit_logs', true);
    }
}
