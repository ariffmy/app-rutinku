<?php

namespace App\Models;

use CodeIgniter\Model;

class ChildRewardGoalModel extends Model
{
    protected $table = 'child_reward_goals';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['child_id', 'reward_id', 'status', 'started_at', 'completed_at'];
    protected $validationRules = [
        'child_id' => 'required|is_natural_no_zero',
        'reward_id' => 'required|is_natural_no_zero',
        'status' => 'required|in_list[active,completed,cancelled]',
        'started_at' => 'required|valid_date',
        'completed_at' => 'permit_empty|valid_date',
    ];
}
