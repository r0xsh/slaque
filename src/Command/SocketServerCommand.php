<?php

namespace App\Command;

use App\WebSocket\SlaqueSocket;
use Doctrine\ORM\EntityManager;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SocketServerCommand extends Command
{
    protected static $defaultName = 'app:socket-server';

    private $slaqueSocket;

    /**
     * SocketServerCommand constructor.
     * @param SlaqueSocket $slaqueSocket
     */
    public function __construct(SlaqueSocket $slaqueSocket)
    {
        parent::__construct();
        $this->slaqueSocket = $slaqueSocket;
    }

    protected function configure()
    {
        $this
            ->setDescription('Run the ratchet websocket server')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        (IoServer::factory(
            new HttpServer(
                new WsServer($this->slaqueSocket)),
            47187
        ))->run();
    }
}
