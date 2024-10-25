<?php
class User {
    public static function getUserInfo($user) {
        return [
            'id' => $user['id'],
            'email' => $user['email'],
            "first_name" => $user['first_name'],
            "last_name" => $user['last_name'],
            "role" => $user['role'],
        ];
    }
}
?>