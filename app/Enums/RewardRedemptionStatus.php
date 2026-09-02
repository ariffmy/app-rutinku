<?php

namespace App\Enums;

enum RewardRedemptionStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
