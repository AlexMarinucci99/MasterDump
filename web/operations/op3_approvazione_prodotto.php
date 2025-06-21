<?php
// session_start(); // Start the session - Rimosso perché la sessione è già avviata da index.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'ordinante' && $_SESSION['user_type'] !== 'admin') { // Modificato per includere 'ordinante'
    echo "<p class='error'>Accesso negato. Solo gli ordinanti e gli amministratori possono approvare/respingere prodotti.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op3_approvazione_prodotto.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$message = '';
$error = '';

// Fetch candidate products that are 'in_attesa'
$prodotti_candidati_in_attesa = [];
$sql_prodotti = "SELECT PC.id_cand, PC.id_richiesta, PC.produttore, PC.nome_prodotto, PC.prezzo, C.nome AS nome_categoria, RA.data_inserimento ".
                  "FROM ProdottoCandidato PC ".
                  "JOIN RichiestaAcquisto RA ON PC.id_richiesta = RA.id_richiesta ".
                  "JOIN Categoria C ON RA.id_categoria = C.id_categoria ".
                  "WHERE PC.esito_revisione = 'in_attesa' ".
                  "ORDER BY RA.data_inserimento DESC, PC.id_cand ASC";
$result_prodotti = $conn->query($sql_prodotti);
if ($result_prodotti && $result_prodotti->num_rows > 0) {
    while ($row = $result_prodotti->fetch_assoc()) {
        $prodotti_candidati_in_attesa[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_approvazione'])) {
    $id_cand = isset($_POST['id_cand']) ? (int)$_POST['id_cand'] : null;
    $azione = isset($_POST['azione']) ? $_POST['azione'] : null; // 'approva' o 'respingi'
    $motivazione_rifiuto = ($azione === 'respingi' && isset($_POST['motivazione_rifiuto'])) ? trim($_POST['motivazione_rifiuto']) : NULL;

    if (empty($id_cand) || empty($azione)) {
        $error = "Selezionare un prodotto candidato e un'azione sono obbligatori.";
    } elseif ($azione === 'respingi' && empty($motivazione_rifiuto)) {
        $error = "La motivazione è obbligatoria in caso di rifiuto.";
    } else {
        $nuovo_esito = '';
        if ($azione === 'approva') {
            $nuovo_esito = 'approvato';
        } elseif ($azione === 'respingi') {
            $nuovo_esito = 'respinto';
        }

        if (!empty($nuovo_esito)) {
            // Non aggiorniamo data_ordine durante l'approvazione del prodotto candidato
            // come richiesto dall'utente

            $stmt_approvazione = $conn->prepare("UPDATE ProdottoCandidato SET esito_revisione = ?, motivazione_rifiuto = ? WHERE id_cand = ?");
            
            if ($stmt_approvazione) {
                $stmt_approvazione->bind_param("ssi", $nuovo_esito, $motivazione_rifiuto, $id_cand);
                if ($stmt_approvazione->execute()) {
                    $message = "Prodotto candidato (ID: $id_cand) è stato " . ($nuovo_esito === 'approvato' ? 'approvato' : 'respinto') . " con successo!";
                    // Refresh the list
                    $prodotti_candidati_in_attesa = [];
                    $result_prodotti = $conn->query($sql_prodotti); // Re-run query
                    if ($result_prodotti && $result_prodotti->num_rows > 0) {
                        while ($row = $result_prodotti->fetch_assoc()) {
                            $prodotti_candidati_in_attesa[] = $row;
                        }
                    }
                } else {
                    $error = "Errore durante l'aggiornamento del prodotto candidato: " . $stmt_approvazione->error;
                }
                $stmt_approvazione->close();
            } else {
                $error = "Errore nella preparazione della query di aggiornamento: " . $conn->error;
            }
        } else {
            $error = "Azione non valida.";
        }
    }
}
?>

<div class="operation-description">
    <p>Questa sezione permette di approvare o respingere un prodotto candidato proposto per una richiesta di acquisto.</p>
    <p>Se un prodotto viene respinto, è necessario fornire una motivazione.</p>
</div>

<?php if ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="POST" action="index.php?op=3" id="formApprovazioneProdotto">
    <label for="id_cand">Seleziona Prodotto Candidato (in attesa):</label>
    <select name="id_cand" id="id_cand" required onchange="toggleMotivazione(this.value)">
        <option value="">Seleziona un prodotto...</option>
        <?php if (!empty($prodotti_candidati_in_attesa)): ?>
            <?php foreach ($prodotti_candidati_in_attesa as $prodotto): ?>
                <option value="<?php echo htmlspecialchars($prodotto['id_cand']); ?>">
                    ID Cand: <?php echo htmlspecialchars($prodotto['id_cand']); ?> (Rich. ID: <?php echo htmlspecialchars($prodotto['id_richiesta']); ?>) - 
                    <?php echo htmlspecialchars($prodotto['produttore']); ?> <?php echo htmlspecialchars($prodotto['nome_prodotto']); ?> - 
                    €<?php echo htmlspecialchars(number_format($prodotto['prezzo'], 2, ',', '.')); ?>
                    (Cat: <?php echo htmlspecialchars($prodotto['nome_categoria']); ?>)
                </option>
            <?php endforeach; ?>
        <?php else: ?>
            <option value="" disabled>Nessun prodotto candidato in attesa di revisione.</option>
        <?php endif; ?>
    </select>

    <label for="azione">Azione:</label>
    <select name="azione" id="azione" required onchange="toggleMotivazioneVisibility()">
        <option value="">Scegli un'azione...</option>
        <option value="approva">Approva</option>
        <option value="respingi">Respingi</option>
    </select>

    <div id="motivazione_div" style="display:none;">
        <label for="motivazione_rifiuto">Motivazione Rifiuto (se respinto):</label>
        <textarea name="motivazione_rifiuto" id="motivazione_rifiuto" rows="3"></textarea>
    </div>

    <input type="submit" name="submit_approvazione" value="Esegui Azione">
</form>

<script>
function toggleMotivazioneVisibility() {
    const azioneSelect = document.getElementById('azione');
    const motivazioneDiv = document.getElementById('motivazione_div');
    const motivazioneTextarea = document.getElementById('motivazione_rifiuto');

    if (azioneSelect.value === 'respingi') {
        motivazioneDiv.style.display = 'block';
        motivazioneTextarea.required = true;
    } else {
        motivazioneDiv.style.display = 'none';
        motivazioneTextarea.required = false;
        motivazioneTextarea.value = ''; // Clear if not respingi
    }
}

// Call it on page load in case a value is pre-selected (e.g. after form submission with error)
window.onload = function() {
    toggleMotivazioneVisibility();
};
</script>

<a href="../index.php" class="back-link">Torna al menu principale</a>