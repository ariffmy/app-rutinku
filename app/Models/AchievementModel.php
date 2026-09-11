<?php

namespace App\Models;

use CodeIgniter\Model;

class AchievementModel extends Model
{
    protected $table = 'achievements';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['code', 'name', 'description', 'icon', 'condition_type', 'condition_value', 'points_reward', 'is_active'];
    protected $validationRules = [
        'code' => 'required|regex_match[/^[A-Z0-9_]+$/]|max_length[50]',
        'name' => 'required|max_length[120]',
        'description' => 'required|max_length[500]',
        'icon' => 'required|max_length[50]',
        'condition_type' => 'required|in_list[task_count,perfect_day_count,streak,earned_points]',
        'condition_value' => 'required|is_natural_no_zero',
        'points_reward' => 'required|is_natural|less_than_equal_to[1000000]',
        'is_active' => 'required|in_list[0,1]',
    ];
}
