<?php

namespace App\Models;

use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table = 'audit_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $updatedField = '';
    protected $allowedFields = [
        'user_id',
        'target_user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];
    protected $validationRules = [
        'user_id' => 'permit_empty|is_natural_no_zero',
        'target_user_id' => 'permit_empty|is_natural_no_zero',
        'action' => 'required|max_length[100]',
        'auditable_type' => 'permit_empty|max_length[100]',
        'auditable_id' => 'permit_empty|is_natural_no_zero',
        'description' => 'permit_empty|max_length[500]',
        'ip_address' => 'permit_empty|max_length[45]',
        'user_agent' => 'permit_empty|max_length[500]',
    ];
}
