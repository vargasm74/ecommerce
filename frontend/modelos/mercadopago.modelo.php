<?php

class MercadoPago
{
    private static function enabled(): bool
    {
        return filter_var(
            getenv("MERCADOPAGO_ENABLED") ?: "false",
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function crearOrder(array $payload, string $idempotencyKey): array
    {
        if(!self::enabled()){
            throw new RuntimeException("Mercado Pago no esta habilitado");
        }

        $accessToken = trim((string) getenv("MERCADOPAGO_ACCESS_TOKEN"));

        if($accessToken === ""){
            throw new RuntimeException("Falta MERCADOPAGO_ACCESS_TOKEN");
        }

        if($idempotencyKey === "" || strlen($idempotencyKey) > 128){
            throw new InvalidArgumentException("Idempotency key invalida");
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $ch = curl_init("https://api.mercadopago.com/v1/orders");

        curl_setopt_array($ch, array(
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => array(
                "Accept: application/json",
                "Content-Type: application/json",
                "Authorization: Bearer ".$accessToken,
                "X-Idempotency-Key: ".$idempotencyKey
            ),
            CURLOPT_POSTFIELDS => $json
        ));

        $body = curl_exec($ch);

        if($body === false){
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("Error de conexion con Mercado Pago: ".$error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $respuesta = json_decode($body, true);

        if(!is_array($respuesta)){
            throw new RuntimeException("Respuesta invalida de Mercado Pago");
        }

        if($status < 200 || $status >= 300){
            $detalle = $respuesta["message"]
                ?? $respuesta["error"]
                ?? ("HTTP ".$status);

            throw new RuntimeException("Mercado Pago rechazo la order: ".$detalle);
        }

        if(empty($respuesta["id"]) || empty($respuesta["checkout_url"])){
            throw new RuntimeException("Mercado Pago no devolvio id/checkout_url");
        }

        return $respuesta;
    }
}
