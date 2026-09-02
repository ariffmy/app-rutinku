<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Exceptions\AuthorizationException;
use App\Services\AuthService;
use App\Services\RoutineService;
use CodeIgniter\Exceptions\PageNotFoundException;
use InvalidArgumentException;

class RoutineTaskController extends BaseController
{
    public function new(int $routineId)
    {
        $parent = (new AuthService())->currentUser();

        try {
            $routine = (new RoutineService())->getForParent((int) $parent->id, $routineId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('parent/routine_tasks/form', [
            'title' => 'Task baharu',
            'routine' => $routine,
            'task' => null,
            'action' => route_to('parent.routine-tasks.create', $routineId),
        ]);
    }

    public function create(int $routineId)
    {
        if (! $this->validate($this->taskRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parent = (new AuthService())->currentUser();

        try {
            (new RoutineService())->createTask((int) $parent->id, $routineId, $this->taskInput());
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.routines.edit', $routineId))->with('success', 'Task telah ditambah.');
    }

    public function edit(int $taskId)
    {
        $parent = (new AuthService())->currentUser();

        try {
            $task = (new RoutineService())->getTaskForParent((int) $parent->id, $taskId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        return view('parent/routine_tasks/form', [
            'title' => 'Edit task',
            'routine' => $task['routine'],
            'task' => $task,
            'action' => route_to('parent.routine-tasks.update', $taskId),
        ]);
    }

    public function update(int $taskId)
    {
        if (! $this->validate($this->taskRules())) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $parent = (new AuthService())->currentUser();

        try {
            $routineId = (new RoutineService())->updateTask((int) $parent->id, $taskId, $this->taskInput());
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (InvalidArgumentException $exception) {
            return redirect()->back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.routines.edit', $routineId))->with('success', 'Task telah dikemas kini.');
    }

    public function delete(int $taskId)
    {
        $parent = (new AuthService())->currentUser();

        try {
            $result = (new RoutineService())->deleteTask((int) $parent->id, $taskId);
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        }

        $message = $result['action'] === 'archived'
            ? 'Task telah dinyahaktifkan kerana mempunyai sejarah completion.'
            : 'Task telah dipadam.';

        return redirect()->to(route_to('parent.routines.edit', $result['routine_id']))->with('success', $message);
    }

    private function taskRules(): array
    {
        return [
            'title' => 'required|max_length[160]',
            'description' => 'permit_empty|max_length[5000]',
            'task_time' => 'permit_empty|regex_match[/^(?:[01]\\d|2[0-3]):[0-5]\\d$/]',
            'points' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[10000]',
            'is_required' => 'required|in_list[0,1]',
            'sort_order' => 'required|integer|greater_than_equal_to[0]|less_than_equal_to[9999]',
            'is_active' => 'required|in_list[0,1]',
        ];
    }

    private function taskInput(): array
    {
        return [
            'title' => $this->request->getPost('title'),
            'description' => $this->request->getPost('description'),
            'task_time' => $this->request->getPost('task_time'),
            'points' => $this->request->getPost('points'),
            'is_required' => $this->request->getPost('is_required'),
            'sort_order' => $this->request->getPost('sort_order'),
            'is_active' => $this->request->getPost('is_active'),
        ];
    }
}
