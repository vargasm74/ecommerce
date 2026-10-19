<?php

declare(strict_types=1);

require_once __DIR__."/modelos/conexion.php";
require_once __DIR__."/modelos/carrito.modelo.php";
require_once __DIR__."/modelos/mercadopago.modelo.php";
require_once __DIR__."/controladores/carrito.controlador.php";

use MercadoPago\Exceptions\InvalidWebhookSignatureException;

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

if(($_SERVER["REQUEST_METHOD"] ?? "") !== "POST"){
    http_response_code(405);
    header("Allow: POST");
    echo json_encode(array("error" => "method_not_allowed"));
    exit;
}

$rawBody = file_get_contents("php://input");
$body = json_decode($rawBody ?: "{}", true);

if(!is_array($body)){
    $body = array();
}

$type = trim((string) ($_GET["type"] ?? ($body["type"] ?? "")));

if($type !== "" && $type !== "order"){
    http_response_code(200);
    echo json_encode(array("ok" => true, "ignored" => true));
    exit;
}

/*
 * PHP convierte los puntos de los nombres de query params a guion bajo,
 * por eso ?data.id=... normalmente queda disponible como $_GET["data_id"].
 * También aceptamos el valor del body como respaldo.
 */
$dataId = trim((string) (
    $_GET["data_id"]
    ?? $_GET["data.id"]
    ?? ($body["data"]["id"] ?? "")
));

$xSignature = isset($_SERVER["HTTP_X_SIGNATURE"])
    ? trim((string) $_SERVER["HTTP_X_SIGNATURE"])
    : null;

$xRequestId = isset($_SERVER["HTTP_X_REQUEST_ID"])
    ? trim((string) $_SERVER["HTTP_X_REQUEST_ID"])
    : null;

if($dataId === ""){
    http_response_code(400);
    echo json_encode(array("error" => "missing_order_id"));
    exit;
}

try{

    MercadoPago::validarFirmaWebhook(
        $xSignature,
        $xRequestId,
        $dataId
    );

}catch(InvalidWebhookSignatureException $e){

    error_log(
        "Webhook Mercado Pago firma invalida request_id="
        .($xRequestId ?: "sin-request-id")
    );

    http_response_code(401);
    echo json_encode(array("error" => "invalid_signature"));
    exit;

}catch(Throwable $e){

    error_log(
        "Webhook Mercado Pago no pudo validar firma: ".$e->getMessage()
    );

    http_response_code(503);
    echo json_encode(array("error" => "webhook_not_configured"));
    exit;
}

try{

    $resultado = ControladorCarrito::ctrProcesarWebhookMercadoPago(
        $dataId,
        $xRequestId
    );

    if(($resultado["resultado"] ?? "") === "mismatch"){
        error_log(
            "Webhook Mercado Pago PAYMENT_MISMATCH order_id="
            .($resultado["order_id"] ?? "desconocido")
            ." provider_order_id=".$dataId
        );
    }

    http_response_code(200);

    $respuesta = array(
        "ok" => true,
        "resultado" => $resultado["resultado"] ?? "ok"
    );

    if((getenv("APP_ENV") ?: "production") === "development"){
        $respuesta["detalle"] = $resultado;
    }

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

}catch(Throwable $e){

    error_log(
        "Webhook Mercado Pago error procesando order "
        .$dataId.": ".$e->getMessage()
    );

    http_response_code(500);

    $respuesta = array(
        "error" => "processing_error"
    );

    if((getenv("APP_ENV") ?: "production") === "development"){
        $respuesta["detalle"] = $e->getMessage();
    }

    echo json_encode(
        $respuesta,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}
