<?php
// session_start(); // Start the session - Rimosso perché la sessione è già avviata da index.php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check user role
if ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'tecnico') {
    echo "<p class='error'>Accesso negato. Solo gli amministratori e i tecnici possono visualizzare il tempo medio di evasione.</p>";
    echo "<a href='../index.php' class='back-link'>Torna al menu principale</a>";
    exit;
}

// op11_tempo_medio_evasione.php
if (!isset($conn)) {
    include '../db_connection.php';
}

$tempo_medio_evasione = null;
$error_msg = '';
$debug_info = '';

// Rimozione del debug - non più necessario

// Calcolo preciso: differenza in secondi tra data_ordine del prodotto e data_inserimento della richiesta
$sql = "SELECT AVG(TIMESTAMPDIFF(SECOND, R.data_inserimento, PC.data_ordine)) AS tempo_medio_secondi ".
       "FROM RichiestaAcquisto R ".
       "JOIN ProdottoCandidato PC ON R.id_richiesta = PC.id_richiesta ".
       "JOIN Partecipazione P_tec ON R.id_richiesta = P_tec.id_richiesta AND P_tec.ruolo = 'tecnico' ".
       "WHERE PC.data_ordine IS NOT NULL ".
       "  AND PC.esito_revisione = 'approvato'";

$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $tempo_medio_evasione = $row['tempo_medio_secondi'];
        if ($tempo_medio_evasione === null) {
            $error_msg = "Nessun dato disponibile per calcolare il tempo medio di evasione. Assicurati che ci siano richieste con tecnico assegnato e prodotti candidati approvati e ordinati.";
        }
    } else {
        $error_msg = "Nessun dato disponibile per calcolare il tempo medio di evasione.";
    }
} else {
    $error_msg = "Errore nell'esecuzione della query: " . $conn->error;
}

?>

<?php if ($error_msg): ?>
    <p style="color: #ff6b6b;"><?php echo htmlspecialchars($error_msg); ?></p>
<?php endif; ?>

<?php if ($tempo_medio_evasione !== null && !$error_msg): ?>
    <?php
    // Conversione da secondi a giorni, ore, minuti, secondi
    $secondi_totali = round($tempo_medio_evasione);
    $giorni = floor($secondi_totali / 86400);
    $ore = floor(($secondi_totali % 86400) / 3600);
    $minuti = floor(($secondi_totali % 3600) / 60);
    $secondi = $secondi_totali % 60;
    ?>
    
    <h3>Tempo Medio di Evasione</h3>
    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
        <thead>
            <tr style="background-color: #2c3e50; color: white;">
                <th style="padding: 12px; text-align: left; border: 1px solid #34495e;">Unità</th>
                <th style="padding: 12px; text-align: center; border: 1px solid #34495e;">Valore</th>
            </tr>
        </thead>
        <tbody>
            <tr style="background-color: #34495e;">
                <td style="padding: 10px; border: 1px solid #2c3e50; color: #ecf0f1;">Giorni</td>
                <td style="padding: 10px; border: 1px solid #2c3e50; text-align: center; color: #e74c3c; font-weight: bold; font-size: 18px;"><?php echo $giorni; ?></td>
            </tr>
            <tr style="background-color: #2c3e50;">
                <td style="padding: 10px; border: 1px solid #34495e; color: #ecf0f1;">Ore</td>
                <td style="padding: 10px; border: 1px solid #34495e; text-align: center; color: #f39c12; font-weight: bold; font-size: 18px;"><?php echo $ore; ?></td>
            </tr>
            <tr style="background-color: #34495e;">
                <td style="padding: 10px; border: 1px solid #2c3e50; color: #ecf0f1;">Minuti</td>
                <td style="padding: 10px; border: 1px solid #2c3e50; text-align: center; color: #3498db; font-weight: bold; font-size: 18px;"><?php echo $minuti; ?></td>
            </tr>
            <tr style="background-color: #2c3e50;">
                <td style="padding: 10px; border: 1px solid #34495e; color: #ecf0f1;">Secondi</td>
                <td style="padding: 10px; border: 1px solid #34495e; text-align: center; color: #2ecc71; font-weight: bold; font-size: 18px;"><?php echo $secondi; ?></td>
            </tr>
        </tbody>
    </table>
    
<?php elseif ($tempo_medio_evasione === null && !$error_msg): ?>
    <p>Non ci sono dati sufficienti per calcolare il tempo medio di evasione.</p>
<?php endif; ?>