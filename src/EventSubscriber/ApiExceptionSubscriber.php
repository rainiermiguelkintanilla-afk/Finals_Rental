<?php

namespace App\EventSubscriber;

use App\Service\ApiResponseFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof AccessDeniedException) {
            $event->setResponse(ApiResponseFactory::error(
                'You do not have permission to access this resource.',
                'access_denied',
                Response::HTTP_FORBIDDEN,
            ));

            return;
        }

        if ($throwable instanceof AuthenticationException) {
            $event->setResponse(ApiResponseFactory::error(
                'Authentication required. Provide a valid JWT Bearer token.',
                'authentication_required',
                Response::HTTP_UNAUTHORIZED,
            ));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $status = $throwable->getStatusCode();
            $error = match ($status) {
                Response::HTTP_NOT_FOUND => 'not_found',
                Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
                default => 'http_error',
            };

            $event->setResponse(ApiResponseFactory::error(
                $throwable->getMessage() ?: 'Request failed.',
                $error,
                $status,
            ));

            return;
        }

        $message = 'An unexpected error occurred.';
        if ($event->getKernel()->isDebug()) {
            $message = $throwable->getMessage() ?: $message;
        }

        $event->setResponse(ApiResponseFactory::error(
            $message,
            'server_error',
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ));
    }
}
