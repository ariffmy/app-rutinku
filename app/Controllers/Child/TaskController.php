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
            $completion = (new TaskCompletionService())->completeTask((int) $child->id, $taskId, Time::now(app_timezone()));
        } catch (TaskCompletionException $exception) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['message' => $exception->getMessage(), 'csrf' => csrf_hash()]);
            }
            return redirect()->to(route_to('child.today'))->with('error', $exception->getMessage());
        }

        $status = $completion['status'] ?? 'completed';
        $message = $status === 'pending'
            ? 'Tugasan dihantar dan sedang menunggu kelulusan ibu bapa.'
            : 'Syabas! Tugasan telah disiapkan.';

        return $this->taskResponse($taskId, $status, $message);
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

        return $this->taskResponse($taskId, 'not_completed', 'Penyelesaian telah dibatalkan.');
    }

    private function taskResponse(int $taskId, string $status, string $message)
    {
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['id' => $taskId, 'status' => $status, 'completed' => $status === 'completed', 'message' => $message,
                'balance' => (new \App\Services\PointService())->getBalance((int) Services::trustedChildContext()->child()->id),
                'csrf' => csrf_hash()]);
        }
        return redirect()->to(route_to('child.today'))->with('success', $message);
    }
}
