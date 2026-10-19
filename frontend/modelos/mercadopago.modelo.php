<?php

$mercadoPagoAutoload = dirname(__DIR__, 2)."/vendor/autoload.php";

if(!is_file($mercadoPagoAutoload)){
    throw new RuntimeException(
        "No se encontro vendor/autoload.php. Reconstruya la imagen para instalar la SDK de Mercado Pago."
    );
}

require_once $mercadoPagoAutoload;

use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Order\OrderClient;
use MercadoPago\Exceptions\InvalidWebhookSignatureException;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use MercadoPago\Webhook\WebhookSignatureValidator;

class MercadoPago
{
    private static function enabled(): bool
    {
        return filter_var(
            getenv("MERCADOPAGO_ENABLED") ?: "false",
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private static function configurarSdk(): void
    {
        if(!self::enabled()){
            throw new RuntimeException("Mercado Pago no esta habilitado");
        }

        $accessToken = trim((string) getenv("MERCADOPAGO_ACCESS_TOKEN"));

        if($accessToken === ""){
            throw new RuntimeException("Falta MERCADOPAGO_ACCESS_TOKEN");
        }

        MercadoPagoConfig::setAccessToken($accessToken);

        // Incluso en desarrollo usamos TLS verificado contra la API real de TEST.
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::SERVER);
    }

    private static function mensajeApiException(MPApiException $e): string
    {
        $apiResponse = $e->getApiResponse();

        if(!$apiResponse){
            return "Error de API de Mercado Pago";
        }

        $status = (int) $apiResponse->getStatusCode();
        $content = $apiResponse->getContent();
        $detalle = null;

        if(is_array($content)){
            $detalle = $content["message"]
                ?? $content["error"]
                ?? null;

            if($detalle === null && isset($content["cause"]) && is_array($content["cause"])){
                $primeraCausa = $content["cause"][0] ?? null;

                if(is_array($primeraCausa)){
                    $detalle = $primeraCausa["description"]
                        ?? $primeraCausa["code"]
                        ?? null;
                }
            }
        }

        return "HTTP ".$status.($detalle ? " - ".$detalle : "");
    }

    public static function validarFirmaWebhook(
        ?string $xSignature,
        ?string $xRequestId,
        ?string $dataId
    ): void {
        $secret = trim((string) getenv("MERCADOPAGO_WEBHOOK_SECRET"));

        if($secret === ""){
            throw new RuntimeException("Falta MERCADOPAGO_WEBHOOK_SECRET");
        }

        try{
            WebhookSignatureValidator::validate(
                $xSignature,
                $xRequestId,
                $dataId,
                $secret
            );
        }catch(InvalidWebhookSignatureException $e){
            throw $e;
        }
    }

    public static function consultarOrder(string $providerOrderId): array
    {
        self::configurarSdk();

        $providerOrderId = trim($providerOrderId);

        if($providerOrderId === ""){
            throw new InvalidArgumentException("Order id de Mercado Pago invalido");
        }

        $client = new OrderClient();

        try{

            $order = $client->get($providerOrderId);

        }catch(MPApiException $e){

            throw new RuntimeException(
                "No se pudo consultar la order: ".self::mensajeApiException($e),
                0,
                $e
            );

        }catch(Throwable $e){

            throw new RuntimeException(
                "No se pudo consultar la order: ".$e->getMessage(),
                0,
                $e
            );
        }

        return array(
            "id" => isset($order->id) ? (string) $order->id : "",
            "external_reference" => isset($order->external_reference) ? (string) $order->external_reference : "",
            "status" => isset($order->status) ? (string) $order->status : "",
            "status_detail" => isset($order->status_detail) ? (string) $order->status_detail : "",
            "currency" => isset($order->currency) ? (string) $order->currency : "",
            "total_amount" => isset($order->total_amount) ? (string) $order->total_amount : "",
            "total_paid_amount" => isset($order->total_paid_amount) ? (string) $order->total_paid_amount : ""
        );
    }

    public static function crearOrder(array $payload, string $idempotencyKey): array
    {
        self::configurarSdk();

        if($idempotencyKey === "" || strlen($idempotencyKey) > 128){
            throw new InvalidArgumentException("Idempotency key invalida");
        }

        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders(array(
            "X-Idempotency-Key: ".$idempotencyKey
        ));

        $client = new OrderClient();

        try{

            $order = $client->create($payload, $requestOptions);

        }catch(MPApiException $e){

            throw new RuntimeException(
                "Mercado Pago rechazo la order: ".self::mensajeApiException($e),
                0,
                $e
            );

        }catch(Throwable $e){

            throw new RuntimeException(
                "No se pudo comunicar con Mercado Pago: ".$e->getMessage(),
                0,
                $e
            );
        }

        $orderId = isset($order->id) ? trim((string) $order->id) : "";
        $checkoutUrl = isset($order->checkout_url)
            ? trim((string) $order->checkout_url)
            : "";

        if($orderId === "" || $checkoutUrl === ""){
            throw new RuntimeException(
                "Mercado Pago no devolvio id/checkout_url"
            );
        }

        return array(
            "id" => $orderId,
            "checkout_url" => $checkoutUrl,
            "status" => isset($order->status) ? (string) $order->status : ""
        );
    }
}