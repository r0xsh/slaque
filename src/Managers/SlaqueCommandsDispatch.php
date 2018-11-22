<?php
/**
 * Created by PhpStorm.
 * User: r0xsh
 * Date: 22/11/18
 * Time: 15:28
 */

namespace App\Managers;


use App\Entity\Channel;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\PreAuthenticationJWTUserToken;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Ratchet\ConnectionInterface;
use SplObjectStorage;

/**
 * @property \Doctrine\Common\Persistence\ObjectRepository channels
 */

function debug($d) {
    if (true) {
        var_dump($d);
    }
}

class SlaqueCommandsDispatch
{

    /** @var EntityManagerInterface  */
    private $entityManager;

    /** @var JWTTokenManagerInterface  */
    private $JWTManager;

    /** @var \Doctrine\Common\Persistence\ObjectRepository  */
    private $usersManager;

    /** @var \Doctrine\Common\Persistence\ObjectRepository  */
    private $channelsManager;

    /**
     * @var SplObjectStorage
     */
    private $userState;

    /**
     * @var array
     * [channel => [ ...userState ] ]
     */
    private $channelsUsers = [];

    public function __construct(
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $JWTManager
    )
    {
        $this->entityManager = $entityManager;
        $this->JWTManager = $JWTManager;
        $this->userState = new SplObjectStorage();
        $this->usersManager = $entityManager->getRepository(User::class);
        $this->channelsManager = $entityManager->getRepository(Channel::class);
        $this->channelsUsers = array_reduce($this->channelsManager->findAll(), function($acc, $item){
            $acc[$item->getName()] = new SplObjectStorage();
             return $acc;
        }, []);
    }

    /**
     * Auth the user with his JWT Token
     * @param ConnectionInterface $from
     * @param string $token
     */
    public function send_token(ConnectionInterface $from, string $token) {
        $username = $this->JWTManager->decode(new PreAuthenticationJWTUserToken($token))['username'];
        $this->userState->attach($from, ['username' => $username]);
        $from->send(json_encode([
            "action" => "authenticated",
            "message" => $username
        ]));
    }

    /**
     * Sent when the user change to another channel
     * @param ConnectionInterface $from
     * @param string $channel
     */
    public function join_channel(ConnectionInterface $from, string $channel) {
        if (!array_key_exists($channel, $this->channelsUsers)) {
            try {
                $channelEntity = (new Channel())->setName($channel);
                $this->entityManager->persist($channelEntity);
                $this->entityManager->flush();
            } catch (\Exception $_) {}
            $this->channelsUsers[$channel] = new SplObjectStorage();
            debug("Channel créé");
        }
        $data = $this->userState->offsetGet($from);
        if (isset($data['channel']) || !empty($data['channel'])) {
            $this->channelsUsers[$data['channel']]->offsetUnset($from);
        }
        $data['channel'] = $channel;
        $this->userState->offsetSet($from, $data);
        $this->channelsUsers[$channel]->attach($from);
        echo sprintf("<< %s switching to '%s' channel", $data['username'], $data['channel']);
    }

    /**
     * @param ConnectionInterface $from
     * @param string $message
     */
    public function send_message(ConnectionInterface $from, string $message) {
        $data = $this->userState->offsetGet($from);
        if (!isset($data['channel']) || empty($data['channel'])) {
            return;
        }
        $payload = json_encode([
            'action' => 'receive_message',
            'from' => $data['username'],
            'topic' => $data['channel'],
            'message' => $message
        ]);
        foreach ($this->channelsUsers[$data['channel']] as $user) {
            $user->send($payload);
        }

        try {
            $channelEntity = (new Channel())->setName($data['channel']);
            $this->entityManager->persist($channelEntity);
            $this->entityManager->flush();
        } catch (\Exception $_) {}

        echo sprintf("<< %s sent '%s'", $data['username'], $message);
    }

    public function disconnect(ConnectionInterface $from) {
        $data = $this->userState->offsetGet($from);
        $this->userState->offsetUnset($from);
        if (isset($data['channel']) || !empty($data['channel'])) {
            $this->channelsUsers[$data['channel']]->offsetUnset($from);
        }
        echo sprintf(">< Loggout %s", $data['username']);
    }

    private function resendState() {

    }

}