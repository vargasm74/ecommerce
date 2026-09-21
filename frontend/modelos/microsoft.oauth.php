<?php

class MicrosoftOAuth
{
    private static function base64UrlEncode(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }

    public static function configurado(): bool
    {
        return filter_var(
            getenv("MICROSOFT_OAUTH_ENABLED") ?: "false",
            FILTER_VALIDATE_BOOLEAN
        )
            && (getenv("MICROSOFT_CLIENT_ID") ?: "") !== ""
            && (getenv("MICROSOFT_CLIENT_SECRET") ?: "") !== ""
            && (getenv("MICROSOFT_REDIRECT_URI") ?: "") !== "";
    }

    public static function rutaAutorizacion(): string
    {
        if (!self::configurado()) {
            return "#";
        }

        Seguridad::iniciarSesion();

        $tenant = getenv("MICROSOFT_TENANT") ?: "common";
        $clientId = getenv("MICROSOFT_CLIENT_ID") ?: "";
        $redirectUri = getenv("MICROSOFT_REDIRECT_URI") ?: "";

        $state = bin2hex(random_bytes(32));
        $verifier = self::base64UrlEncode(random_bytes(64));
        $challenge = self::base64UrlEncode(
            hash("sha256", $verifier, true)
        );

        $_SESSION["microsoft_oauth_state"] = $state;
        $_SESSION["microsoft_pkce_verifier"] = $verifier;
        $_SESSION["microsoft_oauth_started_at"] = time();

        $params = [
            "client_id" => $clientId,
            "response_type" => "code",
            "redirect_uri" => $redirectUri,
            "response_mode" => "query",
            "scope" => "openid profile email User.Read",
            "state" => $state,
            "code_challenge" => $challenge,
            "code_challenge_method" => "S256"
        ];

        return "https://login.microsoftonline.com/"
            . rawurlencode($tenant)
            . "/oauth2/v2.0/authorize?"
            . http_build_query($params, "", "&", PHP_QUERY_RFC3986);
    }

    public static function obtenerPerfil(string $code, string $state): array
    {
        if (!self::configurado()) {
            throw new RuntimeException("Microsoft OAuth no esta configurado");
        }

        Seguridad::iniciarSesion();

        $stateEsperado = $_SESSION["microsoft_oauth_state"] ?? "";
        $verifier = $_SESSION["microsoft_pkce_verifier"] ?? "";
        $inicio = (int) ($_SESSION["microsoft_oauth_started_at"] ?? 0);

        unset(
            $_SESSION["microsoft_oauth_state"],
            $_SESSION["microsoft_pkce_verifier"],
            $_SESSION["microsoft_oauth_started_at"]
        );

        if (
            !is_string($stateEsperado)
            || $stateEsperado === ""
            || !hash_equals($stateEsperado, $state)
            || !is_string($verifier)
            || $verifier === ""
            || $inicio <= 0
            || (time() - $inicio) > 600
        ) {
            throw new RuntimeException("Estado OAuth invalido o vencido");
        }

        $tenant = getenv("MICROSOFT_TENANT") ?: "common";
        $clientId = getenv("MICROSOFT_CLIENT_ID") ?: "";
        $clientSecret = getenv("MICROSOFT_CLIENT_SECRET") ?: "";
        $redirectUri = getenv("MICROSOFT_REDIRECT_URI") ?: "";

        $tokenUrl = "https://login.microsoftonline.com/"
            . rawurlencode($tenant)
            . "/oauth2/v2.0/token";

        $token = self::postForm($tokenUrl, [
            "client_id" => $clientId,
            "client_secret" => $clientSecret,
            "grant_type" => "authorization_code",
            "code" => $code,
            "redirect_uri" => $redirectUri,
            "code_verifier" => $verifier,
            "scope" => "openid profile email User.Read"
        ]);

        $accessToken = $token["access_token"] ?? "";

        if (!is_string($accessToken) || $accessToken === "") {
            throw new RuntimeException("Microsoft no devolvio un access token");
        }

        $perfil = self::getJson(
            "https://graph.microsoft.com/v1.0/me?$select=displayName,mail,userPrincipalName",
            $accessToken
        );

        $email = trim((string) (
            $perfil["mail"]
            ?? $perfil["userPrincipalName"]
            ?? ""
        ));

        $nombre = trim((string) ($perfil["displayName"] ?? ""));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Microsoft no devolvio un email valido");
        }

        if ($nombre === "") {
            $nombre = strstr($email, "@", true) ?: "Usuario Microsoft";
        }

        return [
            "nombre" => $nombre,
            "email" => $email
        ];
    }

    private static function postForm(string $url, array $datos): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(
                $datos,
                "",
                "&",
                PHP_QUERY_RFC3986
            ),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/x-www-form-urlencoded"
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20
        ]);

        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($respuesta === false || $error !== "" || $codigo < 200 || $codigo >= 300) {
            throw new RuntimeException("Fallo el intercambio OAuth con Microsoft");
        }

        $json = json_decode($respuesta, true);

        if (!is_array($json)) {
            throw new RuntimeException("Respuesta OAuth invalida");
        }

        return $json;
    }

    private static function getJson(string $url, string $accessToken): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $accessToken,
                "Accept: application/json"
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20
        ]);

        $respuesta = curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($respuesta === false || $error !== "" || $codigo < 200 || $codigo >= 300) {
            throw new RuntimeException("No se pudo obtener el perfil de Microsoft");
        }

        $json = json_decode($respuesta, true);

        if (!is_array($json)) {
            throw new RuntimeException("Perfil Microsoft invalido");
        }

        return $json;
    }
}
