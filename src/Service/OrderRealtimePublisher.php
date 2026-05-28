<?php

namespace App\Service;

use Symfony\Component\HttpKernel\KernelInterface;

class OrderRealtimePublisher
{
    private string $eventsFile;

    public function __construct(KernelInterface $kernel)
    {
        $projectDir = $kernel->getProjectDir();
        $dir = $projectDir . '/var/realtime';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $this->eventsFile = $dir . '/orders-events.ndjson';
        if (!file_exists($this->eventsFile)) {
            @touch($this->eventsFile);
        }
    }

    public function publishOrderUpdated(array $orderPayload): void
    {
        $event = [
            'type' => 'order.updated',
            'ts' => (new \DateTimeImmutable())->format(DATE_ATOM),
            'order' => $orderPayload,
        ];

        @file_put_contents(
            $this->eventsFile,
            json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function getEventsFile(): string
    {
        return $this->eventsFile;
    }
}

