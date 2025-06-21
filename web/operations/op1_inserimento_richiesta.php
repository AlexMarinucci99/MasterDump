<?php
// op1_inserimento_richiesta.php

// Assicurati che la sessione sia avviata (necessario solo se questo file viene chiamato direttamente)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verifica se l'utente è loggato e ha i permessi
// Questa verifica è già in index.php, ma è buona norma averla anche qui per accesso diretto (sebbene sconsigliato)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    echo "<p class='error'>Accesso negato. Effettua il login.</p>";
    exit;
}

// Verifica permessi specifici per questa operazione (da index.php)
// $user_type e $operations_permissions dovrebbero essere disponibili se incluso da index.php
// Se questo file viene chiamato direttamente, queste variabili non esisteranno.
// Per robustezza, potremmo ridefinire $operations_permissions qui o includere un file di configurazione.
// Per ora, assumiamo che sia incluso da index.php dove $user_type è definito.

$current_user_id = $_SESSION['user_id'];
$current_user_type = $_SESSION['user_type'];

// Permessi per op1: 'ordinante', 'admin'
if ($current_user_type !== 'ordinante' && $current_user_type !== 'admin') {
    echo "<p class='error'>Non hai i permessi per eseguire questa operazione.</p>";
    // Potresti voler chiudere la connessione al DB se è stata aperta
    // if(isset($conn)) $conn->close();
    exit;
}

if (!isset($conn)) {
    require_once '../db_connection.php';
}

$message = '';
$error = '';

// Fetch categories for the dropdown (parent and child)
$categories_flat = [];
$sql_categories = "SELECT id_categoria, nome, id_padre FROM Categoria ORDER BY COALESCE(id_padre, id_categoria), id_padre IS NOT NULL, nome ASC";
$result_categories = $conn->query($sql_categories);
$hierarchical_categories = [];
if ($result_categories && $result_categories->num_rows > 0) {
    $temp_categories = [];
    while ($row = $result_categories->fetch_assoc()) {
        $categories_flat[] = $row; // Keep a flat list for JavaScript if needed
        $temp_categories[$row['id_categoria']] = $row;
    }

    // Costruisci la gerarchia: prima mappa tutti gli elementi per ID
    $categories_by_id = [];
    foreach ($temp_categories as $id => $category) {
        $category['children'] = []; // Inizializza l'array dei figli
        $categories_by_id[$id] = $category;
    }

    // Poi, assegna i figli ai rispettivi padri
    foreach ($categories_by_id as $id => &$category_ref) { // Usa il riferimento per modificare l'array originale
        if (!is_null($category_ref['id_padre']) && isset($categories_by_id[$category_ref['id_padre']])) {
            $categories_by_id[$category_ref['id_padre']]['children'][] = &$category_ref;
        }
    }
    unset($category_ref); // Rimuovi il riferimento per sicurezza

    // Infine, estrai solo le categorie principali (quelle senza padre)
    foreach ($categories_by_id as $id => $category) {
        if (is_null($category['id_padre'])) {
            $hierarchical_categories[$id] = $category;
        }
    }

}
$categories_for_js = $hierarchical_categories; // Use this for JS


// Fetch users (ordinanti) for the dropdown - solo se l'utente è admin
$ordinanti = [];
if ($current_user_type === 'admin') {
    $sql_ordinanti = "SELECT id_utente, CONCAT(nome, ' ', cognome) AS nome_completo FROM Utente WHERE tipo = 'ordinante' ORDER BY nome_completo ASC";
    $result_ordinanti = $conn->query($sql_ordinanti);
    if ($result_ordinanti && $result_ordinanti->num_rows > 0) {
        while ($row = $result_ordinanti->fetch_assoc()) {
            $ordinanti[] = $row;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_richiesta'])) {
    $id_categoria = isset($_POST['id_categoria']) ? (int)$_POST['id_categoria'] : null;
    $note_generali = isset($_POST['note_generali']) ? trim($_POST['note_generali']) : null;
    
    // Se l'utente è 'ordinante', l'ID ordinante è il suo. Se è 'admin', lo prende dal form.
    if ($current_user_type === 'ordinante') {
        $id_ordinante = $current_user_id;
    } elseif ($current_user_type === 'admin') {
        $id_ordinante = isset($_POST['id_ordinante']) ? (int)$_POST['id_ordinante'] : null;
    } else {
        $id_ordinante = null; // Non dovrebbe succedere a causa del controllo permessi sopra
    }

    $caratteristiche_valori = isset($_POST['caratteristiche']) ? $_POST['caratteristiche'] : [];

    if (empty($id_categoria) || empty($id_ordinante)) {
        $error = "Categoria e Ordinante (se admin) sono obbligatori.";
    } else {
        $conn->begin_transaction();
        try {
            // 1. Inserimento RichiestaAcquisto
            $stmt_richiesta = $conn->prepare("INSERT INTO RichiestaAcquisto(id_categoria, data_inserimento, note_generali) VALUES(?, NOW(), ?)");
            if (!$stmt_richiesta) {
                throw new Exception("Errore preparazione statement RichiestaAcquisto: " . $conn->error);
            }
            $stmt_richiesta->bind_param("is", $id_categoria, $note_generali);
            if (!$stmt_richiesta->execute()) {
                throw new Exception("Errore esecuzione statement RichiestaAcquisto: " . $stmt_richiesta->error);
            }
            $id_richiesta_creata = $stmt_richiesta->insert_id;
            $stmt_richiesta->close();

            // 2. Registra l'ordinante in Partecipazione
            $stmt_partecipazione = $conn->prepare("INSERT INTO Partecipazione(id_richiesta, id_utente, ruolo) VALUES(?, ?, 'ordinante')");
            if (!$stmt_partecipazione) {
                throw new Exception("Errore preparazione statement Partecipazione: " . $conn->error);
            }
            $stmt_partecipazione->bind_param("ii", $id_richiesta_creata, $id_ordinante);
            if (!$stmt_partecipazione->execute()) {
                throw new Exception("Errore esecuzione statement Partecipazione: " . $stmt_partecipazione->error);
            }
            $stmt_partecipazione->close();

            // 3. Inserimento dei valori per ciascuna caratteristica
            if (!empty($caratteristiche_valori)) {
                $stmt_valore = $conn->prepare("INSERT INTO ValoreRichiesta(id_richiesta, id_caratt, valore, indifferente) VALUES(?, ?, ?, ?)");
                if (!$stmt_valore) {
                    throw new Exception("Errore preparazione statement ValoreRichiesta: " . $conn->error);
                }
                foreach ($caratteristiche_valori as $id_caratt => $data) {
                    // Controlla se 'valore' esiste prima di accedervi per evitare warning
                    $valore_input = isset($data['valore']) ? trim($data['valore']) : '';
                    $indifferente = isset($data['indifferente']) ? 1 : 0;

                    // Se è indifferente, il valore da inserire è una stringa vuota.
                    // Altrimenti, è il valore fornito dall'utente.
                    // Si inserisce un record solo se è indifferente OPPURE se è stato fornito un valore non vuoto.
                    if ($indifferente || !empty($valore_input)) {
                        $val_insert = $indifferente ? '' : $valore_input;
                        $stmt_valore->bind_param("iisi", $id_richiesta_creata, $id_caratt, $val_insert, $indifferente);
                        if (!$stmt_valore->execute()) {
                            throw new Exception("Errore esecuzione statement ValoreRichiesta per caratteristica ID $id_caratt: " . $stmt_valore->error);
                        }
                    } else if (!$indifferente && empty($valore_input)){
                        // Se non è indifferente e il valore è vuoto, potremmo voler inserire comunque un record
                        // con valore vuoto e indifferente = 0, oppure saltare. 
                        // Per ora, se non è indifferente e il valore è vuoto, non inseriamo nulla.
                        // Questo comportamento potrebbe essere rivisto se necessario.
                    }
                }
                $stmt_valore->close();
            }

            $conn->commit();
            $message = "Richiesta di acquisto (ID: $id_richiesta_creata) inserita con successo!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Errore durante l'inserimento della richiesta: " . $e->getMessage();
        }
    }
}

?>

<div class="operation-description">
    <p>Questa sezione permette di inserire una nuova richiesta di acquisto nel sistema.</p>
    <p>È necessario selezionare una categoria di prodotto, specificare le caratteristiche richieste (o marcarle come indifferenti) e indicare l'ordinante.</p>
</div>

<?php if ($message): ?>
    <p class="success"><?php echo htmlspecialchars($message); ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error"><?php echo htmlspecialchars($error); ?></p>
<?php endif; ?>

<form method="POST" action="index.php?op=1" id="formRichiestaAcquisto">
    <?php if ($current_user_type === 'admin'): ?>
        <label for="id_ordinante">Ordinante (seleziona se Admin):</label>
        <select name="id_ordinante" id="id_ordinante" required>
            <option value="">Seleziona un ordinante...</option>
            <?php foreach ($ordinanti as $ordinante_item): ?>
                <option value="<?php echo htmlspecialchars($ordinante_item['id_utente']); ?>">
                    <?php echo htmlspecialchars($ordinante_item['nome_completo']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    <?php else: // L'utente è 'ordinante', l'ID è preimpostato ?>
        <input type="hidden" name="id_ordinante" value="<?php echo htmlspecialchars($current_user_id); ?>">
        <p>Stai inserendo una richiesta come: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></p>
    <?php endif; ?>

    <label for="id_categoria_padre">Categoria Principale:</label>
    <select name="id_categoria_padre" id="id_categoria_padre" required onchange="popolaSottoCategorie(this.value)">
        <option value="">Seleziona una categoria principale...</option>
        <?php foreach ($hierarchical_categories as $parent_id => $parent_category): ?>
            <option value="<?php echo htmlspecialchars($parent_id); ?>">
                <?php echo htmlspecialchars($parent_category['nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="id_categoria">Sotto-Categoria Prodotto:</label>
    <select name="id_categoria" id="id_categoria" required onchange="caricaCaratteristiche(this.value)" disabled>
        <option value="">Seleziona prima una categoria principale...</option>
        <!-- Le sotto-categorie verranno popolate da JavaScript -->
    </select>

    <div id="caratteristiche_container">
        <!-- Le caratteristiche verranno caricate qui tramite AJAX -->
    </div>

    <label for="note_generali">Note Generali:</label>
    <textarea name="note_generali" id="note_generali" rows="4"></textarea>

    <input type="submit" name="submit_richiesta" value="Inserisci Richiesta">
</form>

<script>
var tutteLeCategorieGerarchiche = <?php echo json_encode($categories_for_js); ?>;

function popolaSottoCategorie(idCategoriaPadre) {
    const selectSottoCategoria = document.getElementById('id_categoria');
    const containerCaratteristiche = document.getElementById('caratteristiche_container');
    selectSottoCategoria.innerHTML = '<option value="">Seleziona una sotto-categoria...</option>'; // Reset
    selectSottoCategoria.disabled = true;
    containerCaratteristiche.innerHTML = ''; // Pulisci caratteristiche

    function aggiungiOpzioniRicorsive(categoria, selectElement, prefisso = '') {
        // Aggiungi la categoria corrente se non è una categoria radice fittizia
        // o se è una categoria principale senza figli (per il caso speciale)
        if (categoria.id_categoria) { // Assicurati che ci sia un id_categoria
            const option = document.createElement('option');
            option.value = categoria.id_categoria;
            option.textContent = prefisso + categoria.nome;
            selectElement.appendChild(option);
        }

        // Se ha figli, itera ricorsivamente
        if (categoria.children && categoria.children.length > 0) {
            categoria.children.forEach(function(figlio) {
                aggiungiOpzioniRicorsive(figlio, selectElement, prefisso + categoria.nome + ' > ');
            });
        }
    }

    if (idCategoriaPadre && tutteLeCategorieGerarchiche[idCategoriaPadre]) {
        const parentCategory = tutteLeCategorieGerarchiche[idCategoriaPadre];
        
        // Se la categoria padre selezionata non ha figli, ma è una categoria valida,
        // permetti di selezionarla direttamente e carica le sue caratteristiche.
        if (!parentCategory.children || parentCategory.children.length === 0) {
            const option = document.createElement('option');
            option.value = parentCategory.id_categoria;
            option.textContent = parentCategory.nome + " (Nessuna sotto-categoria)";
            option.selected = true;
            selectSottoCategoria.appendChild(option);
            selectSottoCategoria.disabled = false;
            caricaCaratteristiche(parentCategory.id_categoria);
        } else {
            // Popola con i figli, inclusi i discendenti
            parentCategory.children.forEach(function(sottoCategoria) {
                aggiungiOpzioniRicorsive(sottoCategoria, selectSottoCategoria, ''); // Inizia senza prefisso per il primo livello di figli
            });
            selectSottoCategoria.disabled = false;
        }
    } else {
        selectSottoCategoria.disabled = true;
        selectSottoCategoria.innerHTML = '<option value="">Seleziona prima una categoria principale valida...</option>';
    }
}

function caricaCaratteristiche(idCategoria) {
    const container = document.getElementById('caratteristiche_container');
    container.innerHTML = 'Caricamento caratteristiche...'; // Feedback per l'utente

    if (!idCategoria) {
        container.innerHTML = ''; // Pulisci se nessuna categoria è selezionata
        return;
    }

    // Chiamata AJAX per ottenere le caratteristiche della categoria selezionata
    fetch('ajax_get_caratteristiche.php?id_categoria=' + idCategoria)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                container.innerHTML = `<p class="error">Errore: ${data.error}</p>`;
                return;
            }
            if (data.length === 0) {
                container.innerHTML = '<p>Nessuna caratteristica specifica per questa categoria.</p>';
                return;
            }

            let htmlContent = '<h4>Caratteristiche Prodotto:</h4>';
            data.forEach(car => {
                const inputId = `caratteristica_${car.id_caratt}`;
                const checkboxId = `indifferente_${car.id_caratt}`;
                htmlContent += `
                    <div class="caratteristica-item">
                        <label for="${inputId}">${car.nome}:</label>
                        <input type="${car.tipo_valore === 'numerico' ? 'number' : 'text'}" 
                               name="caratteristiche[${car.id_caratt}][valore]" 
                               id="${inputId}">
                        <input type="checkbox" 
                               name="caratteristiche[${car.id_caratt}][indifferente]" 
                               id="${checkboxId}" 
                               value="1" 
                               onchange="toggleCaratteristicaInput('${inputId}', this.checked)">
                        <label for="${checkboxId}">Indifferente</label>
                    </div>
                `;
            });
            container.innerHTML = htmlContent;
        })
        .catch(error => {
            console.error('Errore nella chiamata AJAX:', error);
            container.innerHTML = '<p class="error">Impossibile caricare le caratteristiche. Riprova più tardi.</p>';
        });
}

function toggleCaratteristicaInput(inputId, isChecked) {
    const inputElement = document.getElementById(inputId);
    if (inputElement) {
        inputElement.disabled = isChecked;
        if (isChecked) {
            inputElement.value = ''; // Pulisci il valore se indifferente
        }
    }
}
</script>

<a href="../index.php" class="back-link">Torna al menu principale</a>