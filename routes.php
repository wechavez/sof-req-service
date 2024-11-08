<?php
require_once 'controllers/AuthController.php';
require_once 'controllers/UserController.php';
require_once 'controllers/RoomController.php';
require_once 'controllers/TriedController.php';
require_once 'controllers/AssistantController.php';

require_once 'middleware/AuthMiddleware.php';

function handleRoute($route, $method) {

    $authController = new AuthController();
    $userController = new UserController();
    $assistantController = new AssistantController();
    $roomController = new RoomController();
    $triedController = new TriedController();


    // Public routes
    if ($method === 'POST' && $route === '/login') {
        $authController->login();
        return;
    }

    if ($method === 'POST' && $route === '/register') {
        $authController->register();
        return;
    }

    if ($method === 'GET' && $route === '/refresh-token') {
        $payload = AuthMiddleware::validateToken();
        $authController->refreshToken($payload['id'], $payload['email']);
        return;
    }

    if ($method === 'GET' && str_starts_with($route, '/assistant')) {
        AuthMiddleware::validateToken();
        $assistantController->generateRequeriments();
        return;
    }

    // Protected routes
    if ($method === 'GET' && $route === '/users') {
        AuthMiddleware::validateToken();
        $userController->getAllUsers();
        return;
    }
    if($method === 'POST' && $route === '/create-room' ){
        $payload = AuthMiddleware::validateToken();
        $roomController -> createRoom($payload['id'], $payload['email']);
        return;
    }

    if($method === 'POST' && $route === '/join-room' ){
        $payload = AuthMiddleware::validateToken();
        $roomController -> joinRoom($payload['id'], $payload['email']);
        return;
    }

    if($method === 'POST' && $route === '/save-tried' ){
        $payload = AuthMiddleware::validateToken();
        $triedController -> saveTried($payload['id'], $payload['email']);
        return;
    }

    if($method === 'POST' && $route === '/show-stats' ){
        $payload = AuthMiddleware::validateToken();
        $triedController -> showStats($payload['id'], $payload['email']);
        return;
    }

    if($method === 'POST' && $route === '/show-all-stats' ){
        $payload = AuthMiddleware::validateToken();
        $triedController -> showAllStats($payload['id'], $payload['email']);
        return;
    }


}
?>