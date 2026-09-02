<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Exceptions\AuthorizationException;
use App\Services\AuthService;
use App\Services\ChildManagementService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

class ChildController extends BaseController
{
    public function index(): string
    {
        $parent = (new AuthService())->currentUser();

        return view('parent/children/index', [
            'title' => 'Children',
            'children' => (new ChildManagementService())->allForParent((int) $parent->id),
        ]);
    }

    public function new(): string
    {
        return view('parent/children/form', ['title' => 'Tambah Child', 'child' => null, 'profile' => null]);
    }

    public function create()
    {
        if (! $this->validate($this->rules(false))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $parent = (new AuthService())->currentUser();
            (new ChildManagementService())->create((int) $parent->id, $this->payload(false));

            return redirect()->to(route_to('parent.children'))->with('success', 'Child berjaya ditambah.');
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
    }

    public function edit(int $childId): string
    {
        try {
            $parent = (new AuthService())->currentUser();
            $record = (new ChildManagementService())->getForParent((int) $parent->id, $childId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('parent/children/form', [
            'title' => 'Edit Child',
            'child' => $record['user'],
            'profile' => $record['profile'],
        ]);
    }

    public function update(int $childId)
    {
        if (! $this->validate($this->rules(true))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        try {
            $parent = (new AuthService())->currentUser();
            (new ChildManagementService())->update((int) $parent->id, $childId, $this->payload(true));

            return redirect()->to(route_to('parent.children'))->with('success', 'Profil Child berjaya dikemas kini.');
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (Throwable $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }
    }

    private function rules(bool $updating): array
    {
        $rules = [
            'name' => 'required|max_length[120]',
            'date_of_birth' => 'permit_empty|valid_date[Y-m-d]',
            'is_ranking_eligible' => 'required|in_list[0,1]',
        ];
        if ($updating) {
            $rules['is_active'] = 'required|in_list[0,1]';
        }

        return $rules;
    }

    private function payload(bool $updating): array
    {
        $payload = [
            'name' => $this->request->getPost('name'),
            'date_of_birth' => $this->request->getPost('date_of_birth'),
            'is_ranking_eligible' => $this->request->getPost('is_ranking_eligible'),
        ];
        if ($updating) {
            $payload['is_active'] = $this->request->getPost('is_active');
        }

        return $payload;
    }
}
