<?php

namespace App\Models;

use CodeIgniter\Model;

class RoutineTaskModel extends Model
{
    protected $table = 'routine_tasks';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'routine_id',
        'title',
        'description',
        'task_time',
        'points',
        'is_required',
        'sort_order',
        'is_active',
    ];
    protected $validationRules = [
        'routine_id' => 'required|is_natural_no_zero',
        'title' => 'required|max_length[160]',
        'description' => 'permit_empty|max_length[5000]',
        'task_time' => 'permit_empty|regex_match[/^(?:[01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/]',
        'points' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10000]',
        'is_required' => 'required|in_list[0,1]',
        'sort_order' => 'required|integer|greater_than_equal_to[0]',
        'is_active' => 'required|in_list[0,1]',
    ];
}
