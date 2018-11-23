<?php
/**
 * Created by PhpStorm.
 * User: r0xsh
 * Date: 22/11/18
 * Time: 15:28
 */

namespace App\Managers;


use App\Entity\Channel;
use App\Entity\Message;
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

    /** @var \Doctrine\Common\Persistence\ObjectRepository  */
    private $messagesManager;

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
        $this->messagesManager = $entityManager->getRepository(Message::class);
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
        $this->resendAppState();
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
            $this->resendAppState();
        }
        $data = $this->userState->offsetGet($from);
        if (isset($data['channel']) || !empty($data['channel'])) {
            $this->channelsUsers[$data['channel']]->offsetUnset($from);
        }
        $data['channel'] = $channel;
        $this->userState->offsetSet($from, $data);
        $this->channelsUsers[$channel]->attach($from);

        $currentChan = $this->channelsManager->findOneBy(["name" => $data['channel']]);
        $messages = $this->messagesManager->findBy(
            ["channel" => $currentChan],
            ["id" => "asc"],
            100
        );

        $from->send(json_encode([
            "action" => "channel_joined",
            "channel" => $channel,
            "messages" => $messages
        ]));

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

        $currentChan = $this->channelsManager->findOneBy(["name" => $data['channel']]);
        $currentUser = $this->usersManager->findOneBy(['username' => $data['username']]);


        try {
            $now = new \DateTime();
            $messageEntity = (new Message())->setChannel($currentChan)->setUser($currentUser)->setMessage($message)->
            setCreatedAt($now)->setUpdatedAt($now);
            $this->entityManager->persist($messageEntity);
            $this->entityManager->flush();
        } catch (\Exception $_) {}

        $payload = json_encode([
            'action' => 'receive_message',
            'message' => $messageEntity
        ]);
        foreach ($this->channelsUsers[$data['channel']] as $user) {
            $user->send($payload);
        }


        echo sprintf("<< %s sent '%s'", $data['username'], $message);
    }

    public function disconnect(ConnectionInterface $from) {
        $data = $this->userState->offsetGet($from);
        $this->userState->offsetUnset($from);
        if (isset($data['channel']) || !empty($data['channel'])) {
            $this->channelsUsers[$data['channel']]->offsetUnset($from);
        }
        $this->resendAppState();
        echo sprintf(">< Loggout %s", $data['username']);
    }

    private function resendAppState() {

        $allUsers = $this->usersManager->findAll();
        $allChannels = array_filter($this->channelsManager->findAll(), function($chan) {
            return substr($chan->getName(), 0, 3) !== 'pm:';
        });
        $userLogged = [];

        $this->userState->rewind();
        while($this->userState->valid()) {
            $object = $this->userState->getInfo();
            $userLogged[] = $object['username'];
            $this->userState->next();
        }

        $state = array_reduce($allUsers, function ($acc, $user) use (&$userLogged) {
            $acc[] = ['username'=> $user->getUsername(), 'online' => in_array($user->getUsername(), $userLogged)];
            return $acc;
        }, []);

        $return = json_encode([
            'action' => 'receive_app_state',
            'channels' => $allChannels,
            'users' => $state
        ]);

        $this->userState->rewind();
        while($this->userState->valid()) {
            /** @var ConnectionInterface $object */
            $object = $this->userState->current();
            $object->send($return);
            $this->userState->next();
        }

    }

}