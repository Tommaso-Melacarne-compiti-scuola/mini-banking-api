<?php

class ResponseUtils
{
    public static function json($response, $data, int $status = 200)
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public static function error($response, string $message, int $status = 500)
    {
        return self::json($response, ['error' => $message], $status);
    }

    public static function notFound($response, string $message = 'Not Found')
    {
        return self::error($response, $message, 404);
    }

    public static function badRequest($response, string $message = 'Bad Request')
    {
        return self::error($response, $message, 400);
    }

    public static function internalServerError($response, string $message = 'Internal Server Error')
    {
        return self::error($response, $message, 500);
    }

    public static function created($response, $data)
    {
        return self::json($response, $data, 201);
    }

    public static function noContent($response)
    {
        return $response->withStatus(204);
    }
}
