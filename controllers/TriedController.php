<?php

require_once 'config/Database.php';

class TriedController{
    private $db;

    public function __construct(){
        $this->db = (new DataBase()) -> getConnection();
    }

    public function getRoomId($room_name, $room_code) {
        $queryRoom = "SELECT id FROM rooms WHERE room_name = :room_name AND room_code = :room_code LIMIT 1";
        $stmtRoom = $this->db->prepare($queryRoom);
        $stmtRoom->bindParam(':room_name', $room_name, PDO::PARAM_STR);
        $stmtRoom->bindParam(':room_code', $room_code, PDO::PARAM_STR);

        if ($stmtRoom->execute()) {
            $room = $stmtRoom->fetch(PDO::FETCH_ASSOC);
            return $room ? $room['id'] : null;
        } else {
            return null;
        }
    }

    public function saveTried($id, $email) {
        $data = json_decode(file_get_contents('php://input'), true);
        $room_name = $data['room_name'] ?? null;
        $room_code = $data['room_code'] ?? null;
        $totalreq = $data['totalreq'] ?? null;
        $movements = $data['movements'] ?? null;
        $score = $data['score'] ?? null;
        $status = $data['status'] ?? null;
        $time = $data['time'] ?? null;

        $room_id = $this->getRoomId($room_name, $room_code);

        echo $room_id;

        if (!$room_id) {
            http_response_code(400);
            echo json_encode(['message' => 'Sala no encontrada o error al obtener el ID de la sala.']);
            return false;
        }

        $query = "INSERT INTO tried (user_id, room_id, totalreq, movements, score, status, time)
            VALUES (:user_id, :room_id, :totalreq, :movements, :score, :status, :time)";
        $stmt = $this->db->prepare($query);

        $stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':room_id', $room_id, PDO::PARAM_INT);
        $stmt->bindParam(':totalreq', $totalreq, PDO::PARAM_INT);
        $stmt->bindParam(':movements', $movements, PDO::PARAM_INT);
        $stmt->bindParam(':score', $score, PDO::PARAM_INT);
        $stmt->bindParam(':status', $status, PDO::PARAM_STR);
        $stmt->bindParam(':time', $time, PDO::PARAM_STR);

        if ($stmt->execute()) {
            http_response_code(201);
            echo json_encode(['message' => 'Intento finalizado.']);
            return true;
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error, no se pudo finalizar el intento']);
            return false;
        }
    }

    public function showStats($id, $email){
        $query = "SELECT t.id, t.room_id, t.totalreq, t.movements, t.score, t.status, t.time, t.created_at, r.room_name
                FROM tried t
                JOIN rooms r ON t.room_id = r.id
                WHERE t.user_id = :user_id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $id, PDO::PARAM_INT);

        if($stmt->execute()){
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $groupedResults = [];
            foreach ($result as $row) {
                $roomName = $row['room_name'];
                unset($row['room_name']);
                unset($row['id']);
                unset($row['room_id']);

                // Agrupa los intentos por nombre de sala
                if (!isset($groupedResults[$roomName])) {
                    $groupedResults[$roomName] = [];
                }
                $groupedResults[$roomName][] = $row;
            }

            http_response_code(200);
            echo json_encode($groupedResults); // Devuelve solo el array agrupado
            return true;
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error en la consulta']);
            return false;
        }
    }

    public function showAllStats($id, $email){
        $query = "SELECT role FROM users WHERE id = :user_id AND email = :email";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['message' => 'Acceso denegado']);
                return false;
            }

            // Si es admin, obtener todos los intentos de todas las salas con detalles de usuario
            $query = "SELECT t.id, t.user_id, t.room_id, t.totalreq, t.movements, t.score, t.status, t.time, t.created_at, r.room_name, u.first_name, u.last_name, u.email
            FROM tried t
            JOIN rooms r ON t.room_id = r.id
            JOIN users u ON t.user_id = u.id
            WHERE r.user_id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $groupedResults = [];
                foreach ($result as $row) {
                    $roomName = $row['room_name'];
                    $userId = $row['user_id'];

                    // Información adicional del usuario
                    $userDetails = [
                        'first_name' => $row['first_name'],
                        'last_name' => $row['last_name'],
                        'email' => $row['email']
                    ];

                    // Eliminar los campos no necesarios para la estructura
                    unset($row['room_name']);
                    unset($row['first_name']);
                    unset($row['last_name']);
                    unset($row['email']);
                    unset($row['id']);
                    unset($row['room_id']);

                    if (!isset($groupedResults[$roomName])) {
                        $groupedResults[$roomName] = [];
                    }

                    if (!isset($groupedResults[$roomName][$userId])) {
                        $groupedResults[$roomName][$userId] = [
                            'user_details' => $userDetails,
                            'attempts' => []
                        ];
                    }

                    $groupedResults[$roomName][$userId]['attempts'][] = $row;
                }

                $finalResult = [];
                foreach ($groupedResults as $roomName => $users) {
                    $finalResult[$roomName] = [];
                    foreach ($users as $userId => $userData) {
                        $finalResult[$roomName][] = [
                            'first_name' => $userData['user_details']['first_name'],
                            'last_name' => $userData['user_details']['last_name'],
                            'email' => $userData['user_details']['email'],
                            'attempts' => $userData['attempts']
                        ];
                    }
                }

                http_response_code(200);
                echo json_encode($finalResult);
                return true;
            } else {
                http_response_code(500);
                echo json_encode(['message' => 'Error en la consulta de intentos']);
                return false;
            }
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Error al verificar usuario']);
            return false;
        }
    }
}

?>