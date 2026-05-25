<?php

namespace App\Controller;

use App\Service\PayMongoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PaymongoWebhookController extends AbstractController
{
    #[Route('/api/webhooks/paymongo', name: 'api_webhooks_paymongo', methods: ['POST'])]
    public function __invoke(Request $request, PayMongoService $payMongo): Response
    {
        if (!$payMongo->isEnabled()) {
            return new Response('PayMongo not configured', Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $rawBody = $request->getContent();

        try {
            $payMongo->handleWebhookPayload(
                $rawBody,
                $request->headers->get('Paymongo-Signature'),
            );
        } catch (\RuntimeException $exception) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return new Response('ok', Response::HTTP_OK);
    }
}
