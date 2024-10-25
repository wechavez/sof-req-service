<?php
require_once 'services/JWTService.php';
require_once 'config/Database.php';
require_once 'models/User.php';

class AuthController {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }


    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        $first_name = $data['first_name'] ?? null;
        $last_name = $data['last_name'] ?? null;
        $email = $data['email'] ?? null;
        $comparePassword = $data['password'];

        if (strlen($comparePassword) <= 5 ) {
            $response = [
                'ok' => false,
                'message' => 'The password must be more than 5 characters long.',
                'user' => null,
            ];

            http_response_code(400);
            echo json_encode($response);
            return;
        }

        $password = password_hash($data['password'], PASSWORD_DEFAULT) ?? null;

        if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
            $response = [
                'ok' => false,
                'message' => 'All fields are required and cannot be empty: first name, last name, email, and password.',
                'user' => null,
            ];

            http_response_code(400);
            echo json_encode($response);
            return;
        }


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $response = [
                'ok' => false,
                'message' => 'Invalid email format.',
                'user' => null,
            ];

            http_response_code(400);
            echo json_encode($response);
            return;
        }

        if (strpos($email, '@ug.edu.ec') === false || substr($email, -9) !== 'ug.edu.ec') {
            $response = [
                'ok' => false,
                'message' => 'Email must be from the @ug.edu.ec domain.',
                'user' => null,
            ];

            http_response_code(400);
            echo json_encode($response);
            return;
        }

        $takenUser = $this->getUserFromDB($email);

        if ($takenUser) {
            $response = [
                'ok' => false,
                'message' => 'User already registered',
                'user' => null,
            ];

            http_response_code(409);

            echo json_encode($response);
            return;
        }

        $query = "INSERT INTO users (first_name, last_name, email, password) VALUES (:first_name, :last_name, :email, :password)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password);

        if ($stmt->execute()) {
            $user = $this->getUserFromDB($email);
            $response = $this->generateLoginResponse($user);
            http_response_code(201);
        } else {
            $response = [
                'ok' => false,
                'message' => 'Registration failed',
                'user' => null,
            ];
            http_response_code(500);
        }

        echo json_encode($response);
    }

    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        $email = $data['email'];
        $password = $data['password'];


        $user = $this->getUserFromDB($email);

        if (!$user) {
            $response = [
                'ok' => false,
                'message' => 'User does not exist',
                'user' => null,
            ];

            http_response_code(404);
            echo json_encode($response);
            return;
        }

        if (!password_verify($password, $user['password'])) {
            $response = [
                'ok' => false,
                'message' => 'Invalid email or password',
                'user' => null,
            ];

            http_response_code(401);
            echo json_encode($response);
            return;
        }

        $response = $this->generateLoginResponse($user);

        http_response_code(200);
        echo json_encode($response);
    }

    public function refreshToken($id, $email) {
        $user = $this->getUserFromDB($email);

        if (!$user) {
            $response = [
                'ok' => false,
                'message' => 'User does not exist',
                'user' => null,
            ];

            http_response_code(404);
            echo json_encode($response);
            return;
        }

        $response = $this->generateLoginResponse($user);
        http_response_code(200);
        echo json_encode($response);
    }

    private function generateLoginResponse($user) {
        $token = JWTService::generateJWT($user['id'], $user['email']);

        return [
            'ok' => true,
            'token' => $token,
            'user' => User::getUserInfo($user),
        ];
    }

    private function getUserFromDB($email) {
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($result)) {
            return null;
        }

        return $result[0];
    }
}

