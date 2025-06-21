<?php
// La sessione è già stata avviata in index.php, quindi non è necessario chiamare session_start() qui

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'tecnico' && $_SESSION['user_type'] !== 'admin') {
    echo "<p class='error'>Accesso negato. Solo i tecnici e gli amministratori possono visualizzare queste richieste.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op7_lista_richieste_tecnico.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$risultati = [];
$id_tecnico_selezionato = null;
$error_msg = '';

// Fetch technicians for the dropdown
$tecnici = [];
if ($_SESSION['user_type'] === 'admin') {
    $sql_tecnici = "SELECT id_utente, CONCAT(nome, ' ', cognome) AS nome_completo FROM Utente WHERE tipo = 'tecnico' ORDER BY nome_completo ASC";
    $result_tecnici = $conn->query($sql_tecnici);
    if ($result_tecnici && $result_tecnici->num_rows > 0) {
        while ($row = $result_tecnici->fetch_assoc()) {
            $tecnici[] = $row;
        }
    }
} else { // tecnico type
    $tecnici[] = ['id_utente' => $_SESSION['user_id'], 'nome_completo' => $_SESSION['user_name']];
     // Pre-select the current tecnico if they are not admin
    if(!isset($_POST['submit_cerca_tecnico'])){
        $_POST['id_tecnico'] = $_SESSION['user_id']; 
        // Simulate post to trigger data loading for the logged-in tecnico by default
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_POST['submit_cerca_tecnico'] = true; // Simulate the button press
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_cerca_tecnico'])) {
    $id_tecnico_selezionato = isset($_POST['id_tecnico']) ? (int)$_POST['id_tecnico'] : null;

    if (empty($id_tecnico_selezionato)) {
        $error_msg = "Selezionare un tecnico è obbligatorio.";
    } else {
        $sql = "SELECT R.id_richiesta, CAT.nome as nome_categoria, R.data_inserimento, R.note_generali, ".
               "P.id_cand, P.produttore, P.nome_prodotto, P.prezzo, P.esito_revisione, P.data_proposta, P.data_ordine ".
               "FROM RichiestaAcquisto R ".
               "JOIN Partecipazione T ON T.id_richiesta = R.id_richiesta AND T.ruolo = 'tecnico' ".
               "JOIN ProdottoCandidato P ON P.id_richiesta = R.id_richiesta ".
               "JOIN Categoria CAT ON R.id_categoria = CAT.id_categoria ".
               "WHERE T.id_utente = ? ".
               "AND P.esito_revisione = 'approvato' ".
               "AND P.data_ordine IS NULL ". // Non ancora ordinato
               "AND R.data_chiusura IS NULL ". // Richiesta ancora aperta
               "ORDER BY R.data_inserimento DESC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_tecnico_selezionato);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $risultati[] = $row;
                    }
                } else {
                    $error_msg = "Nessuna richiesta trovata per il tecnico selezionato con prodotti approvati ma non ancora ordinati.";
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
    <p>Questa sezione permette di estrarre la lista delle richieste di acquisto associate a un determinato tecnico, per le quali un prodotto candidato è stato approvato ma non è ancora stato formalmente ordinato (<code>data_ordine</code> è NULL) e la richiesta è ancora aperta.</p>
</div>

<form method="POST" action="index.php?op=7">
    <label for="id_tecnico">Seleziona Tecnico:</label>
    <select name="id_tecnico" id="id_tecnico" required>
        <option value="">Seleziona un tecnico...</option>
        <?php foreach ($tecnici as $tecnico): ?>
            <option value="<?php echo htmlspecialchars($tecnico['id_utente']); ?>" <?php echo ($id_tecnico_selezionato == $tecnico['id_utente']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($tecnico['nome_completo']); ?> (ID: <?php echo htmlspecialchars($tecnico['id_utente']); ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <input type="submit" name="submit_cerca_tecnico" value="Cerca Richieste Pronte per Ordine">
</form>

<?php if ($error_msg): ?>
    <p class="error"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if (!empty($risultati)): ?>
    <h3>Richieste Pronte per l'Ordine:</h3>
    <table>
        <thead>
            <tr>
                <th>ID Richiesta</th>
                <th>Categoria</th>
                <th>Data Inserimento Rich.</th>
                <th>ID Candidato</th>
                <th>Prodotto</th>
                <th>Prezzo</th>
                <th>Data Proposta Cand.</th>
                <th>Note Richiesta</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($risultati as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id_richiesta']); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_categoria']); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['data_inserimento']))); ?></td>
                    <td><?php echo htmlspecialchars($row['id_cand']); ?></td>
                    <td><?php echo htmlspecialchars($row['produttore'] . ' ' . $row['nome_prodotto']); ?></td>
                    <td>€<?php echo htmlspecialchars(number_format($row['prezzo'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['data_proposta']))); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($row['note_generali'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_cerca_tecnico']) && !$error_msg): ?>
    <p>Nessuna richiesta trovata per i criteri specificati.</p>
<?php endif; ?>

<a href="../index.php" class="back-link">Torna al menu principale</a>