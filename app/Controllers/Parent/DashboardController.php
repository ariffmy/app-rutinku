<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Services\AuthService;
use App\Services\ChildActivityService;
use App\Services\FamilyService;
use App\Services\RankingService;
use App\Models\PointTransactionModel;
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

        $children = (new FamilyService())->children((int) $family['id']);
        $balances = [];
        if ($children !== []) {
            $rows = (new PointTransactionModel())
                ->select('child_user_id, COALESCE(SUM(points), 0) AS balance', false)
                ->whereIn('child_user_id', array_column($children, 'id'))
                ->groupBy('child_user_id')
                ->findAll();
            foreach ($rows as $row) {
                $balances[(int) $row['child_user_id']] = (int) $row['balance'];
            }
        }
        foreach ($children as &$child) {
            $child['balance'] = $balances[(int) $child['id']] ?? 0;
        }
        unset($child);

        return view('parent/dashboard', [
            'title' => 'Papan Pemuka',
            'currentUser' => $auth->currentUser(),
            'family' => $family,
            'activities' => (new ChildActivityService())->recentForParent((int) $auth->currentUser()->id),
            'todayRanking' => $ranking,
            'pendingRewards' => $pendingRewards,
            'children' => $children,
        ]);
    }
}
