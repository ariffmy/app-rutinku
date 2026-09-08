<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\ChildDeviceService;

class Home extends BaseController
{
    public function index()
    {
        $auth = new AuthService();
        if ($auth->isParent()) {
            return redirect()->to(route_to('parent.dashboard'));
        }
        if ($auth->isChild()) {
            return redirect()->to(route_to('child.today'));
        }

        $rawDeviceToken = (string) $this->request->getCookie(ChildDeviceService::requestCookieName());

        return redirect()->to($rawDeviceToken !== '' ? route_to('child.today') : route_to('parent.login'));
    }
}
