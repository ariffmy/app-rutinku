<?php

namespace App\Models;

use CodeIgniter\Model;

class RewardModel extends Model
{
    protected $table = 'rewards';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'family_id',
        'title',
        'category',
        'description',
        'points_required',
        'image',
        'is_active',
    ];
    protected $validationRules = [
        'family_id' => 'required|is_natural_no_zero',
        'title' => 'required|max_length[160]',
        'category' => 'permit_empty|max_length[80]',
        'description' => 'permit_empty|max_length[5000]',
        'points_required' => 'required|is_natural_no_zero|less_than_equal_to[1000000]',
        'image' => 'permit_empty|max_length[255]',
        'is_active' => 'required|in_list[0,1]',
    ];
}
