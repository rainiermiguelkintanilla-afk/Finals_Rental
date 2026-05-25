<?php

namespace App\Controller;

use App\Service\ApiResponseFactory;
use App\Service\RealtimeEventBroadcaster;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/customer/realtime')]
#[IsGranted('ROLE_CUSTOMER')]
final class CustomerRealtimeController extends AbstractController
{
    #[Route('/events', name: 'api_customer_realtime_events', methods: ['GET'])]
    public function events(Request $request, RealtimeEventBroadcaster $broadcaster): Response
    {
        $since = max(0, (int) $request->query->get('since', 0));
        $events = $broadcaster->getSince($since);

        return ApiResponseFactory::success([
            'events' => $events,
            'since' => $since,
            'serverTime' => time(),
        ], 'Realtime events loaded.');
    }

    /** Server-Sent Events stream (mobile / web clients). */
    #[Route('/stream', name: 'api_customer_realtime_stream', methods: ['GET'])]
    public function eventStream(Request $request, RealtimeEventBroadcaster $broadcaster): StreamedResponse
    {
        $since = max(0, (int) $request->query->get('since', 0));

        $response = new StreamedResponse(function () use ($broadcaster, $since): void {
            $lastId = $since;
            $iterations = 0;
            while (!connection_aborted() && $iterations < 120) {
                $events = $broadcaster->getSince($lastId);
                foreach ($events as $event) {
                    echo 'data: '.json_encode($event, JSON_THROW_ON_ERROR)."\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                    $lastId = max($lastId, (int) $event['id']);
                }
                if ($events === []) {
                    echo ": keepalive\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
                sleep(2);
                ++$iterations;
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }
}
