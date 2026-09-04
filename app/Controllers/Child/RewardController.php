<?php

namespace App\Controllers\Child;

use App\Controllers\BaseController;
use App\Exceptions\RewardException;
use App\Services\RewardService;
use CodeIgniter\I18n\Time;
use Config\Services;

class RewardController extends BaseController
{
    public function index(): string
    {
        $context = Services::trustedChildContext();

        return view('child/rewards', [
            'title' => 'Ganjaran',
            'child' => $context->child(),
            'profile' => (new \App\Models\ChildProfileModel())->where('user_id', (int) $context->child()->id)->first(),
            'catalogue' => (new RewardService())->childCatalogue((int) $context->child()->id),
            'activeNav' => 'rewards',
        ]);
    }

    public function redeem(int $rewardId)
    {
        $child = Services::trustedChildContext()->child();
        try {
            (new RewardService())->requestRedemption(
                (int) $child->id,
                $rewardId,
                Time::now(app_timezone()),
            );
        } catch (RewardException $exception) {
            return redirect()->to(route_to('child.rewards'))->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('child.rewards'))->with('success', 'Ganjaran telah diminta dan menunggu Ibu bapa.');
    }
}
