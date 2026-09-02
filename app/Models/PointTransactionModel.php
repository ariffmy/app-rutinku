<?php

namespace App\Models;

use CodeIgniter\Model;
use LogicException;

class PointTransactionModel extends Model
{
    protected $table = 'point_transactions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'child_user_id',
        'type',
        'points',
        'reference_type',
        'reference_id',
        'description',
        'transaction_date',
        'created_by_user_id',
    ];
    protected $validationRules = [
        'child_user_id' => 'required|is_natural_no_zero',
        'type' => 'required|in_list[task,bonus,reward,adjustment,reversal]',
        'points' => 'required|integer|greater_than_equal_to[-1000000]|less_than_equal_to[1000000]',
        'reference_type' => 'permit_empty|max_length[100]',
        'reference_id' => 'permit_empty|is_natural_no_zero',
        'description' => 'permit_empty|max_length[500]',
        'transaction_date' => 'required|valid_date[Y-m-d]',
        'created_by_user_id' => 'permit_empty|is_natural_no_zero',
    ];
    protected $beforeUpdate = ['preventMutation'];
    protected $beforeDelete = ['preventMutation'];

    protected function preventMutation(array $data): array
    {
        throw new LogicException('Point ledger entries are append-only.');
    }
}
