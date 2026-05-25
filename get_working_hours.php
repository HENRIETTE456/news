<?php
require_once 'config/database.php';
header('Content-Type: application/json');

if(isset($_POST['doctor_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM working_hours WHERE doctor_id = ?");
        $stmt->execute([$_POST['doctor_id']]);
        $result = [];
        while($row = $stmt->fetch()) {
            $result[$row['day_of_week']] = [
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time']
            ];
        }
        echo json_encode($result);
    } catch(PDOException $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>