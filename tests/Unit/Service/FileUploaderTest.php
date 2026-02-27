<?php

// ABOUTME: Unit tests for FileUploader service ensuring proper file handling.
// ABOUTME: Tests verify file upload, unique naming, and correct path generation.

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\FileUploader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploaderTest extends TestCase
{
    private string $targetDirectory;

    protected function setUp(): void
    {
        $this->targetDirectory = sys_get_temp_dir() . '/obol_test_uploads_' . bin2hex(random_bytes(8));
        mkdir(directory: $this->targetDirectory, recursive: true);
    }

    protected function tearDown(): void
    {
        $files = glob(pattern: $this->targetDirectory . '/*');
        if (false !== $files) {
            array_map(callback: 'unlink', array: $files);
        }
        if (is_dir(filename: $this->targetDirectory)) {
            rmdir(directory: $this->targetDirectory);
        }
    }

    public function testUploadReturnsRelativePath(): void
    {
        $uploader = new FileUploader(targetDirectory: $this->targetDirectory);
        $file = $this->createTempUploadedFile();

        $result = $uploader->upload(file: $file);

        self::assertStringStartsWith('uploads/logos/', $result);
    }

    public function testUploadMovesFileToTargetDirectory(): void
    {
        $uploader = new FileUploader(targetDirectory: $this->targetDirectory);
        $file = $this->createTempUploadedFile();

        $result = $uploader->upload(file: $file);

        $filename = basename(path: $result);
        self::assertFileExists($this->targetDirectory . '/' . $filename);
    }

    public function testUploadGeneratesUniqueFilenames(): void
    {
        $uploader = new FileUploader(targetDirectory: $this->targetDirectory);

        $file1 = $this->createTempUploadedFile();
        $file2 = $this->createTempUploadedFile();

        $result1 = $uploader->upload(file: $file1);
        $result2 = $uploader->upload(file: $file2);

        self::assertNotSame($result1, $result2);
    }

    private function createTempUploadedFile(): UploadedFile
    {
        $tempFile = tempnam(directory: sys_get_temp_dir(), prefix: 'upload_test_');
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
