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
}
