<?php
require_once 'config/Database.php';

class RoomController{
    private $db;

    public function __construct(){
        $this->db = (new DataBase()) -> getConnection();
    }

    public function createRoom($id, $email) {
        $data = json_decode(file_get_contents('php://input'), true);
        $isAdmin = $this->getUserFromDB($email);

        if($isAdmin){
            $room_code = $data['room_code'] ?? null;
            $room_name = $data['room_name'] ?? null;
            $max_attempts = $data['max_attempts'] ?? null;

            if( empty($room_name)) {
                http_response_code(400);
                echo json_encode(['message' => 'Nombre de sala es requerido.']);
                return;
            }else if(empty ($room_code)){
                http_response_code(400);
                echo json_encode(['message' => 'Código de sala es requerido.']);
                return;
            }

            $room_exists = $this->getRoomFromDB($room_name, $room_code);
            if ($room_exists) {
                http_response_code(400);
                echo json_encode(['message' => $room_exists]);
                return;
            } else {
                if (is_null($max_attempts)) {
                    $query = "INSERT INTO rooms (room_code, room_name, user_id) VALUES (:room_code, :room_name, :user_id)";
                } else {
                    $query = "INSERT INTO rooms (room_code, room_name, user_id, max_attempts) VALUES (:room_code, :room_name, :user_id, :max_attempts)";
                }
                $stmt = $this->db->prepare($query);
                $stmt->bindParam(':room_code', $room_code);
                $stmt->bindParam(':room_name', $room_name);
                $stmt->bindParam(':user_id', $id);
                if (!is_null($max_attempts)) {
                    $stmt->bindParam(':max_attempts', $max_attempts);
                }

                if ($stmt->execute()) {
                    http_response_code(201);
                    echo json_encode(['message' => 'Se ha creado la sala.']);
                    return true;
                } else {
                    http_response_code(500);
                    echo json_encode(['message' => 'Error al crear la sala.']);
                    return false;
                }
            }
        }else{
            http_response_code(400);
            echo json_encode(['message' => 'Acceso denegado. Solo los administradores pueden crear salas.']);
            return;
        }
    }

    private function getUserFromDB($email) {
        $query = "SELECT role FROM users WHERE email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($result)) {
            return null;
        }

        return $result['role'] === 'admin';
    }

    private function getRoomFromDB($room_name, $room_code) {
        $queryName = "SELECT * FROM rooms WHERE room_name = :room_name";
        $stmtName = $this->db->prepare($queryName);
        $stmtName->bindParam(':room_name', $room_name);
        $stmtName->execute();
        $resultName = $stmtName->fetch(PDO::FETCH_ASSOC);

        $queryCode = "SELECT * FROM rooms WHERE room_code = :room_code";
        $stmtCode = $this->db->prepare($queryCode);
        $stmtCode->bindParam(':room_code', $room_code);
        $stmtCode->execute();
        $resultCode = $stmtCode->fetch(PDO::FETCH_ASSOC);

        if ($resultName && $resultCode) {
            return "El nombre de la sala ($room_name) y el código ($room_code) ya existen.";
        } elseif ($resultName) {
            return "El nombre de la sala ($room_name) ya existe.";
        } elseif ($resultCode) {
            return "El código ($room_code) ya existe.";
        }

        return false;
    }

    public function joinRoom($id, $email) {
        $data = json_decode(file_get_contents('php://input'), true);
        $room_code = $data['room_code'] ?? null;

        if (!$room_code) {
            http_response_code(400);
            echo json_encode(['message' => 'Código de sala no proporcionado.']);
            return;
        }

        // Verificar si la sala existe y obtener max_attempts
        $queryRoom = "SELECT id, room_name, max_attempts FROM rooms WHERE room_code = :room_code";
        $stmtRoom = $this->db->prepare($queryRoom);
        $stmtRoom->bindParam(':room_code', $room_code);
        $stmtRoom->execute();
        $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            http_response_code(400);
            echo json_encode(['message' => 'La sala con el código proporcionado no existe.']);
            return;
        }

        $room_name = $room['room_name'];

        // Contar los intentos realizados por el usuario en esta sala
        $queryAttempts = "SELECT COUNT(*) AS total_attempts FROM tried WHERE user_id = :user_id AND room_id = :room_id";
        $stmtAttempts = $this->db->prepare($queryAttempts);
        $stmtAttempts->bindParam(':user_id', $id);
        $stmtAttempts->bindParam(':room_id', $room['id']);
        $stmtAttempts->execute();
        $attempts = $stmtAttempts->fetch(PDO::FETCH_ASSOC);
        $totalAttempts = $attempts['total_attempts'];

        // Verificar si los intentos son ilimitados
        if ($room['max_attempts'] == -1) {
            http_response_code(200); // Código de estado HTTP 200: OK
            echo json_encode([
                'message' => 'Intentos ilimitados en esta sala.',
                'attempts' => "$totalAttempts / ∞",
                'room_name' => "$room_name",
                'room_code' => "$room_code"
            ]);
            return;
        }

        // Verificar si el usuario ha alcanzado el límite de intentos
        if ($totalAttempts >= $room['max_attempts']) {
            http_response_code(403); // Código de estado HTTP 403: Forbidden
            echo json_encode([
                'message' => 'Acceso denegado: Has alcanzado el número máximo de intentos en esta sala.',
                'attempts' => "$totalAttempts / {$room['max_attempts']}",
                'room_name' => "$room_name",
                'room_code' => "$room_code" // TODO: Change to (0 or -1) or consider adding a boolean field.
            ]);
            return;
        }

        // Permitir el ingreso si le quedan intentos
        $remainingAttempts = $room['max_attempts'] - $totalAttempts;
        http_response_code(200);
        echo json_encode([
            'message' => 'Acceso permitido',
            'remaining_attempts' => $remainingAttempts,
            'attempts' => "$totalAttempts / {$room['max_attempts']}",
            'room_name' => "$room_name",
            'room_code' => "$room_code"
        ]);
        return;
    }
}

?>