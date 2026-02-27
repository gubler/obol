<?php

// ABOUTME: Handles file uploads by moving uploaded files to a target directory.
// ABOUTME: Returns the relative public path for storage in entity fields.

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final class FileUploader
{
    public function __construct(
        private readonly string $targetDirectory,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $filename = (new Ulid()) . '.' . $file->guessExtension();

        $file->move(directory: $this->targetDirectory, name: $filename);

        return 'uploads/logos/' . $filename;
    }
}
