<?php

namespace App\Models;

use CodeIgniter\Model;

class ChildAchievementModel extends Model
{
    protected $table = 'child_achievements';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['child_id', 'achievement_id', 'earned_at', 'points_awarded', 'created_at'];
    protected $validationRules = [
        'child_id' => 'required|is_natural_no_zero',
        'achievement_id' => 'required|is_natural_no_zero',
        'earned_at' => 'required|valid_date[Y-m-d H:i:s]',
        'points_awarded' => 'required|is_natural|less_than_equal_to[1000000]',
        'created_at' => 'required|valid_date[Y-m-d H:i:s]',
    ];
}
