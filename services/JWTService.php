<?php
class JWTPayload {
    public $id;
    public $email;
    public $exp;
}

class JWTService {
    private static $secretKey = '8f890bf03eea60ae2c136b6e3b22afd6480ae5711635038dfe6de4646592a16d';

    public static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function generateJWT($id, $email) {
        $payload = new JWTPayload();
        $payload->id = $id;
        $payload->email = $email;

        $payload->exp = time() + (60 * 60); // 1h

        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secretKey, true);
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function verifyJWT($jwt) {
        list($header, $payload, $signature) = explode('.', $jwt);
        $validSignature = self::base64UrlEncode(hash_hmac('sha256', $header . "." . $payload, self::$secretKey, true));

        if (!hash_equals($signature, $validSignature)) {
            return false;
        }

        $payloadArray = json_decode(base64_decode($payload), true);

        if (isset($payloadArray['exp']) && $payloadArray['exp'] < time()) {
            return false;
        }

        return $payloadArray;
    }
}

?>
