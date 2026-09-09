<?php

namespace App\Models;

use CodeIgniter\Model;

class PerfectDayModel extends Model
{
    protected $table = 'perfect_days';
    protected $returnType = 'array';
    protected $allowedFields = ['child_id', 'perfect_date', 'completed_tasks', 'required_tasks', 'bonus_points', 'created_at'];
    protected $validationRules = [
        'child_id' => 'required|is_natural_no_zero',
        'perfect_date' => 'required|valid_date[Y-m-d]',
        'completed_tasks' => 'required|is_natural_no_zero',
        'required_tasks' => 'required|is_natural_no_zero',
        'bonus_points' => 'required|is_natural|less_than_equal_to[1000000]',
        'created_at' => 'required|valid_date',
    ];
}
