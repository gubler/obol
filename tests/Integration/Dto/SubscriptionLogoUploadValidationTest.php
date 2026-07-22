<?php

// ABOUTME: Integration test that the subscription logo field rejects dangerous and oversize uploads.
// ABOUTME: An SVG/HTML upload (active-script XSS on our origin) and an oversize file must not validate.

declare(strict_types=1);

namespace App\Tests\Integration\Dto;

use App\Dto\Subscription\CreateSubscriptionDto;
use App\Dto\Subscription\UpdateSubscriptionDto;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SubscriptionLogoUploadValidationTest extends KernelTestCase
{
    /**
     * @var list<string>
     */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file(filename: $path)) {
                unlink(filename: $path);
            }
        }

        parent::tearDown();
    }

    #[DataProvider('dtoClassProvider')]
    public function testRejectsAnSvgLogoBecauseItCanCarryActiveScript(string $dtoClass): void
    {
        $svg = $this->uploadedFile(
            content: '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            originalName: 'logo.svg',
            clientMimeType: 'image/svg+xml',
        );

        self::assertGreaterThan(0, \count($this->validateLogo($dtoClass, $svg)), 'an SVG logo must be rejected');
    }

    #[DataProvider('dtoClassProvider')]
    public function testRejectsAnHtmlFileMasqueradingAsALogo(string $dtoClass): void
    {
        $html = $this->uploadedFile(
            content: '<!doctype html><html><body><script>alert(1)</script></body></html>',
            originalName: 'logo.html',
            clientMimeType: 'text/html',
        );

        self::assertGreaterThan(0, \count($this->validateLogo($dtoClass, $html)), 'an HTML upload must be rejected');
    }

    #[DataProvider('dtoClassProvider')]
    public function testRejectsAnOversizeLogo(string $dtoClass): void
    {
        $oversize = $this->uploadedFile(
            content: str_repeat('a', 3 * 1024 * 1024),
            originalName: 'huge.png',
            clientMimeType: 'image/png',
        );

        self::assertGreaterThan(0, \count($this->validateLogo($dtoClass, $oversize)), 'an oversize logo must be rejected');
    }

    #[DataProvider('dtoClassProvider')]
    public function testAcceptsASmallPngLogo(string $dtoClass): void
    {
        // A 1x1 transparent PNG - real magic bytes so the content sniffs to image/png.
        $png = $this->uploadedFile(
            content: (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', strict: true),
            originalName: 'logo.png',
            clientMimeType: 'image/png',
        );

        self::assertCount(0, $this->validateLogo($dtoClass, $png), 'a small PNG logo must be accepted');
    }

    /**
     * Both subscription DTOs carry the same logo constraint, so both must reject the same uploads.
     *
     * @return iterable<string, array{class-string}>
     */
    public static function dtoClassProvider(): iterable
    {
        yield 'create' => [CreateSubscriptionDto::class];
        yield 'edit' => [UpdateSubscriptionDto::class];
    }

    /**
     * @param class-string $dtoClass
     */
    private function validateLogo(string $dtoClass, UploadedFile $file): \Symfony\Component\Validator\ConstraintViolationListInterface
    {
        self::bootKernel();
        $validator = self::getContainer()->get(id: ValidatorInterface::class);
        self::assertInstanceOf(ValidatorInterface::class, $validator);

        return $validator->validatePropertyValue($dtoClass, 'logo', $file);
    }

    private function uploadedFile(string $content, string $originalName, string $clientMimeType): UploadedFile
    {
        $tempFile = tempnam(directory: sys_get_temp_dir(), prefix: 'logo_upload_test_');
        \assert(false !== $tempFile);
        file_put_contents(filename: $tempFile, data: $content);
        $this->tempFiles[] = $tempFile;

        return new UploadedFile(path: $tempFile, originalName: $originalName, mimeType: $clientMimeType, test: true);
    }
}
