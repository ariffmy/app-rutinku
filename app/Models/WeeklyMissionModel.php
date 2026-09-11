<?php

namespace App\Models;

use CodeIgniter\Model;

class WeeklyMissionModel extends Model
{
    protected $table = 'weekly_missions';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'family_id', 'title', 'description', 'mission_type', 'target_value', 'bonus_points',
        'start_date', 'end_date', 'is_active', 'created_by',
    ];
    protected $validationRules = [
        'family_id' => 'required|is_natural_no_zero',
        'title' => 'required|max_length[160]',
        'description' => 'permit_empty|max_length[1000]',
        'mission_type' => 'required|in_list[routine_completion_count,perfect_day_count]',
        'target_value' => 'required|is_natural_no_zero|less_than_equal_to[1000]',
        'bonus_points' => 'required|is_natural|less_than_equal_to[1000000]',
        'start_date' => 'required|valid_date[Y-m-d]',
        'end_date' => 'required|valid_date[Y-m-d]',
        'is_active' => 'required|in_list[0,1]',
        'created_by' => 'required|is_natural_no_zero',
    ];
}
