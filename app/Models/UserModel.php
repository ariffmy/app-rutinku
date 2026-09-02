<?php

namespace App\Models;

use App\Entities\User;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table = 'users';
    protected $primaryKey = 'id';
    protected $returnType = User::class;
    protected $useAutoIncrement = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'name',
        'email',
        'username',
        'password_hash',
        'role',
        'is_active',
        'last_login_at',
    ];
    protected $validationRules = [
        'name' => 'required|max_length[120]',
        'email' => 'permit_empty|valid_email|max_length[190]|is_unique[users.email,id,{id}]',
        'username' => 'permit_empty|alpha_numeric_punct|max_length[100]|is_unique[users.username,id,{id}]',
        'password_hash' => 'required|max_length[255]',
        'role' => 'required|in_list[parent,child]',
        'is_active' => 'required|in_list[0,1]',
    ];
    protected $validationMessages = [];
    protected $skipValidation = false;
    protected $cleanValidationRules = true;
}
