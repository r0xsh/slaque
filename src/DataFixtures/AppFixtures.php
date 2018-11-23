<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class AppFixtures extends Fixture
{


    private $encoder;

    public function __construct(UserPasswordEncoderInterface $encoder)
    {
        $this->encoder = $encoder;
    }

    public function load(ObjectManager $manager)
    {
        // $product = new Product();
        // $manager->persist($product);

        $users = [
            'admin', 'antoine', 'valerian', 'ronan', 'louis', 'axel', 'lucas', 'antoine2', 'guillaume'
        ];
        foreach ($users as $usern) {
            $user = new User();
            $user->setUsername($usern);
            $password = $this->encoder->encodePassword($user, $usern);
            $user->setPassword($password);
            $manager->persist($user);
            $manager->flush();
        }
    }
}
