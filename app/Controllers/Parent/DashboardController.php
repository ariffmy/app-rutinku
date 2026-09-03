<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Services\AuthService;
use App\Services\FamilyService;
use App\Services\RankingService;
use App\Models\RewardRedemptionModel;
use DateTimeImmutable;
use DateTimeZone;

class DashboardController extends BaseController
{
    public function index()
    {
        $auth = new AuthService();
        $family = $auth->currentFamily();
        $ranking = (new RankingService())->daily(
            (int) $auth->currentUser()->id,
            new DateTimeImmutable('now', new DateTimeZone(app_timezone())),
        );
        $pendingRewards = (new RewardRedemptionModel())
            ->join('rewards', 'rewards.id = reward_redemptions.reward_id')
            ->where('rewards.family_id', (int) $family['id'])
            ->where('reward_redemptions.status', 'pending')
            ->countAllResults();

        return view('parent/dashboard', [
            'title' => 'Papan Pemuka',
            'currentUser' => $auth->currentUser(),
            'family' => $family,
            'children' => (new FamilyService())->children((int) $family['id']),
            'todayRanking' => $ranking,
            'pendingRewards' => $pendingRewards,
        ]);
    }
}
