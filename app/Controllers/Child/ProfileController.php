<?php

namespace App\Controllers\Child;

use App\Controllers\BaseController;
use App\Models\ChildProfileModel;
use App\Services\PointService;
use App\Services\StreakService;
use Config\Services;
use DateTimeImmutable;
use DateTimeZone;

class ProfileController extends BaseController
{
    public function index(): string
    {
        $context = Services::trustedChildContext();
        $child = $context->child();
        $childId = (int) $child->id;

        return view('child/profile', [
            'title' => 'Profile',
            'activeNav' => 'profile',
            'child' => $child,
            'family' => $context->family(),
            'device' => $context->device(),
            'profile' => (new ChildProfileModel())->where('user_id', $childId)->first(),
            'balance' => (new PointService())->getBalance($childId),
            'streak' => (new StreakService())->currentStreak(
                $childId,
                new DateTimeImmutable('today', new DateTimeZone(app_timezone())),
            ),
        ]);
    }
}
