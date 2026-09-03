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
            return redirect()->to(route_to('child.today'))->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('child.today'))->with('success', 'Syabas! Tugasan telah disiapkan.');
    }

    public function undo(int $taskId)
    {
        $child = Services::trustedChildContext()->child();

        try {
            (new TaskCompletionService())->undoTask((int) $child->id, $taskId, Time::now(app_timezone()));
        } catch (TaskCompletionException $exception) {
            return redirect()->to(route_to('child.today'))->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('child.today'))->with('success', 'Penyelesaian telah dibatalkan.');
    }
}
