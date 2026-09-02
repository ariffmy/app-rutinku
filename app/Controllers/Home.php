<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\ChildDeviceService;

class Home extends BaseController
{
    public function index()
    {
        if ((new AuthService())->isParent()) {
            return redirect()->to(route_to('parent.dashboard'));
        }

        $rawDeviceToken = (string) $this->request->getCookie(ChildDeviceService::requestCookieName());

        return redirect()->to($rawDeviceToken !== '' ? route_to('child.today') : route_to('parent.login'));
    }
}
