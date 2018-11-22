<?php
/**
 * Created by PhpStorm.
 * User: r0xsh
 * Date: 22/11/18
 * Time: 13:37
 */

namespace App\WebSocket;


use App\Entity\Channel;
use App\Entity\User;
use App\Managers\SlaqueCommandsDispatch;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class SlaqueSocket implements MessageComponentInterface
{

    private $slaqueManager;

    public function __construct(
        SlaqueCommandsDispatch $slaqueManager
    )
    {
        $this->slaqueManager = $slaqueManager;
    }


    /**
     * When a new connection is opened it will be passed to this method
     * @param  ConnectionInterface $conn The socket/connection that just connected to your application
     * @throws \Exception
     */
    function onOpen(ConnectionInterface $conn)
    {
        $conn->send(json_encode([
            "action" => "ask_token"
        ]));
    }

    /**
     * This is called before or after a socket is closed (depends on how it's closed).  SendMessage to $conn will not result in an error if it has already been closed.
     * @param  ConnectionInterface $conn The socket/connection that is closing/closed
     * @throws \Exception
     */
    function onClose(ConnectionInterface $conn)
    {
        $this->slaqueManager->disconnect($conn);
    }

    /**
     * If there is an error with one of the sockets, or somewhere in the application where an Exception is thrown,
     * the Exception is sent back down the stack, handled by the Server and bubbled back up the application through this method
     * @param  ConnectionInterface $conn
     * @param  \Exception $e
     * @throws \Exception
     */
    function onError(ConnectionInterface $conn, \Exception $e)
    {
        // TODO: Implement onError() method.
    }

    /**
     * Triggered when a client sends data through the socket
     * @param  \Ratchet\ConnectionInterface $from The socket/connection that sent the message to your application
     * @param  string $msg The message received
     * @throws \Exception
     */
    function onMessage(ConnectionInterface $from, $msg)
    {
        $msg = json_decode($msg, true);
        if (!isset($msg['action']) || empty($msg['action'])) {
            return;
        }

        echo sprintf("<< %s %s\n", $msg['action'], $msg['message']);

        $this->slaqueManager->{$msg['action']}($from, $msg['message']);
    }
}