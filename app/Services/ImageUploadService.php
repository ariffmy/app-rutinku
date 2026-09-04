<?php
namespace App\Services;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;

class ImageUploadService
{
    public function store(?UploadedFile $file, int $familyId): ?string
    {
        if ($file === null || $file->getError() === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (! $file->isValid() || $file->getSize() > 4 * 1024 * 1024) {
            throw new InvalidArgumentException('Pilih gambar JPG, PNG atau WebP tidak melebihi 4 MB.');
        }
        $info = @getimagesize($file->getTempName());
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] * $info[1] > 12000000) {
            throw new InvalidArgumentException('Gambar tidak sah atau terlalu besar (maksimum 12 megapiksel).');
        }
        if (! function_exists('imagecreatefromstring')) {
            throw new InvalidArgumentException('Pemprosesan gambar belum tersedia pada pelayan.');
        }
        $source = @imagecreatefromstring(file_get_contents($file->getTempName()));
        if ($source === false) {
            throw new InvalidArgumentException('Gambar tidak dapat dibaca.');
        }
        $scale = min(1, 1024 / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $output = imagecreatetruecolor($width, $height);
        imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
        imagecopyresampled($output, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        $directory = WRITEPATH . 'uploads/family-' . $familyId;
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new InvalidArgumentException('Gambar tidak dapat disimpan.');
        }
        $name = bin2hex(random_bytes(16)) . '.jpg';
        if (! imagejpeg($output, $directory . '/' . $name, 85)) {
            throw new InvalidArgumentException('Gambar tidak dapat disimpan.');
        }
        return $name;
    }
}
