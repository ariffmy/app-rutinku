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
            'title' => 'Anak-anak',
            'children' => (new ChildManagementService())->allForParent((int) $parent->id),
        ]);
    }

    public function new(): string
    {
        return view('parent/children/form', ['title' => 'Tambah Anak', 'child' => null, 'profile' => null]);
    }

    public function create()
    {
        if (! $this->validate($this->rules(false))) {
            $this->rememberSafeInput();
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        try {
            $parent = (new AuthService())->currentUser();
            (new ChildManagementService())->create((int) $parent->id, $this->payload(false));

            return redirect()->to(route_to('parent.children'))->with('success', 'Anak berjaya ditambah.');
        } catch (Throwable $exception) {
            $this->rememberSafeInput();
            return redirect()->back()->with('error', $exception->getMessage());
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
            'title' => 'Sunting Anak',
            'child' => $record['user'],
            'profile' => $record['profile'],
        ]);
    }

    public function update(int $childId)
    {
        if (! $this->validate($this->rules(true))) {
            $this->rememberSafeInput();
            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        try {
            $parent = (new AuthService())->currentUser();
            (new ChildManagementService())->getForParent((int) $parent->id, $childId);
            (new ChildManagementService())->update((int) $parent->id, $childId, $this->payload(true));

            return redirect()->to(route_to('parent.children'))->with('success', 'Profil Anak berjaya dikemas kini.');
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (Throwable $exception) {
            $this->rememberSafeInput();
            return redirect()->back()->with('error', $exception->getMessage());
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
            $rules['email'] = 'permit_empty|valid_email|max_length[190]';
            $rules['is_active'] = 'required|in_list[0,1]';
            $rules['password'] = 'permit_empty|min_length[8]|max_length[72]';
            $rules['password_confirm'] = 'permit_empty|matches[password]';
        } else {
            $rules['email'] = 'required|valid_email|max_length[190]';
            $rules['password'] = 'required|min_length[8]|max_length[72]';
            $rules['password_confirm'] = 'required|matches[password]';
        }

        return $rules;
    }

    private function payload(bool $updating): array
    {
        $payload = [
            'name' => $this->request->getPost('name'),
            'email' => $this->request->getPost('email'),
            'date_of_birth' => $this->request->getPost('date_of_birth'),
            'is_ranking_eligible' => $this->request->getPost('is_ranking_eligible'),
        ];
        if ($updating) {
            $payload['is_active'] = $this->request->getPost('is_active');
        }
        if ((string) $this->request->getPost('password') !== '') {
            $payload['password'] = (string) $this->request->getPost('password');
        }

        $choice = (string) $this->request->getPost('avatar');
        if ($choice !== '' && ! array_key_exists($choice, ui_avatar_options())) {
            throw new \InvalidArgumentException('Pilih avatar yang disediakan.');
        }
        $family = (new AuthService())->currentFamily();
        $image = (new \App\Services\ImageUploadService())->store($this->request->getFile('photo'), (int) $family['id']);
        if ($image !== null || $choice !== '') {
            $payload['avatar'] = $image ?? $choice;
        }

        return $payload;
    }

    private function rememberSafeInput(): void
    {
        $post = $this->request->getPost();
        unset($post['password'], $post['password_confirm'], $post[csrf_token()]);
        service('session')->setFlashdata('_ci_old_input', ['get' => [], 'post' => $post]);
    }
}
