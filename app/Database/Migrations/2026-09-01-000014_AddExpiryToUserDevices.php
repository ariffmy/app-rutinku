<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExpiryToUserDevices extends Migration
{
    private const LIFETIME_SECONDS = 15_552_000;

    public function up(): void
    {
        $this->forge->addColumn('user_devices', [
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'after' => 'is_trusted',
            ],
        ]);

        $devices = $this->db->table('user_devices')->select('id, created_at')->get()->getResultArray();
        foreach ($devices as $device) {
            $createdAt = strtotime((string) $device['created_at']);
            $this->db->table('user_devices')->where('id', $device['id'])->update([
                'expires_at' => date('Y-m-d H:i:s', ($createdAt === false ? time() : $createdAt) + self::LIFETIME_SECONDS),
            ]);
        }
    }

    public function down(): void
    {
        $this->forge->dropColumn('user_devices', 'expires_at');
    }
}
