<?php

namespace App\Models;

use CodeIgniter\Model;

class TaskCompletionModel extends Model
{
    protected $table = 'task_completions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'child_user_id',
        'routine_task_id',
        'completion_date',
        'completed_at',
        'points_awarded',
        'status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by_user_id',
    ];
    protected $validationRules = [
        'child_user_id' => 'required|is_natural_no_zero',
        'routine_task_id' => 'required|is_natural_no_zero',
        'completion_date' => 'required|valid_date[Y-m-d]',
        'completed_at' => 'required|valid_date[Y-m-d H:i:s]',
        'points_awarded' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10000]',
        'status' => 'required|in_list[pending,completed,rejected]',
        'rejection_reason' => 'permit_empty|max_length[500]',
        'reviewed_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'reviewed_by_user_id' => 'permit_empty|is_natural_no_zero',
    ];
}
