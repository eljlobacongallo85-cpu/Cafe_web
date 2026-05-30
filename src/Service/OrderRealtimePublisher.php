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
        @chmod($dir, 0775);

        $this->eventsFile = $dir . '/realtime-events.ndjson';
        if (!file_exists($this->eventsFile)) {
            @touch($this->eventsFile);
        }
        @chmod($this->eventsFile, 0664);
    }

    public function publishOrderUpdated(array $orderPayload): void
    {
        $this->publish('order.updated', ['order' => $orderPayload]);
    }

    public function publishProductUpdated(array $productPayload): void
    {
        $this->publish('product.updated', ['product' => $productPayload]);
    }

    public function publishProductDeleted(int|string $id, ?string $name = null): void
    {
        $this->publish('product.deleted', [
            'product' => [
                'id' => $id,
                'name' => $name,
            ],
        ]);
    }

    public function publishActivityLogged(array $logPayload): void
    {
        $this->publish('activity.logged', ['activity' => $logPayload]);
    }

    private function publish(string $type, array $payload): void
    {
        $event = [
            'type' => $type,
            'ts' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ...$payload,
        ];

        $encoded = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return;
        }

        @file_put_contents(
            $this->eventsFile,
            $encoded . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

    public function getEventsFile(): string
    {
        return $this->eventsFile;
    }
}

