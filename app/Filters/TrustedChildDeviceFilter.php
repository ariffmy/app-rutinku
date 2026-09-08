<?php

namespace App\Filters;

use App\Services\ChildDeviceService;
use Config\Services;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class TrustedChildDeviceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $context = Services::trustedChildContext();
        $context->clear();

        if ((new \App\Services\AuthService())->resolveChildSessionIntoContext()) {
            return null;
        }

        $rawToken = (string) $request->getCookie(ChildDeviceService::requestCookieName());
        $devices = new ChildDeviceService();

        if ($rawToken !== '' && $devices->resolveIntoContext($rawToken, $context)) {
            return null;
        }

        $response = redirect()->to(route_to('parent.login'))
            ->with('error', 'Sila log masuk menggunakan e-mel Anak.');

        if ($rawToken !== '') {
            $devices->clearCookie($response);
        }

        return $response;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
