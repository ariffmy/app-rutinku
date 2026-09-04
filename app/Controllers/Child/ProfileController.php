<?php

namespace App\Controllers\Child;

use App\Controllers\BaseController;
use App\Models\ChildProfileModel;
use App\Services\PointService;
use Config\Services;

class ProfileController extends BaseController
{
    public function index(): string
    {
        $context = Services::trustedChildContext();
        $child = $context->child();
        $childId = (int) $child->id;

        return view('child/profile', [
            'title' => 'Profil',
            'activeNav' => 'profile',
            'child' => $child,
            'family' => $context->family(),
            'parents' => db_connect()->table('users')->select('users.name')->join('family_users', 'family_users.user_id = users.id')
                ->where('family_users.family_id', (int) $context->family()['id'])->where('users.role', 'parent')->orderBy('users.name')->get()->getResultArray(),
            'profile' => (new ChildProfileModel())->where('user_id', $childId)->first(),
            'balance' => (new PointService())->getBalance($childId),
        ]);
    }

    public function update()
    {
        $context = Services::trustedChildContext();
        $model = new ChildProfileModel();
        $profile = $model->where('user_id', (int) $context->child()->id)->first();
        try {
            if ((int) $this->request->getServer('CONTENT_LENGTH') > 5 * 1024 * 1024) {
                throw new \InvalidArgumentException('Saiz muat naik terlalu besar. Pilih gambar tidak melebihi 4 MB.');
            }
            $choice = (string) $this->request->getPost('avatar');
            if ($choice !== '' && ! array_key_exists($choice, ui_avatar_options())) {
                throw new \InvalidArgumentException('Pilih avatar yang disediakan.');
            }
            $avatar = (new \App\Services\ImageUploadService())->store($this->request->getFile('photo'), (int) $context->family()['id']);
            $avatar ??= $choice !== '' ? $choice : ($profile['avatar'] ?? 'user');
            if ($profile) {
                $saved = $model->skipValidation(true)->update((int) $profile['id'], ['avatar' => $avatar]);
            } else {
                $saved = $model->insert(['user_id' => (int) $context->child()->id, 'avatar' => $avatar, 'is_ranking_eligible' => 1]);
            }
            if (! $saved) {
                throw new \InvalidArgumentException('Profil tidak dapat disimpan.');
            }
        } catch (\InvalidArgumentException $exception) {
            return redirect()->to(route_to('child.profile'))->with('error', $exception->getMessage());
        }
        return redirect()->to(route_to('child.profile'))->with('success', 'Gambar profil telah dikemas kini.');
    }
}
