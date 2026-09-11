<?php

namespace App\Controllers\Child;

use App\Controllers\BaseController;
use App\Models\ChildProfileModel;
use App\Services\AchievementService;
use App\Services\PointService;
use Config\Services;

class AchievementController extends BaseController
{
    public function index(): string
    {
        $context = Services::trustedChildContext();
        $childId = (int) $context->child()->id;
        return view('child/achievements', [
            'title' => 'Pencapaian',
            'child' => $context->child(),
            'family' => $context->family(),
            'profile' => (new ChildProfileModel())->where('user_id', $childId)->first(),
            'balance' => (new PointService())->getBalance($childId),
            'achievements' => (new AchievementService())->catalogueForChild($childId),
            'activeNav' => 'achievements',
        ]);
    }
}
