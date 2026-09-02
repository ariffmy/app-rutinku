<?php

namespace App\Models;

use CodeIgniter\Model;

class RoutineDayModel extends Model
{
    protected $table = 'routine_days';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['routine_id', 'day_of_week'];
    protected $validationRules = [
        'routine_id' => 'required|is_natural_no_zero',
        'day_of_week' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[7]',
    ];
}
