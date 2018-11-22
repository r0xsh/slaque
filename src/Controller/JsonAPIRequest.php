<?php
/**
 * Created by PhpStorm.
 * User: r0xsh
 * Date: 22/11/18
 * Time: 12:04
 */

namespace App\Controller;


use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait JsonAPIRequest
{

    public function jsonDecode(Request $request): Request {
        if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
            $data = json_decode($request->getContent(), true);
            $request->request->replace(is_array($data) ? $data : array());
        }
        return $request;
    }

    public function jsonResponse(array $data): JsonResponse {
        return new JsonResponse($data);
    }

}