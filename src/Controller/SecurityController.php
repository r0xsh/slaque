<?php

namespace App\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class SecurityController extends AbstractController
{
    use JsonAPIRequest;
    /**
     * @Route("/api/login", name="login")
     */
    public function login(Request $request)
    {
        $request = $this->jsonDecode($request);

        $user = $this->getUser();

        return $this->json(array(
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ));
    }

    /**
     * @Route("/register", name="register")
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $encoder
     * @param JWTTokenManagerInterface $JWTManager
     * @return Response
     */
    public function register(Request $request, UserPasswordEncoderInterface $encoder, JWTTokenManagerInterface $JWTManager)
    {
        $em = $this->getDoctrine()->getManager();

        $request = $this->jsonDecode($request);

        $username = $request->request->get('username');
        $password = $request->request->get('password');

        $user = new User();
        $user->setUsername($username)->setPassword($encoder->encodePassword($user, $password));
        $em->persist($user);
        $em->flush();
        return $this->jsonResponse([
            'token' => $JWTManager->create($user),
            'user' => $user
        ]);
    }

    /**
     * @Route("/api", name="api")
     *
     * @return Response
     */
    public function api()
    {
        return new Response(sprintf('Logged in as %s', $this->getUser()->getUsername()));
    }
}
