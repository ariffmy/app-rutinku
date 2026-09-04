<?php

namespace App\Controllers\Child;

use App\Controllers\BaseController;
use App\Exceptions\TaskCompletionException;
use App\Services\TaskCompletionService;
use CodeIgniter\I18n\Time;
use Config\Services;

class TaskController extends BaseController
{
    public function complete(int $taskId)
    {
        $child = Services::trustedChildContext()->child();

        try {
            (new TaskCompletionService())->completeTask((int) $child->id, $taskId, Time::now(app_timezone()));
        } catch (TaskCompletionException $exception) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['message' => $exception->getMessage(), 'csrf' => csrf_hash()]);
            }
            return redirect()->to(route_to('child.today'))->with('error', $exception->getMessage());
        }

        return $this->taskResponse($taskId, true, 'Syabas! Tugasan telah disiapkan.');
    }

    public function undo(int $taskId)
    {
        $child = Services::trustedChildContext()->child();

        try {
            (new TaskCompletionService())->undoTask((int) $child->id, $taskId, Time::now(app_timezone()));
        } catch (TaskCompletionException $exception) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['message' => $exception->getMessage(), 'csrf' => csrf_hash()]);
            }
            return redirect()->to(route_to('child.today'))->with('error', $exception->getMessage());
        }

        return $this->taskResponse($taskId, false, 'Penyelesaian telah dibatalkan.');
    }

    private function taskResponse(int $taskId, bool $completed, string $message)
    {
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['id' => $taskId, 'completed' => $completed, 'message' => $message,
                'balance' => (new \App\Services\PointService())->getBalance((int) Services::trustedChildContext()->child()->id),
                'csrf' => csrf_hash()]);
        }
        return redirect()->to(route_to('child.today'))->with('success', $message);
    }
}
