<?php
require_once 'services/JWTService.php';
require_once 'config/Database.php';
require_once 'models/User.php';

class RoomController {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    public function createRoom() {
        $data = json_decode(file_get_contents('php://input'), true);
        $room_code = $data['room_code'] ?? null;
        $currentUser = $this->getUserFromDB("ramos@ug.edu.ec");
        switch (true) {
            case $currentUser === null:
                http_response_code(400);
                echo json_encode(['message' => 'Usuario no encontrado']);
                return;
                break;
            case $currentUser['role'] === 'admin':
                $room_code = $data['room_code'] ?? null;
                $room_name = $data['room_name'] ?? null;
                if (empty($room_code)) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Código de sala es requerido.']);
                    return;
                }else if(empty($room_name)){
                    http_response_code(400);
                    echo json_encode(['message' => 'Nombre de sala es requerido.']);
                    return;
                }
                // TODO: CREATE ROOM
                $room_exists = $this->getRoomFromDB($room_name);
                if ($room_exists) {
                    http_response_code(400);
                    echo json_encode(['message' => 'Nombre de sala ya existe, intenta con otro']);
                    return;
                } else {
                    $query = "INSERT INTO rooms (room_code, room_name, user_id) VALUES (:room_code, :room_name, :user_id)";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindParam(':room_code', $room_code);
                    $stmt->bindParam(':room_name', $room_name);
                    $stmt->bindParam(':user_id', $currentUser['id']);
                    if ($stmt->execute()) {
                        http_response_code(500);
                        echo json_encode(['message' => 'Se ha creado la sala.']);
                        return true;
                    } else {
                        http_response_code(500);
                        echo json_encode(['message' => 'Error al crear la sala.']);
                        return false;
                    }
                }
                break;
            default:
                http_response_code(400);
                echo json_encode(['message' => 'Acceso denegado. Solo los administradores pueden crear salas.']);
                return;
                break;
        }
    }


    private function getUserFromDB($email) {
        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            return null;
        }

        return $result;
    }

    private function getRoomFromDB($room_name) {
        $query = "SELECT * FROM rooms WHERE room_name = :room_name";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_name', $room_name);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return !empty($result);
    }
}
