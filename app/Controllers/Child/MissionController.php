<?php

namespace App\Controllers\Child;

use App\Controllers\BaseController;
use App\Models\ChildProfileModel;
use App\Services\MissionService;
use App\Services\PointService;
use CodeIgniter\I18n\Time;
use Config\Services;

class MissionController extends BaseController
{
    public function index(): string
    {
        $context = Services::trustedChildContext();
        $childId = (int) $context->child()->id;
        return view('child/missions', [
            'title' => 'Misi Minggu Ini',
            'child' => $context->child(),
            'family' => $context->family(),
            'profile' => (new ChildProfileModel())->where('user_id', $childId)->first(),
            'balance' => (new PointService())->getBalance($childId),
            'missions' => (new MissionService())->listForChild($childId, Time::now(app_timezone())),
            'activeNav' => 'missions',
        ]);
    }
}
