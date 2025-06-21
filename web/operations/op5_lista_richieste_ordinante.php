<?php
// File incluso da index.php, session_start() già chiamato

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'ordinante' && $_SESSION['user_type'] !== 'admin') {
    echo "<p class='error'>Accesso negato. Solo gli ordinanti e gli amministratori possono visualizzare queste richieste.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op5_lista_richieste_ordinante.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$risultati = [];
$id_ordinante_selezionato = null;
$error_msg = '';

// Fetch users (ordinanti) for the dropdown
$ordinanti = [];
if ($_SESSION['user_type'] === 'admin') {
    $sql_ordinanti = "SELECT id_utente, CONCAT(nome, ' ', cognome) AS nome_completo FROM Utente WHERE tipo = 'ordinante' ORDER BY nome_completo ASC";
    $result_ordinanti = $conn->query($sql_ordinanti);
    if ($result_ordinanti && $result_ordinanti->num_rows > 0) {
        while ($row = $result_ordinanti->fetch_assoc()) {
            $ordinanti[] = $row;
        }
    }
} else { // ordinante type
    $ordinanti[] = ['id_utente' => $_SESSION['user_id'], 'nome_completo' => $_SESSION['user_name']];
    // Pre-select the current ordinante if they are not admin
    if(!isset($_POST['submit_cerca_ordinante'])){
        $_POST['id_ordinante'] = $_SESSION['user_id']; 
        // Simulate post to trigger data loading for the logged-in ordinante by default
        $_SERVER["REQUEST_METHOD"] = "POST";
        $_POST['submit_cerca_ordinante'] = true; // Simulate the button press
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_cerca_ordinante'])) {
    if ($_SESSION['user_type'] === 'ordinante') {
        $id_ordinante_selezionato = $_SESSION['user_id']; // Force to own ID for ordinante
    } else {
        $id_ordinante_selezionato = isset($_POST['id_ordinante']) ? (int)$_POST['id_ordinante'] : null;
    }

    if (empty($id_ordinante_selezionato)) {
        $error_msg = "Selezionare un ordinante è obbligatorio.";
    } else {
        $sql = "SELECT R.id_richiesta, CAT.nome as nome_categoria, R.data_inserimento, R.note_generali, ".
               "P.id_cand, P.produttore, P.nome_prodotto, P.prezzo, P.esito_revisione ".
               "FROM RichiestaAcquisto R ".
               "JOIN Partecipazione O ON O.id_richiesta = R.id_richiesta AND O.ruolo = 'ordinante' ".
               "JOIN ProdottoCandidato P ON P.id_richiesta = R.id_richiesta ".
               "JOIN Categoria CAT ON R.id_categoria = CAT.id_categoria ".
               "WHERE O.id_utente = ? ".
               "AND R.data_chiusura IS NULL ".
               "AND P.esito_revisione = 'in_attesa' ".
               "ORDER BY R.data_inserimento DESC";
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $id_ordinante_selezionato);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $risultati[] = $row;
                    }
                } else {
                    $error_msg = "Nessuna richiesta in corso trovata per l'ordinante selezionato con prodotti candidati in attesa.";
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
    <p>Questa sezione permette di estrarre la lista delle richieste di acquisto <em>in corso</em> (non chiuse) di un determinato ordinante, che hanno un prodotto candidato associato ma non ancora approvato o respinto (stato 'in_attesa').</p>
</div>

<form method="POST" action="index.php?op=5">
    <?php if ($_SESSION['user_type'] === 'admin'): ?>
        <label for="id_ordinante">Seleziona Ordinante:</label>
        <select name="id_ordinante" id="id_ordinante" required>
            <option value="">Seleziona un ordinante...</option>
            <?php foreach ($ordinanti as $ordinante): ?>
                <option value="<?php echo htmlspecialchars($ordinante['id_utente']); ?>" <?php echo (isset($id_ordinante_selezionato) && $id_ordinante_selezionato == $ordinante['id_utente']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ordinante['nome_completo']); ?> (ID: <?php echo htmlspecialchars($ordinante['id_utente']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <input type="submit" name="submit_cerca_ordinante" value="Cerca Richieste">
    <?php else: // Ordinante, no dropdown, search for self by default ?>
        <input type="hidden" name="id_ordinante" value="<?php echo $_SESSION['user_id']; ?>">
        <?php 
        // If it's an ordinante and the form hasn't been submitted yet by button click, 
        // we don't show the button, as data is auto-loaded.
        // Or, always show the button if preferred, for consistency.
        // For now, let's assume we want to auto-load and not require a button press for 'ordinante'
        // The logic to auto-submit is handled above.
        // If we want a button for ordinante to refresh, it can be added here.
        // echo '<input type="submit" name="submit_cerca_ordinante" value="Mostra le mie Richieste">'; 
        ?>
    <?php endif; ?>
</form>

<?php if ($error_msg): ?>
    <p class="error"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if (!empty($risultati)): ?>
    <h3>Richieste Trovate:</h3>
    <table>
        <thead>
            <tr>
                <th>ID Richiesta</th>
                <th>Categoria</th>
                <th>Data Inserimento</th>
                <th>Note Generali Richiesta</th>
                <th>ID Candidato</th>
                <th>Produttore</th>
                <th>Nome Prodotto</th>
                <th>Prezzo Candidato</th>
                <th>Esito Revisione</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($risultati as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['id_richiesta']); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_categoria']); ?></td>
                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($row['data_inserimento']))); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($row['note_generali'])); ?></td>
                    <td><?php echo htmlspecialchars($row['id_cand']); ?></td>
                    <td><?php echo htmlspecialchars($row['produttore']); ?></td>
                    <td><?php echo htmlspecialchars($row['nome_prodotto']); ?></td>
                    <td>€<?php echo htmlspecialchars(number_format($row['prezzo'], 2, ',', '.')); ?></td>
                    <td><?php echo htmlspecialchars($row['esito_revisione']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_cerca_ordinante']) && !$error_msg): ?>
    <p>Nessuna richiesta trovata per i criteri specificati.</p>
<?php endif; ?>

<a href="../index.php" class="back-link">Torna al menu principale</a>