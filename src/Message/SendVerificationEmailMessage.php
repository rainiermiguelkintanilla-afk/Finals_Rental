<?php

namespace App\Message;

/**
 * Dispatched after registration; handled asynchronously so the HTTP response is not
 * blocked on Brevo/network.
 */
final class SendVerificationEmailMessage
{
    public function __construct(
        public readonly int $emailVerificationTokenId
    ) {
    }
}
