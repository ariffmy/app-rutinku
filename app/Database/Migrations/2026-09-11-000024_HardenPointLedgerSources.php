<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HardenPointLedgerSources extends Migration
{
    public function up(): void
    {
        // Historical manual adjustments had no source. Their own immutable ID
        // is a stable source key and makes every ledger row traceable.
        $table = $this->db->table('point_transactions');
        foreach ($table->where('type', 'adjustment')->groupStart()
            ->where('reference_type', null)->orWhere('reference_id', null)->groupEnd()->get()->getResultArray() as $row) {
            $this->db->table('point_transactions')->where('id', $row['id'])->update([
                'reference_type' => 'manual_adjustment',
                'reference_id' => $row['id'],
            ]);
        }
    }

    public function down(): void
    {
        // Ledger history is append-only; do not erase source metadata.
    }
}
