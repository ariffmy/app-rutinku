<?php

namespace App\Models;

use CodeIgniter\Model;

class ChildProfileModel extends Model
{
    protected $table = 'child_profiles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'user_id',
        'avatar',
        'date_of_birth',
        'is_ranking_eligible',
    ];
    protected $validationRules = [
        'user_id' => 'required|is_natural_no_zero|is_unique[child_profiles.user_id,id,{id}]',
        'avatar' => 'permit_empty|max_length[255]',
        'date_of_birth' => 'permit_empty|valid_date[Y-m-d]',
        'is_ranking_eligible' => 'required|in_list[0,1]',
    ];
}
