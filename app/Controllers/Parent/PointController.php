<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Exceptions\AuthorizationException;
use App\Exceptions\PointException;
use App\Services\AuthService;
use App\Services\FamilyService;
use App\Services\PointService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;

class PointController extends BaseController
{
    public function index()
    {
        $auth = new AuthService();
        $parent = $auth->currentUser();
        $family = $auth->currentFamily();
        $children = array_values(array_filter(
            (new FamilyService())->children((int) $family['id']),
            static fn (array $child): bool => (bool) $child['is_active'],
        ));
        $requestedChildId = $this->request->getGet('child');
        $selectedChildId = is_numeric($requestedChildId)
            ? (int) $requestedChildId
            : (isset($children[0]) ? (int) $children[0]['id'] : null);
        $account = null;

        if ($selectedChildId !== null) {
            try {
                $account = (new PointService())->getParentAccount((int) $parent->id, $selectedChildId);
            } catch (AuthorizationException) {
                throw PageNotFoundException::forPageNotFound();
            }
        }

        return view('parent/points/index', [
            'title' => 'Points',
            'children' => $children,
            'selectedChildId' => $selectedChildId,
            'account' => $account,
        ]);
    }

    public function adjust()
    {
        if (! $this->validate([
            'child_user_id' => 'required|is_natural_no_zero',
            'points' => 'required|regex_match[/^-?[1-9]\d*$/]',
            'reason' => 'required|max_length[500]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parent = (new AuthService())->currentUser();
        $childUserId = (int) $this->request->getPost('child_user_id');

        try {
            (new PointService())->manualAdjustment(
                (int) $parent->id,
                $childUserId,
                (int) $this->request->getPost('points'),
                (string) $this->request->getPost('reason'),
                Time::now(app_timezone()),
            );
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (PointException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.points') . '?child=' . $childUserId)
            ->with('success', 'Adjustment points telah direkodkan.');
    }
}
