<?php
// File incluso da index.php, session_start() già chiamato

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// No specific role check needed for this operation, as all logged-in users can view details.

// op8_dettaglio_richiesta.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$dettaglio_richiesta = null;
$valori_richiesta = [];
$id_richiesta_selezionata = null;
$error_msg = '';

// Fetch all requests for the dropdown
$tutte_le_richieste = [];
$sql_tutte_richieste = "SELECT R.id_richiesta, C.nome AS nome_categoria, R.data_inserimento, U.nome AS nome_ordinante, U.cognome AS cognome_ordinante ".
                         "FROM RichiestaAcquisto R ".
                         "JOIN Categoria C ON R.id_categoria = C.id_categoria ".
                         "LEFT JOIN Partecipazione P_ord ON R.id_richiesta = P_ord.id_richiesta AND P_ord.ruolo = 'ordinante' ".
                         "LEFT JOIN Utente U ON P_ord.id_utente = U.id_utente ".
                         "ORDER BY R.data_inserimento DESC";
$result_tutte_richieste = $conn->query($sql_tutte_richieste);
if ($result_tutte_richieste && $result_tutte_richieste->num_rows > 0) {
    while ($row = $result_tutte_richieste->fetch_assoc()) {
        $tutte_le_richieste[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET['id_richiesta'])) {
    $id_richiesta_selezionata = (int)$_GET['id_richiesta'];
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_dettaglio_richiesta'])) {
    $id_richiesta_selezionata = isset($_POST['id_richiesta']) ? (int)$_POST['id_richiesta'] : null;
}

if ($id_richiesta_selezionata) {
    $sql_dettaglio = "SELECT R.id_richiesta, CAT.nome AS nome_categoria, R.data_inserimento, R.data_chiusura, R.note_generali AS note_richiesta, R.esito_chiusura,".
                     "UO.id_utente AS id_ordinante, CONCAT(UO.nome, ' ', UO.cognome) AS nome_ordinante, UO.email AS email_ordinante, ".
                     "UT.id_utente AS id_tecnico, CONCAT(UT.nome, ' ', UT.cognome) AS nome_tecnico, UT.email AS email_tecnico, ".
                     "P.id_cand, P.produttore, P.nome_prodotto, P.codice_prodotto, ".
                     "P.prezzo, P.url, P.note AS note_prodotto, P.esito_revisione, P.motivazione_rifiuto, P.data_proposta, P.data_ordine ".
                     "FROM RichiestaAcquisto R ".
                     "JOIN Categoria CAT ON R.id_categoria = CAT.id_categoria ".
                     "LEFT JOIN Partecipazione PO ON PO.id_richiesta = R.id_richiesta AND PO.ruolo = 'ordinante' ".
                     "LEFT JOIN Utente UO ON PO.id_utente = UO.id_utente ".
                     "LEFT JOIN Partecipazione PT ON PT.id_richiesta = R.id_richiesta AND PT.ruolo = 'tecnico' ".
                     "LEFT JOIN Utente UT ON PT.id_utente = UT.id_utente ".
                     "LEFT JOIN ProdottoCandidato P ON P.id_richiesta = R.id_richiesta ". // Assumiamo un solo candidato per richiesta o il più rilevante
                     "WHERE R.id_richiesta = ?";
    
    $stmt_dettaglio = $conn->prepare($sql_dettaglio);
    if ($stmt_dettaglio) {
        $stmt_dettaglio->bind_param("i", $id_richiesta_selezionata);
        if ($stmt_dettaglio->execute()) {
            $result_dettaglio = $stmt_dettaglio->get_result();
            if ($result_dettaglio->num_rows > 0) {
                $dettaglio_richiesta = $result_dettaglio->fetch_assoc();

                // Fetch valori caratteristiche
                $sql_valori = "SELECT C.nome AS nome_caratteristica, VR.valore, VR.indifferente ".
                              "FROM ValoreRichiesta VR ".
                              "JOIN Caratteristica C ON VR.id_caratt = C.id_caratt ".
                              "WHERE VR.id_richiesta = ? ".
                              "ORDER BY C.nome ASC";
                $stmt_valori = $conn->prepare($sql_valori);
                if ($stmt_valori) {
                    $stmt_valori->bind_param("i", $id_richiesta_selezionata);
                    if ($stmt_valori->execute()) {
                        $result_valori = $stmt_valori->get_result();
                        while ($row_val = $result_valori->fetch_assoc()) {
                            $valori_richiesta[] = $row_val;
                        }
                    }
                    $stmt_valori->close();
                } else {
                     $error_msg = "Errore preparazione query valori caratteristiche: " . $conn->error;
                }
            } else {
                $error_msg = "Nessuna richiesta trovata con l'ID specificato.";
            }
        } else {
            $error_msg = "Errore nell'esecuzione della query di dettaglio: " . $stmt_dettaglio->error;
        }
        $stmt_dettaglio->close();
    } else {
        $error_msg = "Errore nella preparazione della query di dettaglio: " . $conn->error;
    }
}

?>

<div class="operation-description">
    <p>Questa sezione permette di visualizzare tutti i dettagli di una specifica richiesta di acquisto, inclusi i dati della richiesta iniziale, le caratteristiche richieste, l'eventuale tecnico assegnato e i dettagli del prodotto candidato (se presente) con il relativo stato di approvazione.</p>
</div>

<form method="POST" action="index.php?op=8">
    <label for="id_richiesta">Seleziona Richiesta per Dettaglio:</label>
    <select name="id_richiesta" id="id_richiesta" required onchange="this.form.submit()"> <!-- Submit on change -->
        <option value="">Seleziona una richiesta...</option>
        <?php foreach ($tutte_le_richieste as $richiesta_item): ?>
            <option value="<?php echo htmlspecialchars($richiesta_item['id_richiesta']); ?>" <?php echo ($id_richiesta_selezionata == $richiesta_item['id_richiesta']) ? 'selected' : ''; ?>>
                ID: <?php echo htmlspecialchars($richiesta_item['id_richiesta']); ?> - 
                <?php echo htmlspecialchars($richiesta_item['nome_categoria']); ?> 
                (Ordinante: <?php echo htmlspecialchars($richiesta_item['nome_ordinante'] . ' ' . $richiesta_item['cognome_ordinante']); ?> 
                del <?php echo htmlspecialchars(date('d/m/Y', strtotime($richiesta_item['data_inserimento']))); ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <noscript><input type="submit" name="submit_dettaglio_richiesta" value="Mostra Dettaglio"></noscript>
</form>

<?php if ($error_msg): ?>
    <p class="error"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if ($dettaglio_richiesta): ?>
    <h3>Dettaglio Richiesta ID: <?php echo htmlspecialchars($dettaglio_richiesta['id_richiesta']); ?></h3>
    
    <h4>Informazioni Richiesta</h4>
    <table>
        <tr><th>Categoria</th><td><?php echo htmlspecialchars($dettaglio_richiesta['nome_categoria']); ?></td></tr>
        <tr><th>Data Inserimento</th><td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($dettaglio_richiesta['data_inserimento']))); ?></td></tr>
        <tr><th>Note Richiesta</th><td><?php echo nl2br(htmlspecialchars($dettaglio_richiesta['note_richiesta'])); ?></td></tr>
        <tr><th>Stato Richiesta</th><td><?php echo $dettaglio_richiesta['data_chiusura'] ? 'Chiusa' : 'Aperta'; ?></td></tr>
        <?php if ($dettaglio_richiesta['data_chiusura']): ?>
            <tr><th>Data Chiusura</th><td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($dettaglio_richiesta['data_chiusura']))); ?></td></tr>
            <tr><th>Esito Chiusura</th><td><?php echo htmlspecialchars($dettaglio_richiesta['esito_chiusura']); ?></td></tr>
        <?php endif; ?>
    </table>

    <h4>Ordinante</h4>
    <table>
        <tr><th>Nome</th><td><?php echo htmlspecialchars($dettaglio_richiesta['nome_ordinante']); ?></td></tr>
        <tr><th>Email</th><td><?php echo htmlspecialchars($dettaglio_richiesta['email_ordinante']); ?></td></tr>
    </table>

    <?php if ($dettaglio_richiesta['id_tecnico']): ?>
        <h4>Tecnico Incaricato</h4>
        <table>
            <tr><th>Nome</th><td><?php echo htmlspecialchars($dettaglio_richiesta['nome_tecnico']); ?></td></tr>
            <tr><th>Email</th><td><?php echo htmlspecialchars($dettaglio_richiesta['email_tecnico']); ?></td></tr>
        </table>
    <?php else: ?>
        <p>Nessun tecnico attualmente assegnato a questa richiesta.</p>
    <?php endif; ?>

    <?php if (!empty($valori_richiesta)): ?>
        <h4>Caratteristiche Richieste</h4>
        <table>
            <thead><tr><th>Caratteristica</th><th>Valore Richiesto</th><th>Indifferente</th></tr></thead>
            <tbody>
                <?php foreach ($valori_richiesta as $val): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($val['nome_caratteristica']); ?></td>
                        <td><?php echo $val['indifferente'] ? '<em>N/D</em>' : htmlspecialchars($val['valore']); ?></td>
                        <td><?php echo $val['indifferente'] ? 'Sì' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>Nessuna caratteristica specificata per questa richiesta.</p>
    <?php endif; ?>

    <?php if ($dettaglio_richiesta['id_cand']): ?>
        <h4>Prodotto Candidato</h4>
        <table>
            <tr><th>ID Candidato</th><td><?php echo htmlspecialchars($dettaglio_richiesta['id_cand']); ?></td></tr>
            <tr><th>Produttore</th><td><?php echo htmlspecialchars($dettaglio_richiesta['produttore']); ?></td></tr>
            <tr><th>Nome Prodotto</th><td><?php echo htmlspecialchars($dettaglio_richiesta['nome_prodotto']); ?></td></tr>
            <tr><th>Codice Prodotto</th><td><?php echo htmlspecialchars($dettaglio_richiesta['codice_prodotto']); ?></td></tr>
            <tr><th>Prezzo</th><td>€<?php echo htmlspecialchars(number_format($dettaglio_richiesta['prezzo'], 2, ',', '.')); ?></td></tr>
            <tr><th>URL</th><td><?php echo $dettaglio_richiesta['url'] ? '<a href="'.htmlspecialchars($dettaglio_richiesta['url']).'" target="_blank">'.htmlspecialchars($dettaglio_richiesta['url']).'</a>' : 'N/D'; ?></td></tr>
            <tr><th>Note Prodotto</th><td><?php echo nl2br(htmlspecialchars($dettaglio_richiesta['note_prodotto'])); ?></td></tr>
            <tr><th>Data Proposta</th><td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($dettaglio_richiesta['data_proposta']))); ?></td></tr>
            <tr><th>Esito Revisione</th><td style="font-weight:bold; color: <?php echo $dettaglio_richiesta['esito_revisione'] == 'approvato' ? 'green' : ($dettaglio_richiesta['esito_revisione'] == 'respinto' ? 'red' : 'orange'); ?>;">
                <?php echo htmlspecialchars(ucfirst($dettaglio_richiesta['esito_revisione'])); ?>
            </td></tr>
            <?php if ($dettaglio_richiesta['esito_revisione'] == 'respinto' && $dettaglio_richiesta['motivazione_rifiuto']): ?>
                <tr><th>Motivazione Rifiuto</th><td><?php echo nl2br(htmlspecialchars($dettaglio_richiesta['motivazione_rifiuto'])); ?></td></tr>
            <?php endif; ?>
            <?php if ($dettaglio_richiesta['data_ordine']): ?>
                <tr><th>Data Ordine</th><td><?php echo htmlspecialchars(date('d/m/Y H:i:s', strtotime($dettaglio_richiesta['data_ordine']))); ?></td></tr>
            <?php else: ?>
                 <tr><th>Data Ordine</th><td><em>Non ancora ordinato</em></td></tr>
            <?php endif; ?>
        </table>
    <?php else: ?>
        <p>Nessun prodotto candidato è stato ancora proposto per questa richiesta.</p>
    <?php endif; ?>

<?php elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_dettaglio_richiesta']) && !$error_msg && !$dettaglio_richiesta): ?>
     <p>Seleziona una richiesta per visualizzarne i dettagli.</p>
<?php endif; ?>

<a href="../index.php" class="back-link">Torna al menu principale</a>