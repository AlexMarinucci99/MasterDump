<?php
// session_start(); // Start the session - Rimosso perché la sessione è già avviata da index.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'admin') {
    echo "<p class='error'>Accesso negato. Solo gli amministratori possono associare tecnici.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op2_associazione_tecnico.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$message = '';
$error = '';

// Fetch open requests not yet assigned to a technician
$richieste_aperte = [];
$sql_richieste = "SELECT R.id_richiesta, C.nome AS nome_categoria, R.data_inserimento, R.note_generali ".
                 "FROM RichiestaAcquisto R ".
                 "JOIN Categoria C ON R.id_categoria = C.id_categoria ".
                 "LEFT JOIN Partecipazione P_tec ON R.id_richiesta = P_tec.id_richiesta AND P_tec.ruolo = 'tecnico' ".
                 "WHERE R.data_chiusura IS NULL AND P_tec.id_utente IS NULL ".
                 "ORDER BY R.data_inserimento DESC";
$result_richieste = $conn->query($sql_richieste);
if ($result_richieste && $result_richieste->num_rows > 0) {
    while ($row = $result_richieste->fetch_assoc()) {
        $richieste_aperte[] = $row;
    }
}

// Fetch technicians
$tecnici = [];
$sql_tecnici = "SELECT id_utente, CONCAT(nome, ' ', cognome) AS nome_completo FROM Utente WHERE tipo = 'tecnico' ORDER BY nome_completo ASC";
$result_tecnici = $conn->query($sql_tecnici);
if ($result_tecnici && $result_tecnici->num_rows > 0) {
    while ($row = $result_tecnici->fetch_assoc()) {
        $tecnici[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_associazione'])) {
    $id_richiesta = isset($_POST['id_richiesta']) ? (int)$_POST['id_richiesta'] : null;
    $id_tecnico = isset($_POST['id_tecnico']) ? (int)$_POST['id_tecnico'] : null;

    if (empty($id_richiesta) || empty($id_tecnico)) {
        $error = "Selezionare una richiesta e un tecnico sono obbligatori.";
    } else {
        // Check if a technician is already assigned to this request (double check, though query for requests should prevent this)
        $check_sql = "SELECT id_utente FROM Partecipazione WHERE id_richiesta = ? AND ruolo = 'tecnico'";
        $stmt_check = $conn->prepare($check_sql);
        $stmt_check->bind_param("i", $id_richiesta);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows > 0) {
            $error = "La richiesta selezionata è già stata assegnata a un tecnico.";
        } else {
            // $stmt_check->close(); // Non chiudere qui, verrà chiuso dopo
            $stmt_associazione = $conn->prepare("INSERT INTO Partecipazione(id_richiesta, id_utente, ruolo) VALUES(?, ?, 'tecnico')");
            if ($stmt_associazione) {
                $stmt_associazione->bind_param("ii", $id_richiesta, $id_tecnico);
                if ($stmt_associazione->execute()) {
                    $message = "Tecnico (ID: $id_tecnico) associato con successo alla richiesta (ID: $id_richiesta)!";
                    // Refresh the list of open requests
                    $richieste_aperte = [];
                    $result_richieste = $conn->query($sql_richieste); // Re-run the query
                    if ($result_richieste && $result_richieste->num_rows > 0) {
                        while ($row = $result_richieste->fetch_assoc()) {
                            $richieste_aperte[] = $row;
                        }
                    }
                } else {
                    $error = "Errore durante l'associazione del tecnico: " . $stmt_associazione->error;
                }
                $stmt_associazione->close();
            } else {
                $error = "Errore nella preparazione della query di associazione: " . $conn->error;
            }
        }
        $stmt_check->close(); // Chiudi $stmt_check qui, dopo il blocco if/else
        // La riga problematica if(isset($stmt_check) && !$stmt_check->errno) $stmt_check->close(); è stata rimossa implicitamente con questa sostituzione.
    }
}
?>

<div class="operation-description">
    <p>Questa sezione permette di associare una richiesta di acquisto aperta (non ancora chiusa e non ancora assegnata) a un tecnico.</p>
</div>

<?php if ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="POST" action="index.php?op=2">
    <label for="id_richiesta">Seleziona Richiesta Aperta:</label>
    <select name="id_richiesta" id="id_richiesta" required>
        <option value="">Seleziona una richiesta...</option>
        <?php if (!empty($richieste_aperte)): ?>
            <?php foreach ($richieste_aperte as $richiesta): ?>
                <option value="<?php echo htmlspecialchars($richiesta['id_richiesta']); ?>">
                    ID: <?php echo htmlspecialchars($richiesta['id_richiesta']); ?> - <?php echo htmlspecialchars($richiesta['nome_categoria']); ?> (del <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($richiesta['data_inserimento']))); ?>)
                </option>
            <?php endforeach; ?>
        <?php else: ?>
            <option value="" disabled>Nessuna richiesta aperta da assegnare.</option>
        <?php endif; ?>
    </select>

    <label for="id_tecnico">Seleziona Tecnico:</label>
    <select name="id_tecnico" id="id_tecnico" required>
        <option value="">Seleziona un tecnico...</option>
        <?php if (!empty($tecnici)): ?>
            <?php foreach ($tecnici as $tecnico): ?>
                <option value="<?php echo htmlspecialchars($tecnico['id_utente']); ?>">
                    <?php echo htmlspecialchars($tecnico['nome_completo']); ?> (ID: <?php echo htmlspecialchars($tecnico['id_utente']); ?>)
                </option>
            <?php endforeach; ?>
        <?php else: ?>
             <option value="" disabled>Nessun tecnico disponibile.</option>
        <?php endif; ?>
    </select>

    <input type="submit" name="submit_associazione" value="Associa Tecnico">
</form>
<br>
<a href="../index.php" class="back-link">Torna al menu principale</a>