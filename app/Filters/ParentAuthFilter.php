<?php

namespace App\Filters;

use App\Services\AuthService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

class ParentAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        Services::trustedChildContext()->clear();

        if (! (new AuthService())->isParent()) {
            service('session')->remove(['user_id', 'user_role', 'family_id', 'auth_expires_at']);

            return redirect()->to(route_to('parent.login'))->with('error', 'Sila log masuk sebagai Parent.');
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
