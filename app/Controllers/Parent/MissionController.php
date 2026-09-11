<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Exceptions\AuthorizationException;
use App\Exceptions\MissionException;
use App\Services\AuthService;
use App\Services\FamilyService;
use App\Services\MissionService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;

class MissionController extends BaseController
{
    public function index(): string
    {
        $parent = (new AuthService())->currentUser();
        return view('parent/missions/index', ['title' => 'Misi Minggu Ini']
            + (new MissionService())->listForParent((int) $parent->id, Time::now(app_timezone())));
    }

    public function new(): string
    {
        $auth = new AuthService();
        $family = $auth->currentFamily();
        $today = Time::now(app_timezone());
        $monday = $today->modify('monday this week');
        return view('parent/missions/form', [
            'title' => 'Tambah Misi',
            'children' => array_values(array_filter(
                (new FamilyService())->children((int) $family['id']),
                static fn (array $child): bool => (bool) $child['is_active'],
            )),
            'defaultStart' => $monday->format('Y-m-d'),
            'defaultEnd' => $monday->addDays(6)->format('Y-m-d'),
        ]);
    }

    public function create()
    {
        if (! $this->validate([
            'title' => 'required|max_length[160]',
            'description' => 'permit_empty|max_length[1000]',
            'mission_type' => 'required|in_list[' . implode(',', MissionService::TYPES) . ']',
            'target_value' => 'required|is_natural_no_zero|less_than_equal_to[1000]',
            'bonus_points' => 'required|is_natural|less_than_equal_to[1000000]',
            'start_date' => 'required|valid_date[Y-m-d]',
            'end_date' => 'required|valid_date[Y-m-d]',
            'child_ids' => 'required',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parent = (new AuthService())->currentUser();
        try {
            (new MissionService())->create((int) $parent->id, [
                'title' => $this->request->getPost('title'),
                'description' => $this->request->getPost('description'),
                'mission_type' => $this->request->getPost('mission_type'),
                'target_value' => $this->request->getPost('target_value'),
                'bonus_points' => $this->request->getPost('bonus_points'),
                'start_date' => $this->request->getPost('start_date'),
                'end_date' => $this->request->getPost('end_date'),
            ], (array) $this->request->getPost('child_ids'), Time::now(app_timezone()));
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (MissionException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
        return redirect()->to(route_to('parent.missions'))->with('success', 'Misi mingguan telah dicipta.');
    }
}
