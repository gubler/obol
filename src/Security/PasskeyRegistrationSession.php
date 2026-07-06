<?php

// ABOUTME: Shared session-bucket key naming the passkey that was just registered.
// ABOUTME: The repository sets it on registration; the list controller reads and clears it to highlight the new row.

declare(strict_types=1);

namespace App\Security;

final class PasskeyRegistrationSession
{
    /**
     * One-shot session key holding the id of the just-registered credential. Set by the repository's
     * saveCredentialRecord on the registration path; read then cleared by the list controller.
     */
    public const string JUST_REGISTERED_KEY = 'passkey.just_registered_id';
}
