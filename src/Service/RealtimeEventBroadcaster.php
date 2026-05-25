<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Append-only event log in Symfony cache — shared by mobile API poll, SSE, and dashboard.
 */
final class RealtimeEventBroadcaster
{
    private const CACHE_KEY = 'rainier.realtime.events';
    private const MAX_EVENTS = 250;

    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $type, array $payload = []): void
    {
        $events = $this->readEvents();
        $events[] = [
            'id' => (int) round(microtime(true) * 1000),
            'type' => $type,
            'payload' => $payload,
            'timestamp' => time(),
        ];
        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }
        $this->writeEvents($events);
    }

    /**
     * @return list<array{id:int,type:string,payload:array,timestamp:int}>
     */
    public function getSince(int $sinceId): array
    {
        $events = $this->readEvents();

        return array_values(array_filter(
            $events,
            static fn (array $event): bool => ($event['id'] ?? 0) > $sinceId,
        ));
    }

    /**
     * @return list<array{id:int,type:string,payload:array,timestamp:int}>
     */
    private function readEvents(): array
    {
        /** @var list<array{id:int,type:string,payload:array,timestamp:int}> $events */
        $events = $this->cache->get(self::CACHE_KEY, static function (ItemInterface $item) {
            $item->expiresAfter(86400);

            return [];
        });

        return is_array($events) ? $events : [];
    }

    /**
     * @param list<array{id:int,type:string,payload:array,timestamp:int}> $events
     */
    private function writeEvents(array $events): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->get(self::CACHE_KEY, static function (ItemInterface $item) use ($events) {
            $item->expiresAfter(86400);

            return $events;
        });
    }
}
