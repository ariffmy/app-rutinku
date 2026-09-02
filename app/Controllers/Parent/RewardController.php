<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Exceptions\AuthorizationException;
use App\Exceptions\RewardException;
use App\Services\AuthService;
use App\Services\RewardService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use InvalidArgumentException;

class RewardController extends BaseController
{
    public function index(): string
    {
        $parent = (new AuthService())->currentUser();
        $data = (new RewardService())->listForParent((int) $parent->id);

        return view('parent/rewards/index', ['title' => 'Rewards'] + $data);
    }

    public function new(): string
    {
        return view('parent/rewards/form', [
            'title' => 'Reward baharu',
            'reward' => null,
            'action' => route_to('parent.rewards.create'),
        ]);
    }

    public function create()
    {
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $parent = (new AuthService())->currentUser();
        try {
            (new RewardService())->create((int) $parent->id, $this->input());
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.rewards'))->with('success', 'Reward telah dicipta.');
    }

    public function edit(int $rewardId): string
    {
        $parent = (new AuthService())->currentUser();
        try {
            $reward = (new RewardService())->getForParent((int) $parent->id, $rewardId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('parent/rewards/form', [
            'title' => 'Edit reward',
            'reward' => $reward,
            'action' => route_to('parent.rewards.update', $rewardId),
        ]);
    }

    public function update(int $rewardId)
    {
        if (! $this->validate($this->rules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }
        $parent = (new AuthService())->currentUser();
        try {
            (new RewardService())->update((int) $parent->id, $rewardId, $this->input());
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.rewards'))->with('success', 'Reward telah dikemas kini.');
    }

    public function archive(int $rewardId)
    {
        $parent = (new AuthService())->currentUser();
        try {
            (new RewardService())->archive((int) $parent->id, $rewardId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(route_to('parent.rewards'))->with('success', 'Reward telah dinyahaktifkan.');
    }

    public function approve(int $redemptionId)
    {
        $parent = (new AuthService())->currentUser();
        try {
            (new RewardService())->approve((int) $parent->id, $redemptionId, Time::now(app_timezone()));
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (RewardException $exception) {
            return redirect()->to(route_to('parent.rewards'))->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.rewards'))->with('success', 'Reward redemption telah diluluskan.');
    }

    public function reject(int $redemptionId)
    {
        $parent = (new AuthService())->currentUser();
        try {
            (new RewardService())->reject((int) $parent->id, $redemptionId, Time::now(app_timezone()));
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (RewardException $exception) {
            return redirect()->to(route_to('parent.rewards'))->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.rewards'))->with('success', 'Reward redemption telah ditolak.');
    }

    private function rules(): array
    {
        return [
            'title' => 'required|max_length[160]',
            'description' => 'permit_empty|max_length[5000]',
            'points_required' => 'required|is_natural_no_zero|less_than_equal_to[1000000]',
            'image' => 'permit_empty|max_length[255]',
            'is_active' => 'required|in_list[0,1]',
        ];
    }

    private function input(): array
    {
        return [
            'title' => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'points_required' => $this->request->getPost('points_required'),
            'image' => $this->request->getPost('image'),
            'is_active' => $this->request->getPost('is_active'),
        ];
    }
}
