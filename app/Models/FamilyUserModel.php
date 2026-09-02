<?php

namespace App\Models;

use CodeIgniter\Model;

class FamilyUserModel extends Model
{
    protected $table = 'family_users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $updatedField = '';
    protected $allowedFields = ['family_id', 'user_id'];
    protected $validationRules = [
        'family_id' => 'required|is_natural_no_zero',
        'user_id' => 'required|is_natural_no_zero',
    ];
}
