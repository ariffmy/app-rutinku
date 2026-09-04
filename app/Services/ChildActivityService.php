<?php

namespace App\Services;

/** Read existing history so cancelled completions remain visible without duplicate logging. */
class ChildActivityService
{
    public function recentForParent(int $parentUserId): array
    {
        $parent = (new \App\Models\UserModel())->find($parentUserId);
        if ($parent === null || ! $parent->is_active || $parent->roleEnum() !== \App\Enums\UserRole::PARENT) {
            throw new \App\Exceptions\AuthorizationException('Hanya ibu bapa boleh melihat log aktiviti keluarga.');
        }
        $family = (new FamilyService())->currentFamilyForUser($parentUserId);
        if ($family === null) {
            return [];
        }
        $children = array_column((new FamilyService())->children((int) $family['id']), 'id');
        if ($children === []) {
            return [];
        }
        $db = db_connect();
        $points = $db->table('point_transactions p')->select('p.*, u.name AS child_name')
            ->join('users u', 'u.id = p.child_user_id')->whereIn('p.child_user_id', $children)
            ->orderBy('p.created_at', 'DESC')->orderBy('p.id', 'DESC')->limit(20)->get()->getResultArray();
        $activities = [];
        foreach ($points as $point) {
            $activities[] = ['key' => 'p' . $point['id'], 'child_name' => $point['child_name'],
                'at' => $point['created_at'], 'description' => ui_point_description($point),
                'detail' => ((int) $point['points'] > 0 ? '+' : '') . $point['points'] . ' mata'];
        }
        $requests = $db->table('reward_redemptions r')->select('r.*, u.name AS child_name, w.title AS reward_title')
            ->join('users u', 'u.id = r.child_user_id')->join('rewards w', 'w.id = r.reward_id')
            ->whereIn('r.child_user_id', $children)->where('w.family_id', (int) $family['id'])
            ->orderBy('r.requested_at', 'DESC')->orderBy('r.id', 'DESC')->limit(20)->get()->getResultArray();
        foreach ($requests as $request) {
            $activities[] = ['key' => 'r' . $request['id'], 'child_name' => $request['child_name'],
                'at' => $request['requested_at'], 'description' => 'Memohon ganjaran: ' . $request['reward_title'],
                'detail' => ui_label('redemption', $request['status'])];
        }
        usort($activities, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']) ?: strnatcmp($b['key'], $a['key']));
        return array_slice($activities, 0, 20);
    }
}
