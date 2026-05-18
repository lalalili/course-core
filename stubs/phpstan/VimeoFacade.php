<?php

namespace Vimeo\Laravel\Facades;

class Vimeo
{
    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    public static function request(string $uri, array $body = [], string $method = 'GET', array $headers = []): array
    {
        return [];
    }
}
