<?php
session_start(); // Start the session first

// Controlla se l'utente è loggato, altrimenti reindirizza alla pagina di login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db_connection.php'; // Include the database connection

// Recupera informazioni utente dalla sessione
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_name = $_SESSION['user_name'];

// Mappatura delle operazioni ai ruoli autorizzati
// Ordinante: 1, 3, 5, 8, 10, 12
// Tecnico: 4, 6, 7, 9, 11
// Admin: solo operazione 2 (Associazione Richiesta a Tecnico) e registrazione tecnici
$operations_permissions = [
    1 => ['ordinante'], // Inserimento Richiesta Acquisto
    2 => ['admin'],     // Associazione Richiesta a Tecnico (solo admin)
    3 => ['ordinante'], // Approvazione Prodotto Candidato
    4 => ['tecnico'],   // Eliminazione Richiesta Acquisto
    5 => ['ordinante'], // Lista Richieste Ordinante
    6 => ['tecnico'],   // Lista Richieste Non Assegnate
    7 => ['tecnico'],   // Lista Richieste Di Un Dato Tecnico
    8 => ['ordinante'], // Dettaglio Completo Richiesta (per ordinante)
    // L'operazione 8 era anche per tecnico, ma la nuova specifica la assegna solo a ordinante.
    // Se necessario, si può duplicare o creare una op8_tecnico.php specifica.
    // Per ora, seguo la specifica: op 8 solo per ordinante.
    9 => ['tecnico'],   // Conteggio Richieste Di Un Dato Tecnico
    10 => ['ordinante'],// Calcolo Spesa Totale Ordinante
    11 => ['tecnico'],  // Calcolo Tempo Medio Evasione Ordini
    12 => ['ordinante'],// Chiusura Richiesta Acquisto
];

// Funzione per verificare i permessi
function has_permission($op_num, $user_type, $permissions_map) {
    if (isset($permissions_map[$op_num])) {
        return in_array($user_type, $permissions_map[$op_num]);
    }
    return false;
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master's Market</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="main-header-outside">
        <h1>Master's Market</h1>
    </header>
    <div class="container">
        <div class="user-info" style="text-align: right; margin-bottom: 20px;">
            <span>Benvenuto, <strong><?php echo htmlspecialchars($user_name); ?></strong> (<u><?php echo htmlspecialchars($user_type); ?></u>)</span>
            <a href="logout.php">Logout</a>
        </div>
        <nav>
            <ul>
                <?php
                $admin_restricted_ops = [2]; // Operazione 2: Associazione Richiesta a Tecnico

                // Definizioni dei nomi delle operazioni per compattezza
                $op_names = [
                    1 => "Inserimento Richiesta Acquisto",
                    2 => "Associazione Richiesta a Tecnico",
                    3 => "Approvazione Prodotto Candidato",
                    4 => "Eliminazione Richiesta Acquisto",
                    5 => "Lista Richieste Ordinante",
                    6 => "Lista Richieste Non Assegnate",
                    7 => "Lista Richieste Di Un Dato Tecnico",
                    8 => "Dettaglio Completo Richiesta",
                    9 => "Conteggio Richieste Di Un Dato Tecnico",
                    10 => "Calcolo Spesa Totale Ordinante",
                    11 => "Calcolo Tempo Medio Evasione Ordini",
                    12 => "Chiusura Richiesta Acquisto"
                ];

                if ($user_type === 'admin') {
                    echo "<p style='font-size: 1.1em; font-weight: bold;'>Pannello Amministratore:</p>";
                    // Link diretto per la registrazione utenti tecnici
                    echo "<li><a href='register.php'>Registrazione Utente Tecnico</a></li>";

                    // Mostra solo le operazioni consentite definite in $admin_restricted_ops
                    foreach ($op_names as $op_num => $op_name) {
                        if (in_array($op_num, $admin_restricted_ops)) {
                            // Verifica comunque il permesso, anche se dovrebbe sempre averlo per queste operazioni
                            if (has_permission($op_num, $user_type, $operations_permissions)) {
                                echo "<li><a href='index.php?op=$op_num'>$op_name</a></li>";
                            }
                        }
                    }
                } else { // Per utenti non admin (ordinante, tecnico)
                    echo "<p style='font-size: 1.1em; font-weight: bold;'>Operazioni Disponibili:</p>";
                    foreach ($op_names as $op_num => $op_name) {
                        if (has_permission($op_num, $user_type, $operations_permissions)) {
                            echo "<li><a href='index.php?op=$op_num'>$op_name</a></li>";
                        }
                    }
                }
                ?>
            </ul>
        </nav>

        <hr>

        <main class="operation-content">
        <?php
        // Gestione delle operazioni
        if (isset($_GET['op'])) {
            $op = $_GET['op'];
            $page_title = "";
            $operation_file = "";

            switch ($op) {
                case 1:
                    $page_title = "Inserimento Richiesta Acquisto";
                    $operation_file = "operations/op1_inserimento_richiesta.php";
                    break;
                case 2:
                    $page_title = "Associazione Richiesta a Tecnico";
                    $operation_file = "operations/op2_associazione_tecnico.php";
                    break;
                case 3:
                    $page_title = "Approvazione Prodotto Candidato";
                    $operation_file = "operations/op3_approvazione_prodotto.php";
                    break;
                case 4:
                    $page_title = "Eliminazione Richiesta Acquisto";
                    $operation_file = "operations/op4_eliminazione_richiesta.php";
                    break;
                case 5:
                    $page_title = "Lista Richieste Ordinante (in corso, candidato in attesa)";
                    $operation_file = "operations/op5_lista_richieste_ordinante.php";
                    break;
                case 6:
                    $page_title = "Lista Richieste Non Assegnate";
                    $operation_file = "operations/op6_lista_richieste_non_assegnate.php";
                    break;
                case 7:
                    $page_title = "Lista Richieste Tecnico (prodotto approvato, non ordinato)";
                    $operation_file = "operations/op7_lista_richieste_tecnico.php";
                    break;
                case 8:
                    $page_title = "Dettaglio Completo Richiesta";
                    $operation_file = "operations/op8_dettaglio_richiesta.php";
                    break;
                case 9:
                    $page_title = "Conteggio Richieste Tecnico";
                    $operation_file = "operations/op9_conteggio_richieste_tecnico.php";
                    break;
                case 10:
                    $page_title = "Calcolo Spesa Totale Ordinante (Annuale)";
                    $operation_file = "operations/op10_calcolo_spesa_ordinante.php";
                    break;
                case 11:
                    $page_title = "Calcolo Tempo Medio Evasione Ordini";
                    $operation_file = "operations/op11_tempo_medio_evasione.php";
                    break;
                case 12:
                    $page_title = "Chiusura Richiesta Acquisto";
                    $operation_file = "operations/op12_chiusura_richiesta.php";
                    break;
                default:
                    echo "<p class='error'>Operazione non valida.</p>";
                    break;
            }

            if (!empty($operation_file)) {
                if (has_permission(intval($op), $user_type, $operations_permissions)) {
                    echo "<h2>".htmlspecialchars($page_title)."</h2>";
                    if (file_exists($operation_file)) {
                        // Rendi disponibili le variabili di sessione e connessione allo script incluso
                        // Non è strettamente necessario passare $conn se ogni script lo include già
                        include $operation_file;
                    } else {
                        echo "<p class='error'>File dell'operazione non trovato: ".htmlspecialchars($operation_file).". Crealo per implementare questa funzionalità.</p>";
                    }
                } else {
                    echo "<h2>Accesso Negato</h2>";
                    echo "<p class='error'>Non hai i permessi necessari per visualizzare questa operazione.</p>";
                }
            } elseif (isset($op)) { // Se $op è settato ma $operation_file è vuoto (default case nello switch)
                 echo "<p class='error'>Operazione non valida.</p>";
            }
        } else {
            echo "<p>Benvenuto nel sistema di gestione Market. Seleziona un'operazione dal menu.</p>";
        }
        ?>
        </main>
    </div> <!-- Chiusura .container -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Master's Market. Tutti i diritti riservati.</p>
    </footer>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close(); // Close the database connection if it was opened
}
?>