<?php

// ABOUTME: Handles file uploads by moving uploaded files to a target directory.
// ABOUTME: Returns the relative public path for storage in entity fields.

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Ulid;

final readonly class FileUploader
{
    public function __construct(
        #[Autowire(param: 'app.uploads_directory')]
        private string $targetDirectory,
        #[Autowire(param: 'app.uploads_public_path')]
        private string $publicPath,
    ) {
    }

    public function upload(UploadedFile $file): string
    {
        $filename = (new Ulid()) . '.' . $file->guessExtension();

        $file->move(directory: $this->targetDirectory, name: $filename);

        return $this->publicPath . '/' . $filename;
    }
}
