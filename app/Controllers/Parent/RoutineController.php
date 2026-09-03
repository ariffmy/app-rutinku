<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Exceptions\AuthorizationException;
use App\Services\AuthService;
use App\Services\FamilyService;
use App\Services\RoutineService;
use CodeIgniter\Exceptions\PageNotFoundException;
use InvalidArgumentException;

class RoutineController extends BaseController
{
    public function index()
    {
        $parent = (new AuthService())->currentUser();
        $childId = $this->request->getGet('child');

        try {
            $routines = (new RoutineService())->listForParent(
                (int) $parent->id,
                is_numeric($childId) ? (int) $childId : null,
            );
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('parent/routines/index', [
            'title' => 'Routines',
            'routines' => $routines,
            'children' => $this->children(),
            'selectedChildId' => is_numeric($childId) ? (int) $childId : null,
        ]);
    }

    public function new()
    {
        return view('parent/routines/form', [
            'title' => 'Routine baharu',
            'routine' => null,
            'children' => $this->children(),
            'action' => route_to('parent.routines.create'),
        ]);
    }

    public function create()
    {
        if (! $this->validate($this->routineRules(allowAllChildren: true))) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parent = (new AuthService())->currentUser();

        try {
            $service = new RoutineService();
            if ($this->request->getPost('child_user_id') === 'all') {
                $routineIds = $service->createForAllChildren(
                    (int) $parent->id,
                    $this->routineInput(),
                    (array) $this->request->getPost('days'),
                );

                return redirect()->to(route_to('parent.routines'))->with('success',
                    'Routine telah dicipta untuk ' . count($routineIds) . ' anak aktif. Buka setiap routine untuk tambah task; setiap salinan diurus berasingan.');
            }

            $routineId = $service->create(
                (int) $parent->id,
                $this->routineInput(),
                (array) $this->request->getPost('days'),
            );
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.routines.edit', $routineId))->with('success', 'Routine telah dicipta. Tambah task di bawah.');
    }

    public function edit(int $routineId)
    {
        $parent = (new AuthService())->currentUser();

        try {
            $routine = (new RoutineService())->getForParent((int) $parent->id, $routineId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('parent/routines/form', [
            'title' => 'Edit routine',
            'routine' => $routine,
            'children' => $this->children(),
            'action' => route_to('parent.routines.update', $routineId),
        ]);
    }

    public function update(int $routineId)
    {
        if (! $this->validate($this->routineRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parent = (new AuthService())->currentUser();

        try {
            (new RoutineService())->update(
                (int) $parent->id,
                $routineId,
                $this->routineInput(),
                (array) $this->request->getPost('days'),
            );
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.routines.edit', $routineId))->with('success', 'Routine telah dikemas kini.');
    }

    public function delete(int $routineId)
    {
        $parent = (new AuthService())->currentUser();

        try {
            $action = (new RoutineService())->delete((int) $parent->id, $routineId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $message = $action === 'archived'
            ? 'Routine telah dinyahaktifkan kerana mempunyai sejarah completion.'
            : 'Routine telah dipadam.';

        return redirect()->to(route_to('parent.routines'))->with('success', $message);
    }

    private function children(): array
    {
        $auth = new AuthService();
        $family = $auth->currentFamily();

        return array_values(array_filter(
            (new FamilyService())->children((int) $family['id']),
            static fn (array $child): bool => (bool) $child['is_active'],
        ));
    }

    private function routineRules(bool $allowAllChildren = false): array
    {
        return [
            'child_user_id' => $allowAllChildren && $this->request->getPost('child_user_id') === 'all'
                ? 'required|in_list[all]'
                : 'required|is_natural_no_zero',
            'name' => 'required|max_length[120]',
            'description' => 'permit_empty|max_length[5000]',
            'type' => 'permit_empty|max_length[50]',
            'start_time' => 'permit_empty|regex_match[/^(?:[01]\\d|2[0-3]):[0-5]\\d$/]',
            'sort_order' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
            'is_active' => 'required|in_list[0,1]',
            'days' => 'required',
        ];
    }

    private function routineInput(): array
    {
        return [
            'child_user_id' => $this->request->getPost('child_user_id'),
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'type' => $this->request->getPost('type'),
            'start_time' => $this->request->getPost('start_time'),
            'sort_order' => $this->request->getPost('sort_order'),
            'is_active' => $this->request->getPost('is_active'),
        ];
    }
}
