<?php
include 'db_connection.php';

header('Content-Type: application/json');

$id_categoria = isset($_GET['id_categoria']) ? (int)$_GET['id_categoria'] : 0;

if ($id_categoria <= 0) {
    echo json_encode(['error' => 'ID Categoria non valido.']);
    exit;
}

$caratteristiche = [];
/*
SELECT C.id_caratt, C.nome, C.tipo_valore
FROM Caratteristica C
JOIN Possiede P ON C.id_caratt = P.id_caratt
WHERE P.id_categoria = ?
ORDER BY C.nome ASC
*/
$sql = "SELECT C.id_caratt, C.nome, C.tipo_valore ".
       "FROM Caratteristica C ".
       "JOIN Possiede P ON C.id_caratt = P.id_caratt ".
       "WHERE P.id_categoria = ? ".
       "ORDER BY C.nome ASC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['error' => 'Errore preparazione statement: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id_categoria);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $caratteristiche[] = $row;
        }
    }
} else {
    echo json_encode(['error' => 'Errore esecuzione query: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();
// $conn->close(); // Don't close connection here if it's included from index.php which closes it at the end.

echo json_encode($caratteristiche);
?>