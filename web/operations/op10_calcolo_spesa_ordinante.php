<?php
// session_start(); // Start the session - Rimosso perché la sessione è già avviata da index.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'ordinante') {
    echo "<p class='error'>Accesso negato. Solo gli amministratori e gli ordinanti possono calcolare la spesa.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op10_calcolo_spesa_ordinante.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$spesa_totale = null;
$id_ordinante_selezionato = null;
$nome_ordinante_selezionato = '';
$anno_selezionato = null;
$error_msg = '';

$is_ordinante_logged_in = ($_SESSION['user_type'] === 'ordinante');

// Fetch users (ordinanti) for the dropdown only if admin is logged in
$ordinanti = [];
if (!$_SESSION['user_type'] === 'ordinante') {
    $sql_ordinanti = "SELECT id_utente, CONCAT(nome, ' ', cognome) AS nome_completo FROM Utente WHERE tipo = 'ordinante' ORDER BY nome_completo ASC";
    $result_ordinanti = $conn->query($sql_ordinanti);
    if ($result_ordinanti && $result_ordinanti->num_rows > 0) {
        while ($row = $result_ordinanti->fetch_assoc()) {
            $ordinanti[] = $row;
        }
    }
} elseif ($is_ordinante_logged_in) {
    // If ordinante is logged in, set their ID and name directly
    $id_ordinante_selezionato = $_SESSION['user_id'];
    // We need to fetch the name if not readily available in session, or construct it if parts are.
    // Assuming $_SESSION['user_name'] and $_SESSION['user_cognome'] exist from login
    if (isset($_SESSION['user_name']) && isset($_SESSION['user_cognome'])) {
        $nome_ordinante_selezionato = $_SESSION['user_name'] . ' ' . $_SESSION['user_cognome'];
    } else {
        // Fallback: Query the name based on ID if not in session
        $stmt_user_name = $conn->prepare("SELECT CONCAT(nome, ' ', cognome) AS nome_completo FROM Utente WHERE id_utente = ?");
        $stmt_user_name->bind_param("i", $_SESSION['user_id']);
        $stmt_user_name->execute();
        $result_user_name = $stmt_user_name->get_result();
        if ($result_user_name->num_rows > 0) {
            $user_data = $result_user_name->fetch_assoc();
            $nome_ordinante_selezionato = $user_data['nome_completo'];
        }
        $stmt_user_name->close();
    }
}

// Generate a list of years (e.g., last 10 years to next 5 years)
$anni_disponibili = [];
$anno_corrente = date('Y');
for ($i = $anno_corrente + 2; $i >= $anno_corrente - 10; $i--) {
    $anni_disponibili[] = $i;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_calcola_spesa'])) {
    if ($is_ordinante_logged_in) {
        // Ordinante is logged in, ID and name are already set from session
        // $id_ordinante_selezionato is already set
        // $nome_ordinante_selezionato is already set
    } else {
        // Admin is logged in, get ordinante from POST
        $id_ordinante_selezionato = isset($_POST['id_ordinante']) ? (int)$_POST['id_ordinante'] : null;
        // Get ordinante's name for display if admin selected one
        if ($id_ordinante_selezionato) {
            foreach ($ordinanti as $ord) {
                if ($ord['id_utente'] == $id_ordinante_selezionato) {
                    $nome_ordinante_selezionato = $ord['nome_completo'];
                    break;
                }
            }
        }
    }
    $anno_selezionato = isset($_POST['anno']) ? (int)$_POST['anno'] : null;

    if (empty($id_ordinante_selezionato) || empty($anno_selezionato)) {
        $error_msg = "L'ID dell'ordinante e l'anno sono obbligatori.";
        if (empty($id_ordinante_selezionato) && !$is_ordinante_logged_in) {
             $error_msg = "Selezionare un ordinante e un anno sono obbligatori.";
        } elseif (empty($anno_selezionato)) {
            $error_msg = "Selezionare un anno è obbligatorio.";
        }
    } else {

        $sql = "SELECT SUM(C.prezzo) AS spesa_totale ".
               "FROM Partecipazione Par ".
               "JOIN RichiestaAcquisto R ON R.id_richiesta = Par.id_richiesta ".
               "JOIN ProdottoCandidato C ON C.id_richiesta = R.id_richiesta ".
               "WHERE Par.ruolo = 'ordinante' ".
               "  AND Par.id_utente = ? ".
               "  AND C.esito_revisione = 'approvato' ".
               "  AND R.esito_chiusura = 'accettato' ". // Prodotto candidato approvato E richiesta chiusa con accettazione
               "  AND YEAR(C.data_ordine) = ?"; // Anno dell'ordine del prodotto
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("ii", $id_ordinante_selezionato, $anno_selezionato);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $row = $result->fetch_assoc();
                    $spesa_totale = $row['spesa_totale']; // This will be NULL if no records match, or the sum
                    if ($spesa_totale === null) $spesa_totale = 0; // Treat NULL sum as 0
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
    <p>Questa sezione permette di calcolare la somma totale spesa da un determinato ordinante in un anno solare specifico.</p>
    <p>La spesa è calcolata sommando i prezzi dei prodotti candidati che sono stati 'approvati', associati a richieste chiuse con esito 'accettato', e il cui <code>data_ordine</code> ricade nell'anno selezionato.</p>
</div>

<form method="POST" action="index.php?op=10">
    <?php if (!$is_ordinante_logged_in): // Mostra il dropdown solo se l'admin è loggato ?>
        <label for="id_ordinante">Seleziona Ordinante:</label>
        <select name="id_ordinante" id="id_ordinante" required>
            <option value="">Seleziona un ordinante...</option>
            <?php foreach ($ordinanti as $ordinante): ?>
                <option value="<?php echo htmlspecialchars($ordinante['id_utente']); ?>" <?php echo ($id_ordinante_selezionato == $ordinante['id_utente']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($ordinante['nome_completo']); ?> (ID: <?php echo htmlspecialchars($ordinante['id_utente']); ?>)
                </option>
            <?php endforeach; ?>
        </select>
    <?php else: // Se l'ordinante è loggato, passa il suo ID come hidden input o non passarlo se già gestito in PHP ?>
        <input type="hidden" name="id_ordinante" value="<?php echo htmlspecialchars($_SESSION['user_id']); ?>">
        <p>Stai calcolando la spesa per te stesso (Ordinante: <?php echo htmlspecialchars($nome_ordinante_selezionato); ?>).</p>
    <?php endif; ?>

    <label for="anno">Seleziona Anno Solare:</label>
    <select name="anno" id="anno" required>
        <option value="">Seleziona un anno...</option>
        <?php foreach ($anni_disponibili as $a): ?>
            <option value="<?php echo $a; ?>" <?php echo ($anno_selezionato == $a) ? 'selected' : ''; ?>><?php echo $a; ?></option>
        <?php endforeach; ?>
    </select>

    <input type="submit" name="submit_calcola_spesa" value="Calcola Spesa Totale">
</form>

<?php if ($error_msg): ?>
    <p class="error"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if ($spesa_totale !== null && !$error_msg): ?>
    <h3>Risultato Calcolo Spesa:</h3>
    <p>L'ordinante <strong><?php echo htmlspecialchars($nome_ordinante_selezionato); ?></strong> (ID: <?php echo htmlspecialchars($id_ordinante_selezionato); ?>) 
       ha speso un totale di <strong>€<?php echo htmlspecialchars(number_format($spesa_totale, 2, ',', '.')); ?></strong> 
       nell'anno <strong><?php echo htmlspecialchars($anno_selezionato); ?></strong> per prodotti approvati e accettati.</p>
<?php endif; ?>

<a href="../index.php" class="back-link">Torna al menu principale</a>