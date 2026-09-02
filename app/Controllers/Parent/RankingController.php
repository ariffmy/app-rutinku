<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Services\AuthService;
use App\Services\RankingService;
use DateTimeImmutable;
use DateTimeZone;

class RankingController extends BaseController
{
    public function index(): string
    {
        $period = (string) ($this->request->getGet('period') ?: 'daily');
        if (! in_array($period, ['daily', 'weekly', 'monthly'], true)) {
            $period = 'daily';
        }
        $dateInput = (string) ($this->request->getGet('date') ?: date('Y-m-d'));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput, new DateTimeZone(app_timezone()));
        if ($date === false || $date->format('Y-m-d') !== $dateInput) {
            $date = new DateTimeImmutable('today', new DateTimeZone(app_timezone()));
            $dateInput = $date->format('Y-m-d');
        }

        $parent = (new AuthService())->currentUser();
        $ranking = (new RankingService())->{$period}((int) $parent->id, $date);

        return view('parent/ranking/index', [
            'title' => 'Ranking',
            'period' => $period,
            'dateInput' => $dateInput,
            'ranking' => $ranking,
        ]);
    }
}
