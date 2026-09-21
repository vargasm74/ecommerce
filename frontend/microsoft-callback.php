<?php

require_once "modelos/seguridad.php";
Seguridad::iniciarSesion();

require_once "modelos/conexion.php";
require_once "modelos/usuarios.modelo.php";
require_once "modelos/rutas.php";
require_once "modelos/microsoft.oauth.php";
require_once "controladores/usuarios.controlador.php";

$url = Ruta::ctrRuta();

if (isset($_GET["error"])) {
    error_log("Microsoft OAuth error: " . (string) $_GET["error"]);
    header("Location: " . $url . "?oauth=microsoft-error");
    exit;
}

$code = $_GET["code"] ?? "";
$state = $_GET["state"] ?? "";

try {
    if (!is_string($code) || $code === "" || !is_string($state) || $state === "") {
        throw new RuntimeException("Callback Microsoft incompleto");
    }

    $perfil = MicrosoftOAuth::obtenerPerfil($code, $state);

    $datos = [
        "nombre" => $perfil["nombre"],
        "email" => $perfil["email"],
        "foto" => "",
        "password" => "null",
        "modo" => "microsoft",
        "verificacion" => 0,
        "emailEncriptado" => "null"
    ];

    $resultado = ControladorUsuarios::ctrRegistroRedesSociales($datos);

    if ($resultado === "modo-incompatible") {
        header("Location: " . $url . "?oauth=modo-incompatible");
        exit;
    }

    if ($resultado !== "ok") {
        throw new RuntimeException("No se pudo iniciar la sesion local");
    }

    header("Location: " . $url);
    exit;

} catch (Throwable $e) {
    error_log("Microsoft OAuth callback rechazado: " . $e->getMessage());
    header("Location: " . $url . "?oauth=microsoft-error");
    exit;
}
