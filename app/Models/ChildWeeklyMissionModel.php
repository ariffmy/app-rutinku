<?php

namespace App\Models;

use CodeIgniter\Model;

class ChildWeeklyMissionModel extends Model
{
    protected $table = 'child_weekly_missions';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['mission_id', 'child_id', 'progress', 'status', 'completed_at'];
    protected $validationRules = [
        'mission_id' => 'required|is_natural_no_zero',
        'child_id' => 'required|is_natural_no_zero',
        'progress' => 'required|is_natural',
        'status' => 'required|in_list[active,completed,expired,cancelled]',
        'completed_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
    ];
}
