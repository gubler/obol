<?php

// ABOUTME: Unit tests for FileUploader service ensuring proper file handling.
// ABOUTME: Tests verify file upload, unique naming, and correct path generation.

declare(strict_types=1);

use App\Service\FileUploader;
use Symfony\Component\HttpFoundation\File\UploadedFile;

beforeEach(function (): void {
    $this->targetDirectory = sys_get_temp_dir() . '/obol_test_uploads_' . bin2hex(random_bytes(8));
    mkdir(directory: $this->targetDirectory, recursive: true);
});

afterEach(function (): void {
    $files = glob(pattern: $this->targetDirectory . '/*');
    if (false !== $files) {
        array_map(callback: 'unlink', array: $files);
    }
    if (is_dir(filename: $this->targetDirectory)) {
        rmdir(directory: $this->targetDirectory);
    }
});

test('upload returns relative path', function (): void {
    $uploader = new FileUploader(targetDirectory: $this->targetDirectory);
    $file = createTempUploadedFile();

    $result = $uploader->upload(file: $file);

    expect($result)->toStartWith('uploads/logos/');
});

test('upload moves file to target directory', function (): void {
    $uploader = new FileUploader(targetDirectory: $this->targetDirectory);
    $file = createTempUploadedFile();

    $result = $uploader->upload(file: $file);

    $filename = basename(path: $result);
    expect($this->targetDirectory . '/' . $filename)->toBeFile();
});

test('upload generates unique filenames', function (): void {
    $uploader = new FileUploader(targetDirectory: $this->targetDirectory);

    $file1 = createTempUploadedFile();
    $file2 = createTempUploadedFile();

    $result1 = $uploader->upload(file: $file1);
    $result2 = $uploader->upload(file: $file2);

    expect($result1)->not->toBe($result2);
});

function createTempUploadedFile(): UploadedFile
{
    $tempFile = tempnam(directory: sys_get_temp_dir(), prefix: 'upload_test_');
    assert(false !== $tempFile);
    file_put_contents(filename: $tempFile, data: 'test content');

    return new UploadedFile(
        path: $tempFile,
        originalName: 'logo.png',
        mimeType: 'image/png',
        test: true,
    );
}
