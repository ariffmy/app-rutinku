<?php

namespace App\Enums;

enum PointTransactionType: string
{
    case TASK = 'task';
    case BONUS = 'bonus';
    case REWARD = 'reward';
    case ADJUSTMENT = 'adjustment';
    case REVERSAL = 'reversal';
}
