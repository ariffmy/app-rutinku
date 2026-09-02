<?php

namespace App\Models;

use CodeIgniter\Model;

class FamilyModel extends Model
{
    protected $table = 'families';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name'];
    protected $validationRules = [
        'name' => 'required|max_length[120]',
    ];
}
