<?php

namespace App\Command;

use App\Service\OrderRealtimePublisher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Workerman\Connection\TcpConnection;
use Workerman\Timer;
use Workerman\Worker;

#[AsCommand(
    name: 'app:websocket:orders',
    description: 'Start WebSocket server for real-time order updates',
)]
class OrdersWebSocketServerCommand extends Command
{
    public function __construct(private readonly OrderRealtimePublisher $publisher)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Bind host', '0.0.0.0')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Bind port', '8081');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $host = (string) $input->getOption('host');
        $port = (string) $input->getOption('port');
        $eventsFile = $this->publisher->getEventsFile();

        /** @var array<int, TcpConnection> $clients */
        $clients = [];
        $offset = 0;
        if (file_exists($eventsFile)) {
            $size = filesize($eventsFile);
            $offset = $size !== false ? $size : 0;
        }

        $worker = new Worker("websocket://{$host}:{$port}");
        $worker->name = 'orders-ws';
        $worker->count = 1;

        $worker->onConnect = static function (TcpConnection $connection) use (&$clients): void {
            $clients[$connection->id] = $connection;
            $connection->send(json_encode([
                'type' => 'connection.ready',
                'ts' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ]));
        };

        $worker->onClose = static function (TcpConnection $connection) use (&$clients): void {
            unset($clients[$connection->id]);
        };

        $worker->onMessage = static function (TcpConnection $connection, string $message): void {
            // Server push only. Accept incoming ping/any payload quietly.
            if ($message === 'ping') {
                $connection->send('pong');
            }
        };

        $worker->onWorkerStart = static function () use (&$clients, $eventsFile, &$offset): void {
            Timer::add(0.5, static function () use (&$clients, $eventsFile, &$offset): void {
                if (!file_exists($eventsFile)) {
                    return;
                }

                clearstatcache(true, $eventsFile);
                $size = filesize($eventsFile);
                if ($size === false) {
                    return;
                }

                if ($size < $offset) {
                    $offset = 0;
                }

                if ($size <= $offset) {
                    return;
                }

                $fp = fopen($eventsFile, 'rb');
                if (!$fp) {
                    return;
                }

                fseek($fp, $offset);
                while (($line = fgets($fp)) !== false) {
                    $payload = trim($line);
                    if ($payload === '') {
                        continue;
                    }

                    foreach ($clients as $client) {
                        $client->send($payload);
                    }
                }

                $offset = ftell($fp) ?: $offset;
                fclose($fp);
            });
        };

        $io->success(sprintf('Orders WebSocket server listening on ws://%s:%s/ws/orders', $host, $port));
        $io->writeln('Make sure your reverse proxy forwards /ws/orders to this port.');

        // Workerman requires an argv command (start/stop/restart/status).
        // In Railway we want this process to run in foreground continuously.
        if (!isset($GLOBALS['argv']) || !is_array($GLOBALS['argv'])) {
            $GLOBALS['argv'] = [];
        }
        $GLOBALS['argv'][0] = $GLOBALS['argv'][0] ?? 'workerman';
        $GLOBALS['argv'][1] = 'start';
        $GLOBALS['argc'] = 2;

        Worker::runAll();

        return Command::SUCCESS;
    }
}

