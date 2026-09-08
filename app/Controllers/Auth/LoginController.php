<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Enums\UserRole;
use App\Services\AuthService;
use App\Services\ChildDeviceService;

class LoginController extends BaseController
{
    public function show()
    {
        $auth = new AuthService();
        if ($auth->isParent()) {
            return redirect()->to(route_to('parent.dashboard'));
        }
        if ($auth->isChild()) {
            return redirect()->to(route_to('child.today'));
        }

        return view('auth/login', ['title' => 'Log Masuk']);
    }

    public function login()
    {
        $rules = [
            'email' => 'required|valid_email|max_length[190]',
            'password' => 'required|max_length[4096]',
            'remember_me' => 'permit_empty|in_list[1]',
        ];

        if (! $this->validate($rules)) {
            $this->rememberSafeInput();

            return redirect()->back()->with('errors', $this->validator->getErrors());
        }

        $throttleKey = 'login-' . hash('sha256', $this->request->getIPAddress());
        if (! service('throttler')->check($throttleKey, 5, MINUTE)) {
            $this->rememberSafeInput();

            return redirect()->back()->with('error', 'Terlalu banyak cubaan. Cuba semula dalam satu minit.');
        }

        $role = (new AuthService())->loginByEmail(
            (string) $this->request->getPost('email'),
            (string) $this->request->getPost('password'),
            $this->request->getPost('remember_me') === '1',
        );

        if ($role === null) {
            $this->rememberSafeInput();

            return redirect()->back()->with('error', 'E-mel atau kata laluan tidak sah.');
        }

        $response = redirect()->to(route_to($role === UserRole::CHILD ? 'child.today' : 'parent.dashboard'));

        return $role === UserRole::CHILD ? (new ChildDeviceService())->clearCookie($response) : $response;
    }

    private function rememberSafeInput(): void
    {
        service('session')->setFlashdata('_ci_old_input', [
            'get' => [],
            'post' => [
                'email' => trim((string) $this->request->getPost('email')),
                'remember_me' => $this->request->getPost('remember_me') === '1' ? '1' : null,
            ],
        ]);
    }

    public function logout()
    {
        (new AuthService())->logout();

        $response = redirect()->to(route_to('parent.login'))->with('success', 'Anda telah log keluar.');

        return (new ChildDeviceService())->clearCookie($response);
    }
}
