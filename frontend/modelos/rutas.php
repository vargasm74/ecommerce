<?php

class Ruta
{
    public static function ctrRuta(): string
    {
        $url = getenv('APP_URL') ?: 'http://localhost/frontend/';

        return rtrim($url, '/') . '/';
    }

    public static function ctrRutaServidor(): string
    {
        $url = getenv('BACKEND_URL') ?: 'http://localhost/backend/';

        return rtrim($url, '/') . '/';
    }
}
