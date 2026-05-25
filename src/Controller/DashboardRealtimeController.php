<?php

namespace App\Controller;

use App\Service\RealtimeEventBroadcaster;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Staff dashboard poll — session auth (web app). */
#[Route('/dashboard/realtime')]
#[IsGranted('ROLE_STAFF')]
final class DashboardRealtimeController extends AbstractController
{
    #[Route('/events', name: 'dashboard_realtime_events', methods: ['GET'])]
    public function events(Request $request, RealtimeEventBroadcaster $broadcaster): Response
    {
        $since = max(0, (int) $request->query->get('since', 0));
        $events = $broadcaster->getSince($since);

        return new JsonResponse([
            'events' => $events,
            'since' => $since,
            'serverTime' => time(),
        ]);
    }
}
