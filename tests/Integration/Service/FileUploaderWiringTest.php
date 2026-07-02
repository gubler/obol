<?php

// ABOUTME: Integration test that the container wires FileUploader to the configured upload paths.
// ABOUTME: Guards the #[Autowire] param wiring - a wrong parameter name would fail service resolution.

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Service\FileUploader;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploaderWiringTest extends KernelTestCase
{
    private ?string $uploadedAbsolutePath = null;

    protected function tearDown(): void
    {
        if (null !== $this->uploadedAbsolutePath && is_file(filename: $this->uploadedAbsolutePath)) {
            unlink(filename: $this->uploadedAbsolutePath);
        }

        parent::tearDown();
    }

    public function testContainerWiresConfiguredUploadPaths(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $uploader = $container->get(id: FileUploader::class);
        self::assertInstanceOf(FileUploader::class, $uploader);

        $result = $uploader->upload(file: self::createTempUploadedFile());

        // publicPath is wired from app.uploads_public_path ('uploads/logos').
        self::assertStringStartsWith('uploads/logos/', $result);

        // targetDirectory is wired from app.uploads_directory (public/uploads/logos);
        // the moved file must land there.
        $projectDir = $container->getParameter(name: 'kernel.project_dir');
        $this->uploadedAbsolutePath = $projectDir . '/public/' . $result;
        self::assertFileExists($this->uploadedAbsolutePath);
    }

    private static function createTempUploadedFile(): UploadedFile
    {
        $tempFile = tempnam(directory: sys_get_temp_dir(), prefix: 'upload_wiring_test_');
        \assert(false !== $tempFile);
        file_put_contents(filename: $tempFile, data: 'test content');

        return new UploadedFile(
            path: $tempFile,
            originalName: 'logo.png',
            mimeType: 'image/png',
            test: true,
        );
    }
}
