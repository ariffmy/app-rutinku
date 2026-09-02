<?php

namespace App\Models;

use CodeIgniter\Model;

class RewardRedemptionModel extends Model
{
    protected $table = 'reward_redemptions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'reward_id',
        'child_user_id',
        'points_used',
        'status',
        'requested_at',
        'approved_at',
        'rejected_at',
        'approved_by_user_id',
        'rejected_by_user_id',
    ];
    protected $validationRules = [
        'reward_id' => 'required|is_natural_no_zero',
        'child_user_id' => 'required|is_natural_no_zero',
        'points_used' => 'required|is_natural_no_zero|less_than_equal_to[1000000]',
        'status' => 'required|in_list[pending,approved,rejected]',
        'requested_at' => 'required|valid_date[Y-m-d H:i:s]',
        'approved_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'rejected_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'approved_by_user_id' => 'permit_empty|is_natural_no_zero',
        'rejected_by_user_id' => 'permit_empty|is_natural_no_zero',
    ];
}
