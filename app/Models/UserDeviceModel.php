<?php

namespace App\Models;

use CodeIgniter\Model;

class UserDeviceModel extends Model
{
    protected $table = 'user_devices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'user_id',
        'token_hash',
        'device_name',
        'device_type',
        'is_trusted',
        'expires_at',
        'last_used_at',
        'revoked_at',
        'created_by_user_id',
    ];
    protected $validationRules = [
        'user_id' => 'required|is_natural_no_zero',
        'token_hash' => 'required|exact_length[64]|alpha_numeric|is_unique[user_devices.token_hash,id,{id}]',
        'device_name' => 'permit_empty|max_length[120]',
        'device_type' => 'permit_empty|max_length[50]',
        'is_trusted' => 'required|in_list[0,1]',
        'expires_at' => 'permit_empty|valid_date[Y-m-d H:i:s]',
        'created_by_user_id' => 'permit_empty|is_natural_no_zero',
    ];
}
