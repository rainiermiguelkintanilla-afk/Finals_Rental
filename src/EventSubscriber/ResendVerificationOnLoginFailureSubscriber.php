<?php

namespace App\EventSubscriber;

use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/**
 * When form login fails because the account is not verified, queue a fresh verification email (Mailer / Mailtrap).
 */
final class ResendVerificationOnLoginFailureSubscriber implements EventSubscriberInterface
{
    private const RESEND_COOLDOWN_SECONDS = 90;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailVerificationService $emailVerificationService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if ($event->getFirewallName() !== 'main') {
            return;
        }

        if (!$this->isUnverifiedAccountFailure($event->getException())) {
            return;
        }

        $request = $event->getRequest();
        $email = $this->getSubmittedEmail($request);
        if ($email === '') {
            return;
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user || $user->isVerified()) {
            return;
        }

        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        if (!$session instanceof Session) {
            return;
        }

        $cooldownKey = 'verification_resend_'.$email;
        $last = (int) $session->get($cooldownKey, 0);
        if (time() - $last < self::RESEND_COOLDOWN_SECONDS) {
            return;
        }

        $this->emailVerificationService->queueFreshVerificationEmail($user);
        $session->set($cooldownKey, time());
        $session->getFlashBag()->add(
            'info',
            'A new verification link has been sent to your email. Check your inbox (and spam), then try signing in again.'
        );
    }

    private function isUnverifiedAccountFailure(\Throwable $e): bool
    {
        while ($e !== null) {
            if ($e instanceof CustomUserMessageAccountStatusException) {
                $key = strtolower($e->getMessageKey());

                return str_contains($key, 'verify');
            }
            $e = $e->getPrevious();
        }

        return false;
    }

    private function getSubmittedEmail(Request $request): string
    {
        return trim((string) $request->request->get('_username', ''));
    }
}
