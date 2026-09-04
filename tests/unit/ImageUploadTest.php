<?php
namespace Tests\Unit;
use App\Services\ImageUploadService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

final class ImageUploadTest extends CIUnitTestCase
{
    public function testImageIsReencodedWithRandomNameAndPrivateLocation(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'rutinku-image-test-');
        $pixels = imagecreatetruecolor(3, 3);
        imagepng($pixels, $temporary);
        $file = $this->getMockBuilder(UploadedFile::class)->setConstructorArgs([$temporary, 'photo.png', 'image/png', filesize($temporary), UPLOAD_ERR_OK])->onlyMethods(['isValid'])->getMock();
        $file->method('isValid')->willReturn(true);
        $name = null;
        try {
            $name = (new ImageUploadService())->store($file, 987654321);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{32}\.jpg$/', $name);
            $this->assertSame(IMAGETYPE_JPEG, getimagesize(WRITEPATH . 'uploads/family-987654321/' . $name)[2]);
        } finally {
            unlink($temporary);
            if ($name) unlink(WRITEPATH . 'uploads/family-987654321/' . $name);
            if (is_dir(WRITEPATH . 'uploads/family-987654321')) rmdir(WRITEPATH . 'uploads/family-987654321');
        }
    }
    public function testInvalidImageIsRejected(): void
    {
        $temporary = tempnam(sys_get_temp_dir(), 'rutinku-image-test-');
        file_put_contents($temporary, '<svg onload="alert(1)"></svg>');
        $file = $this->getMockBuilder(UploadedFile::class)->setConstructorArgs([$temporary, 'photo.jpg', 'image/jpeg', filesize($temporary), UPLOAD_ERR_OK])->onlyMethods(['isValid'])->getMock();
        $file->method('isValid')->willReturn(true);
        try {
            $this->expectException(\InvalidArgumentException::class);
            (new ImageUploadService())->store($file, 987654321);
        } finally {
            unlink($temporary);
        }
    }
}
