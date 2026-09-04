<?php
namespace App\Controllers;
use App\Services\AuthService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Services;

class FamilyImageController extends BaseController
{
    public function parentImage(string $name)
    {
        return $this->show($name, (int) (new AuthService())->currentFamily()['id']);
    }
    public function childImage(string $name)
    {
        return $this->show($name, (int) Services::trustedChildContext()->family()['id']);
    }
    private function show(string $name, int $familyId)
    {
        if (! preg_match('/\A[a-f0-9]{32}\.jpg\z/', $name)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $path = WRITEPATH . 'uploads/family-' . $familyId . '/' . $name;
        if (! is_file($path)) {
            throw PageNotFoundException::forPageNotFound();
        }
        return $this->response->setContentType('image/jpeg')->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-store')->setBody(file_get_contents($path));
    }
}
