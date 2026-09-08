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
        'redemption_limit',
        'is_active',
    ];
    protected $validationRules = [
        'family_id' => 'required|is_natural_no_zero',
        'title' => 'required|max_length[160]',
        'category' => 'required|in_list[Makanan & Minuman,Masa Skrin,Aktiviti,Hadiah,Keistimewaan,Digital,Wang,Istimewa,Lain-lain]',
        'description' => 'permit_empty|max_length[5000]',
        'points_required' => 'required|is_natural_no_zero|less_than_equal_to[1000000]',
        'image' => 'permit_empty|max_length[255]',
        'redemption_limit' => 'required|in_list[unlimited,daily,weekly,monthly,once]',
        'is_active' => 'required|in_list[0,1]',
    ];
}
