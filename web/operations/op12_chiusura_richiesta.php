<?php
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'ordinante' && $_SESSION['user_type'] !== 'admin') {
    echo "<p class='error'>Accesso negato. Solo gli ordinanti e gli amministratori possono chiudere le richieste.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op12_chiusura_richiesta.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$message = '';
$error = '';
$id_ordinante_selezionato = null;
$prodotti_approvati = [];

// Se l'utente è un ordinante, preseleziona il suo ID
if ($_SESSION['user_type'] === 'ordinante') {
    $id_ordinante_selezionato = $_SESSION['user_id'];
}

// Fetch users (ordinanti) for the dropdown if admin is logged in
$ordinanti = [];
if ($_SESSION['user_type'] === 'admin') {
    $sql_ordinanti = "SELECT id_utente, CONCAT(nome, ' ', cognome) AS nome_completo FROM Utente WHERE tipo = 'ordinante' ORDER BY nome_completo ASC";
    $result_ordinanti = $conn->query($sql_ordinanti);
    if ($result_ordinanti && $result_ordinanti->num_rows > 0) {
        while ($row = $result_ordinanti->fetch_assoc()) {
            $ordinanti[] = $row;
        }
    }
}

// Gestione della selezione dell'ordinante (solo per admin)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_cerca_ordinante'])) {
    if ($_SESSION['user_type'] === 'ordinante') {
        $id_ordinante_selezionato = $_SESSION['user_id']; // Force to own ID for ordinante
    } else {
        $id_ordinante_selezionato = isset($_POST['id_ordinante']) ? (int)$_POST['id_ordinante'] : null;
    }

    if (empty($id_ordinante_selezionato)) {
        $error = "Selezionare un ordinante è obbligatorio.";
    }
}

// Se l'ordinante è selezionato, carica i prodotti approvati
if ($id_ordinante_selezionato) {
    $sql_prodotti = "SELECT PC.id_cand, PC.id_richiesta, PC.produttore, PC.nome_prodotto, PC.prezzo, ".  
                  "C.nome AS nome_categoria, RA.data_inserimento, PC.data_ordine ".  
                  "FROM ProdottoCandidato PC ".  
                  "JOIN RichiestaAcquisto RA ON PC.id_richiesta = RA.id_richiesta ".  
                  "JOIN Categoria C ON RA.id_categoria = C.id_categoria ".  
                  "JOIN Partecipazione P ON P.id_richiesta = RA.id_richiesta AND P.ruolo = 'ordinante' ".  
                  "WHERE PC.esito_revisione = 'approvato' ".  
                  "AND RA.data_chiusura IS NULL ".  
                  "AND P.id_utente = ? ".  
                  "ORDER BY PC.data_ordine DESC, PC.id_cand ASC";
    
    $stmt_prodotti = $conn->prepare($sql_prodotti);
    if ($stmt_prodotti) {
        $stmt_prodotti->bind_param("i", $id_ordinante_selezionato);
        if ($stmt_prodotti->execute()) {
            $result_prodotti = $stmt_prodotti->get_result();
            if ($result_prodotti && $result_prodotti->num_rows > 0) {
                while ($row = $result_prodotti->fetch_assoc()) {
                    $prodotti_approvati[] = $row;
                }
            }
        } else {
            $error = "Errore nell'esecuzione della query: " . $stmt_prodotti->error;
        }
        $stmt_prodotti->close();
    } else {
        $error = "Errore nella preparazione della query: " . $conn->error;
    }
}

// Gestione della chiusura della richiesta
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_chiusura'])) {
    $id_cand = isset($_POST['id_cand']) ? (int)$_POST['id_cand'] : null;
    $prodotto_ricevuto = isset($_POST['prodotto_ricevuto']) ? 1 : 0;
    $esito_chiusura = isset($_POST['esito_chiusura']) ? $_POST['esito_chiusura'] : null;
    
    if (empty($id_cand)) {
        $error = "Selezionare un prodotto candidato è obbligatorio.";
    } elseif (empty($esito_chiusura)) {
        $error = "Selezionare un esito di chiusura è obbligatorio.";
    } else {
        // Ottieni l'ID della richiesta dal prodotto candidato
        $stmt_get_richiesta = $conn->prepare("SELECT id_richiesta FROM ProdottoCandidato WHERE id_cand = ?");
        if ($stmt_get_richiesta) {
            $stmt_get_richiesta->bind_param("i", $id_cand);
            if ($stmt_get_richiesta->execute()) {
                $result_get_richiesta = $stmt_get_richiesta->get_result();
                if ($result_get_richiesta && $result_get_richiesta->num_rows > 0) {
                    $row_richiesta = $result_get_richiesta->fetch_assoc();
                    $id_richiesta = $row_richiesta['id_richiesta'];
                    
                    // Aggiorna la richiesta con la data di chiusura e l'esito
                    $stmt_chiusura = $conn->prepare("UPDATE RichiestaAcquisto SET data_chiusura = NOW(), esito_chiusura = ? WHERE id_richiesta = ?");
                    if ($stmt_chiusura) {
                        $stmt_chiusura->bind_param("si", $esito_chiusura, $id_richiesta);
                        if ($stmt_chiusura->execute()) {
                            $message = "Richiesta (ID: $id_richiesta) chiusa con successo! ";
                            $message .= $prodotto_ricevuto ? "Prodotto ricevuto. " : "Prodotto non ricevuto. ";
                            $message .= "Esito: $esito_chiusura";
                            
                            // Aggiorna la lista dei prodotti approvati
                            $prodotti_approvati = [];
                            if ($id_ordinante_selezionato) {
                                $stmt_prodotti = $conn->prepare($sql_prodotti);
                                if ($stmt_prodotti) {
                                    $stmt_prodotti->bind_param("i", $id_ordinante_selezionato);
                                    if ($stmt_prodotti->execute()) {
                                        $result_prodotti = $stmt_prodotti->get_result();
                                        if ($result_prodotti && $result_prodotti->num_rows > 0) {
                                            while ($row = $result_prodotti->fetch_assoc()) {
                                                $prodotti_approvati[] = $row;
                                            }
                                        }
                                    }
                                    $stmt_prodotti->close();
                                }
                            }
                        } else {
                            $error = "Errore durante l'aggiornamento della richiesta: " . $stmt_chiusura->error;
                        }
                        $stmt_chiusura->close();
                    } else {
                        $error = "Errore nella preparazione della query di chiusura: " . $conn->error;
                    }
                } else {
                    $error = "Prodotto candidato non trovato.";
                }
            } else {
                $error = "Errore nell'esecuzione della query: " . $stmt_get_richiesta->error;
            }
            $stmt_get_richiesta->close();
        } else {
            $error = "Errore nella preparazione della query: " . $conn->error;
        }
    }
}
?>

<div class="operation-description">
    <p>Questa sezione permette di chiudere una richiesta di acquisto, specificando se il prodotto è stato ricevuto e se è stato accettato o respinto.</p>
    <p>La chiusura della richiesta è possibile solo dopo che un prodotto candidato è stato approvato.</p>
</div>

<?php if ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<!-- Form per la selezione dell'ordinante (solo per admin) -->
<?php if ($_SESSION['user_type'] === 'admin'): ?>
<form method="POST" action="index.php?op=12">
    <label for="id_ordinante">Seleziona Ordinante:</label>
    <select name="id_ordinante" id="id_ordinante" required>
        <option value="">Seleziona un ordinante...</option>
        <?php foreach ($ordinanti as $ordinante): ?>
            <option value="<?php echo htmlspecialchars($ordinante['id_utente']); ?>" <?php echo (isset($id_ordinante_selezionato) && $id_ordinante_selezionato == $ordinante['id_utente']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($ordinante['nome_completo']); ?> (ID: <?php echo htmlspecialchars($ordinante['id_utente']); ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <input type="submit" name="submit_cerca_ordinante" value="Cerca Prodotti Approvati">
</form>
<?php endif; ?>

<!-- Form per la chiusura della richiesta -->
<?php if (!empty($prodotti_approvati)): ?>
<form method="POST" action="index.php?op=12" id="formChiusuraRichiesta">
    <?php if ($_SESSION['user_type'] === 'ordinante'): ?>
        <input type="hidden" name="id_ordinante" value="<?php echo $_SESSION['user_id']; ?>">
    <?php endif; ?>
    
    <label for="id_cand">Seleziona Prodotto Approvato:</label>
    <select name="id_cand" id="id_cand" required>
        <option value="">Seleziona un prodotto...</option>
        <?php foreach ($prodotti_approvati as $prodotto): ?>
            <option value="<?php echo htmlspecialchars($prodotto['id_cand']); ?>">
                ID Cand: <?php echo htmlspecialchars($prodotto['id_cand']); ?> (Rich. ID: <?php echo htmlspecialchars($prodotto['id_richiesta']); ?>) - 
                <?php echo htmlspecialchars($prodotto['produttore']); ?> <?php echo htmlspecialchars($prodotto['nome_prodotto']); ?> - 
                €<?php echo htmlspecialchars(number_format($prodotto['prezzo'], 2, ',', '.')); ?>
                (Cat: <?php echo htmlspecialchars($prodotto['nome_categoria']); ?>)
                <?php if ($prodotto['data_ordine']): ?>
                    (Ordinato il: <?php echo htmlspecialchars(date('d/m/Y', strtotime($prodotto['data_ordine']))); ?>)
                <?php endif; ?>
            </option>
        <?php endforeach; ?>
    </select>
    
    <div class="checkbox-container">
        <input type="checkbox" name="prodotto_ricevuto" id="prodotto_ricevuto">
        <label for="prodotto_ricevuto">Confermo di aver ricevuto il prodotto</label>
    </div>
    
    <label for="esito_chiusura">Esito della Chiusura:</label>
    <select name="esito_chiusura" id="esito_chiusura" required>
        <option value="">Seleziona un esito...</option>
        <option value="accettato">Accettato</option>
        <option value="respinto_non_conforme">Respinto - Non Conforme</option>
        <option value="respinto_non_funzionante">Respinto - Non Funzionante</option>
    </select>
    
    <input type="submit" name="submit_chiusura" value="Chiudi Richiesta">
</form>
<?php elseif ($id_ordinante_selezionato && !$error): ?>
    <p>Nessun prodotto approvato trovato per questo ordinante con richieste ancora aperte.</p>
<?php endif; ?>

<a href="../index.php" class="back-link">Torna al menu principale</a>

<script>
// Script per gestire la logica del form di chiusura richiesta
document.addEventListener('DOMContentLoaded', function() {
    const checkboxRicevuto = document.getElementById('prodotto_ricevuto');
    const selectEsito = document.getElementById('esito_chiusura');
    
    if (checkboxRicevuto && selectEsito) {
        // Funzione per aggiornare le opzioni di esito in base allo stato della checkbox
        function updateEsitoOptions() {
            // Salva l'opzione selezionata corrente
            const currentValue = selectEsito.value;
            
            // Resetta le opzioni
            selectEsito.innerHTML = '<option value="">Seleziona un esito...</option>';
            
            if (checkboxRicevuto.checked) {
                // Se il prodotto è stato ricevuto, mostra tutte le opzioni
                selectEsito.innerHTML += '<option value="accettato">Accettato</option>';
                selectEsito.innerHTML += '<option value="respinto_non_conforme">Respinto - Non Conforme</option>';
                selectEsito.innerHTML += '<option value="respinto_non_funzionante">Respinto - Non Funzionante</option>';
            } else {
                // Se il prodotto non è stato ricevuto, mostra solo l'opzione "respinto_non_conforme"
                selectEsito.innerHTML += '<option value="respinto_non_conforme">Respinto - Non Conforme</option>';
            }
            
            // Ripristina il valore selezionato se ancora valido
            if (currentValue && Array.from(selectEsito.options).some(option => option.value === currentValue)) {
                selectEsito.value = currentValue;
            }
        }
        
        // Aggiungi listener per il cambio di stato della checkbox
        checkboxRicevuto.addEventListener('change', updateEsitoOptions);
        
        // Inizializza le opzioni al caricamento della pagina
        updateEsitoOptions();
    }
});
</script>