<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Exceptions\AuthorizationException;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\ChildDeviceService;
use CodeIgniter\Exceptions\PageNotFoundException;

class DeviceController extends BaseController
{
    public function index(int $childId)
    {
        $auth = new AuthService();
        $parent = $auth->currentUser();
        $child = (new UserModel())->find($childId);

        try {
            $devices = (new ChildDeviceService())->devicesForChild((int) $parent->id, $childId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('parent/devices/index', [
            'title' => 'Peranti ' . $child->name,
            'child' => $child,
            'devices' => $devices,
        ]);
    }

    public function setup(int $childId)
    {
        if (! $this->validate([
            'device_name' => 'permit_empty|max_length[120]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $auth = new AuthService();
        $parent = $auth->currentUser();
        $deviceType = $this->request->getUserAgent()->isMobile() ? 'mobile' : 'browser';
        $devices = new ChildDeviceService();

        try {
            $provisioned = $devices->provision(
                (int) $parent->id,
                $childId,
                (string) $this->request->getPost('device_name'),
                $deviceType,
            );
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $auth->logoutParent();
        $response = redirect()->to(route_to('child.today'));

        return $devices->attachCookie($response, $provisioned->rawToken);
    }

    public function revoke(int $childId, int $deviceId)
    {
        $parent = (new AuthService())->currentUser();

        try {
            $revoked = (new ChildDeviceService())->revoke((int) $parent->id, $childId, $deviceId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $message = $revoked ? 'Peranti telah dibatalkan.' : 'Peranti itu sudah tidak aktif.';

        return redirect()->to(route_to('parent.child.devices', $childId))->with('success', $message);
    }

    public function delete(int $childId, int $deviceId)
    {
        try {
            (new ChildDeviceService())->deleteInactive((int) (new AuthService())->currentUser()->id, $childId, $deviceId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (\InvalidArgumentException $exception) {
            return redirect()->to(route_to('parent.child.devices', $childId))->with('error', $exception->getMessage());
        }
        return redirect()->to(route_to('parent.child.devices', $childId))
            ->with('success', 'Rekod peranti tidak aktif telah dipadam. Sejarah audit dikekalkan.');
    }

    public function reset(int $childId)
    {
        $parent = (new AuthService())->currentUser();

        try {
            $count = (new ChildDeviceService())->reset((int) $parent->id, $childId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return redirect()->to(route_to('parent.child.devices', $childId))
            ->with('success', $count > 0 ? 'Semua peranti dipercayai telah ditetapkan semula.' : 'Tiada peranti dipercayai aktif untuk ditetapkan semula.');
    }
}
