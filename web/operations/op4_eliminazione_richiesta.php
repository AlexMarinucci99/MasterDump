<?php
// session_start(); // Start the session - Rimosso perché la sessione è già avviata da index.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'ordinante' && $_SESSION['user_type'] !== 'tecnico') {
    echo "<p class='error'>Accesso negato. Solo gli amministratori, gli ordinanti e i tecnici possono eliminare le richieste.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op4_eliminazione_richiesta.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$message = '';
$error = '';

// Fetch all requests for the dropdown (consider if only open or all requests can be deleted)
// For now, let's list all requests. The ON DELETE CASCADE will handle related records.
$richieste_esistenti = [];
$sql_richieste = "SELECT R.id_richiesta, C.nome AS nome_categoria, R.data_inserimento, U.nome AS nome_ordinante, U.cognome AS cognome_ordinante, R.esito_chiusura ".
                   "FROM RichiestaAcquisto R ".
                   "JOIN Categoria C ON R.id_categoria = C.id_categoria ".
                   "LEFT JOIN Partecipazione P_ord ON R.id_richiesta = P_ord.id_richiesta AND P_ord.ruolo = 'ordinante' ".
                   "LEFT JOIN Utente U ON P_ord.id_utente = U.id_utente ".
                   "ORDER BY R.data_inserimento DESC";
$result_richieste = $conn->query($sql_richieste);
if ($result_richieste && $result_richieste->num_rows > 0) {
    while ($row = $result_richieste->fetch_assoc()) {
        $richieste_esistenti[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_eliminazione'])) {
    $id_richiesta_da_eliminare = isset($_POST['id_richiesta_da_eliminare']) ? (int)$_POST['id_richiesta_da_eliminare'] : null;

    if (empty($id_richiesta_da_eliminare)) {
        $error = "Selezionare una richiesta da eliminare è obbligatorio.";
    } else {
        // The foreign key constraints with ON DELETE CASCADE should handle deletion of related records in:
        // Partecipazione, ValoreRichiesta, ProdottoCandidato
        $stmt_elimina = $conn->prepare("DELETE FROM RichiestaAcquisto WHERE id_richiesta = ?");
        if ($stmt_elimina) {
            $stmt_elimina->bind_param("i", $id_richiesta_da_eliminare);
            if ($stmt_elimina->execute()) {
                if ($stmt_elimina->affected_rows > 0) {
                    $message = "Richiesta di acquisto (ID: $id_richiesta_da_eliminare) e tutti i record dipendenti sono stati eliminati con successo!";
                    // Refresh the list of requests
                    $richieste_esistenti = [];
                    $result_richieste = $conn->query($sql_richieste); // Re-run query
                    if ($result_richieste && $result_richieste->num_rows > 0) {
                        while ($row = $result_richieste->fetch_assoc()) {
                            $richieste_esistenti[] = $row;
                        }
                    }
                } else {
                    $error = "Nessuna richiesta trovata con ID $id_richiesta_da_eliminare, o non è stato possibile eliminarla.";
                }
            } else {
                $error = "Errore durante l'eliminazione della richiesta: " . $stmt_elimina->error;
            }
            $stmt_elimina->close();
        } else {
            $error = "Errore nella preparazione della query di eliminazione: " . $conn->error;
        }
    }
}
?>

<div class="operation-description">
    <p>Questa sezione permette di eliminare una richiesta di acquisto esistente dal sistema.</p>
    <p><strong>Attenzione:</strong> L'eliminazione di una richiesta comporterà anche l'eliminazione di tutte le partecipazioni (ordinante, tecnico), i valori delle caratteristiche e gli eventuali prodotti candidati associati a essa, a causa delle regole ON DELETE CASCADE definite nello schema del database.</p>
</div>

<?php if ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="POST" action="index.php?op=4" onsubmit="return confirm('Sei sicuro di voler eliminare questa richiesta? Questa azione è irreversibile e cancellerà tutti i dati associati.');">
    <label for="id_richiesta_da_eliminare">Seleziona Richiesta da Eliminare:</label>
    <select name="id_richiesta_da_eliminare" id="id_richiesta_da_eliminare" required>
        <option value="">Seleziona una richiesta...</option>
        <?php if (!empty($richieste_esistenti)): ?>
            <?php foreach ($richieste_esistenti as $richiesta): ?>
                <option value="<?php echo htmlspecialchars($richiesta['id_richiesta']); ?>">
                    ID: <?php echo htmlspecialchars($richiesta['id_richiesta']); ?> - 
                    <?php echo htmlspecialchars($richiesta['nome_categoria']); ?> 
                    (Ordinante: <?php echo htmlspecialchars($richiesta['nome_ordinante'] . ' ' . $richiesta['cognome_ordinante']); ?>)
                    (del <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($richiesta['data_inserimento']))); ?>)
                    <?php echo $richiesta['esito_chiusura'] ? '[Chiusa: '.htmlspecialchars($richiesta['esito_chiusura']).']' : '[Aperta]'; ?>
                </option>
            <?php endforeach; ?>
        <?php else: ?>
            <option value="" disabled>Nessuna richiesta esistente nel sistema.</option>
        <?php endif; ?>
    </select>

    <input type="submit" name="submit_eliminazione" value="Elimina Richiesta Selezionata" style="background-color: #dc3545;">
</form>

<a href="../index.php" class="back-link">Torna al menu principale</a>