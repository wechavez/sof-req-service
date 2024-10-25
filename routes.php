<?php
require_once 'controllers/AuthController.php';
require_once 'controllers/UserController.php';
require_once 'controllers/RoomController.php';

require_once 'middleware/AuthMiddleware.php';

function handleRoute($route, $method) {

    $authController = new AuthController();
    $userController = new UserController();
    $roomController = new RoomController();
    $url_base = "/sof-req-service";

    echo $method .  " - " . $route . "\n";
    // Public routes
    if ($method === 'POST' && $route === $url_base . '/login') {
        $authController->login();
    }

    if ($method === 'POST' && $route === $url_base . '/register') {
        $authController->register();
    }

    if ($method === 'GET' && $route === $url_base . '/refresh-token') {
        $payload = AuthMiddleware::validateToken();
        $authController->refreshToken($payload['id'], $payload['email']);
    }

    // Protected routes
    if ($method === 'GET' && $route === $url_base . '/users') {
        AuthMiddleware::validateToken();
        $userController->getAllUsers();
    }

    if ($method === 'POST' && $route === $url_base . '/create-room') {
        AuthMiddleware::validateToken();
        $roomController->createRoom();
    }
}
?>



