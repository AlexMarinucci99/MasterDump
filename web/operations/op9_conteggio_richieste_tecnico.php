<?php
// File incluso da index.php, session_start() già chiamato

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'tecnico') {
    echo "<p class='error'>Accesso negato. Solo gli amministratori e i tecnici possono visualizzare il conteggio delle richieste per tecnico.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op9_conteggio_richieste_tecnico.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$conteggio = null;
$id_tecnico_selezionato = null;
$nome_tecnico_selezionato = '';
$error_msg = '';

// Fetch technicians for the dropdown
$tecnici = [];
$sql_tecnici = "SELECT id_utente, CONCAT(nome, ' ', cognome) AS nome_completo FROM Utente WHERE tipo = 'tecnico' ORDER BY nome_completo ASC";
$result_tecnici = $conn->query($sql_tecnici);
if ($result_tecnici && $result_tecnici->num_rows > 0) {
    while ($row = $result_tecnici->fetch_assoc()) {
        $tecnici[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_conteggio_tecnico'])) {
    $id_tecnico_selezionato = isset($_POST['id_tecnico']) ? (int)$_POST['id_tecnico'] : null;

    if (empty($id_tecnico_selezionato)) {
        $error_msg = "Selezionare un tecnico è obbligatorio.";
    } else {
        // Get technician's name for display
        foreach ($tecnici as $tec) {
            if ($tec['id_utente'] == $id_tecnico_selezionato) {
                $nome_tecnico_selezionato = $tec['nome_completo'];
                break;
            }
        }

        $sql = "SELECT COUNT(*) AS totale_richieste ".
               "FROM Partecipazione P ".
               "WHERE P.ruolo = 'tecnico' AND P.id_utente = ?";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_tecnico_selezionato);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $conteggio = $row['totale_richieste'];
                } else {
                    // This case should ideally not happen if COUNT(*) is used, it will return 0.
                    $conteggio = 0; 
                }
            } else {
                $error_msg = "Errore nell'esecuzione della query: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = "Errore nella preparazione della query: " . $conn->error;
        }
    }
}

?>

<div class="operation-description">
    <p>Questa sezione permette di calcolare il numero totale di richieste di acquisto gestite (cioè a cui è stato assegnato come tecnico) da un determinato tecnico.</p>
</div>

<form method="POST" action="index.php?op=9">
    <label for="id_tecnico">Seleziona Tecnico:</label>
    <select name="id_tecnico" id="id_tecnico" required>
        <option value="">Seleziona un tecnico...</option>
        <?php foreach ($tecnici as $tecnico): ?>
            <option value="<?php echo htmlspecialchars($tecnico['id_utente']); ?>" <?php echo ($id_tecnico_selezionato == $tecnico['id_utente']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($tecnico['nome_completo']); ?> (ID: <?php echo htmlspecialchars($tecnico['id_utente']); ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <input type="submit" name="submit_conteggio_tecnico" value="Calcola Conteggio Richieste">
</form>

<?php if ($error_msg): ?>
    <p class="error"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if ($conteggio !== null && !$error_msg): ?>
    <h3>Risultato Conteggio:</h3>
    <p>Il tecnico <strong><?php echo htmlspecialchars($nome_tecnico_selezionato); ?></strong> (ID: <?php echo htmlspecialchars($id_tecnico_selezionato); ?>) ha gestito un totale di <strong><?php echo htmlspecialchars($conteggio); ?></strong> richiesta/e.</p>
<?php endif; ?>

<a href="../index.php" class="back-link">Torna al menu principale</a>