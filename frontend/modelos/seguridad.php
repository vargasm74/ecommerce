<?php

class Seguridad
{
    public static function enviarHeadersSeguridad(): void
    {
        if (headers_sent()) {
            return;
        }

        header("X-Content-Type-Options: nosniff");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("X-Frame-Options: SAMEORIGIN");
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
        header("Content-Security-Policy: frame-ancestors 'self'; base-uri 'self'; object-src 'none'");

        $hstsEnabled = filter_var(
            getenv("SECURITY_HSTS_ENABLED") ?: "false",
            FILTER_VALIDATE_BOOLEAN
        );

        if ($hstsEnabled) {
            $maxAge = (int) (getenv("SECURITY_HSTS_MAX_AGE") ?: 31536000);

            if ($maxAge < 0) {
                $maxAge = 31536000;
            }

            header("Strict-Transport-Security: max-age=".$maxAge);
        }
    }

    public static function iniciarSesion(): void
    {
        self::enviarHeadersSeguridad();
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secureCookie = filter_var(
            getenv("SESSION_SECURE_COOKIE") ?: "false",
            FILTER_VALIDATE_BOOLEAN
        );

        ini_set("session.use_strict_mode", "1");
        ini_set("session.use_only_cookies", "1");

        session_set_cookie_params([
            "lifetime" => 0,
            "path" => "/",
            "secure" => $secureCookie,
            "httponly" => true,
            "samesite" => "Lax"
        ]);

        session_start();
    }

    public static function e($valor): string
    {
        return htmlspecialchars(
            (string) $valor,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8"
        );
    }

    public static function csrfToken(): string
    {
        self::iniciarSesion();

        if (empty($_SESSION["csrf_token"])) {
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        }

        return $_SESSION["csrf_token"];
    }

    public static function validarCsrf(): bool
    {
        self::iniciarSesion();

        $esperado = $_SESSION["csrf_token"] ?? "";
        $recibido = $_POST["_csrf"]
            ?? $_SERVER["HTTP_X_CSRF_TOKEN"]
            ?? "";

        return is_string($esperado)
            && $esperado !== ""
            && is_string($recibido)
            && hash_equals($esperado, $recibido);
    }

    public static function exigirCsrf(): void
    {
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") !== "POST") {
            return;
        }

        if (!self::validarCsrf()) {
            http_response_code(403);
            header("Content-Type: application/json; charset=UTF-8");
            echo json_encode([
                "error" => "Solicitud no valida o sesion expirada"
            ]);
            exit;
        }
    }
}
