<?php

// ABOUTME: Renders the transactional email templates and asserts they share the branded, inlined-CSS base.
// ABOUTME: Guards that copy stays translatable and the CTA link survives the CSS-inlining pass.

declare(strict_types=1);

namespace App\Tests\Feature\Email;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class TransactionalEmailBrandingTest extends KernelTestCase
{
    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): string
    {
        $twig = self::getContainer()->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig->render($template, $context);
    }

    private function loginLinkHtml(): string
    {
        return $this->render('email/login_link.html.twig', [
            'loginLinkUrl' => 'https://obol.example/login/verify?token=abc123',
            'expiresAt' => new \DateTimeImmutable('+1 hour'),
        ]);
    }

    private function verifyHtml(): string
    {
        return $this->render('email/secondary_email_verify.html.twig', [
            'verifyUrl' => 'https://obol.example/account/verify?token=def456',
            'expiresAt' => new \DateTimeImmutable('+1 hour'),
        ]);
    }

    public function testMagicLinkEmailInlinesTheBrandStyling(): void
    {
        $html = $this->loginLinkHtml();

        // The CSS inliner must have run: styled elements carry inline style attributes, not a bare <style> block.
        self::assertStringContainsString('style="', $html);
        // The Obol brand gold is applied (proof the branded base stylesheet was inlined, not just any style).
        self::assertStringContainsString('#9c7320', $html);
    }

    public function testMagicLinkEmailKeepsTranslatableCopyAndTheCtaLink(): void
    {
        $html = $this->loginLinkHtml();

        // Copy still resolves through the catalog (ADR-0012) and the actionable link survives inlining.
        self::assertStringContainsString('Sign in to Obol', $html);
        self::assertStringContainsString('https://obol.example/login/verify?token=abc123', $html);
    }

    public function testBothTransactionalEmailsShareTheBrandedBaseLayout(): void
    {
        $login = $this->loginLinkHtml();
        $verify = $this->verifyHtml();

        // The shared footer note only lives in the base layout, so its presence in both proves they extend it.
        $footer = self::getContainer()->get('translator')->trans('email.layout.automated_note');
        self::assertStringContainsString($footer, $login);
        self::assertStringContainsString($footer, $verify);

        // And the branded shell (brand gold) reaches both.
        self::assertStringContainsString('#9c7320', $verify);
    }
}
