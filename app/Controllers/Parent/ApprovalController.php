<?php

namespace App\Controllers\Parent;

use App\Controllers\BaseController;
use App\Exceptions\AuthorizationException;
use App\Exceptions\TaskCompletionException;
use App\Services\AuthService;
use App\Services\TaskCompletionService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;

class ApprovalController extends BaseController
{
    public function approve(int $completionId)
    {
        $parent = (new AuthService())->currentUser();
        try {
            (new TaskCompletionService())->approveCompletion((int) $parent->id, $completionId, Time::now(app_timezone()));
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (TaskCompletionException $exception) {
            return redirect()->to(route_to('parent.dashboard'))->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.dashboard'))->with('success', 'Penyelesaian diluluskan dan mata telah diberikan.');
    }

    public function reject(int $completionId)
    {
        if (! $this->validate(['rejection_reason' => 'permit_empty|max_length[500]'])) {
            return redirect()->to(route_to('parent.dashboard'))->with('errors', $this->validator->getErrors());
        }
        $parent = (new AuthService())->currentUser();
        try {
            (new TaskCompletionService())->rejectCompletion(
                (int) $parent->id,
                $completionId,
                $this->request->getPost('rejection_reason'),
                Time::now(app_timezone()),
            );
        } catch (AuthorizationException) {
            throw PageNotFoundException::forPageNotFound();
        } catch (TaskCompletionException $exception) {
            return redirect()->to(route_to('parent.dashboard'))->with('error', $exception->getMessage());
        }

        return redirect()->to(route_to('parent.dashboard'))->with('success', 'Penyelesaian telah ditolak.');
    }
}
