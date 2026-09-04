<?php

namespace App\Models;

use CodeIgniter\Model;

class RoutineModel extends Model
{
    protected $table = 'routines';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'child_user_id',
        'group_token',
        'assignment_scope',
        'name',
        'description',
        'type',
        'start_time',
        'sort_order',
        'is_active',
    ];
    protected $validationRules = [
        'child_user_id' => 'required|is_natural_no_zero',
        'name' => 'required|max_length[120]',
        'description' => 'permit_empty|max_length[5000]',
        'type' => 'permit_empty|max_length[50]',
        'start_time' => 'permit_empty|regex_match[/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/]',
        'sort_order' => 'required|integer|greater_than_equal_to[0]',
        'is_active' => 'required|in_list[0,1]',
    ];
}
