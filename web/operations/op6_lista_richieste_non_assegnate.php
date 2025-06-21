<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'tecnico') {
    echo "<p class='error'>Accesso negato. Solo i tecnici e gli amministratori possono visualizzare le richieste non assegnate.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op6_lista_richieste_non_assegnate.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$risultati = [];
$error_msg = '';

$sql = "SELECT R.id_richiesta, C.nome AS nome_categoria, R.data_inserimento, R.note_generali, UO.nome AS nome_ordinante, UO.cognome AS cognome_ordinante ".
       "FROM RichiestaAcquisto R ".
       "JOIN Categoria C ON R.id_categoria = C.id_categoria ".
       "LEFT JOIN Partecipazione T ON T.id_richiesta = R.id_richiesta AND T.ruolo = 'tecnico' ".
       "LEFT JOIN Partecipazione PO ON R.id_richiesta = PO.id_richiesta AND PO.ruolo = 'ordinante' ".
       "LEFT JOIN Utente UO ON PO.id_utente = UO.id_utente ".
       "WHERE T.id_utente IS NULL AND R.data_chiusura IS NULL ". // Non assegnate e non chiuse
       "ORDER BY R.data_inserimento DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $risultati[] = $row;
            }
        } else {
            $error_msg = "Nessuna richiesta di acquisto non ancora assegnata a un tecnico.";
        }
    } else {
        $error_msg = "Errore nell'esecuzione della query: " . $stmt->error;
    }
    $stmt->close();
} else {
    $error_msg = "Errore nella preparazione della query: " . $conn->error;
}

?>

<div class="operation-description">
    <p>Questa sezione mostra l'elenco di tutte le richieste di acquisto che sono attualmente aperte (non chiuse) e non sono ancora state assegnate a nessun tecnico.</p>
</div>

<?php if ($error_msg && empty($risultati)): // Mostra errore solo se non ci sono risultati E c'è un messaggio di errore effettivo (non "nessuna richiesta") ?>
    <p class="error"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if (!empty($risultati)): ?>
    <h3>Richieste Non Assegnate:</h3>
    <table>
        <thead>
            <tr>
                <th>ID Richiesta</th>
                <th>Categoria</th>
                <th>Data Inserimento</th>
                <th>Ordinante</th>
                <th>Note Generali</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($risultati as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id_richiesta']); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_categoria']); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['data_inserimento']))); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_ordinante'] . ' ' . $row['cognome_ordinante']); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($row['note_generali'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif (!$error_msg): // Se non ci sono risultati e nessun messaggio di errore (significa che la query è andata bene ma non ha trovato nulla) ?>
    <p>Al momento non ci sono richieste di acquisto non assegnate.</p>
<?php // Rimosso il blocco che mostrava il messaggio duplicato ?>
<?php endif; ?>

<a href="../index.php" class="back-link">Torna al menu principale</a>