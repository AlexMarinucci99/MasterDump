<?php
session_start(); // Necessario per accedere a $_SESSION['user_type']
require_once 'db_connection.php';

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nome = $_POST['nome'] ?? '';
    $cognome = $_POST['cognome'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $tipo_utente = $_POST['tipo_utente'] ?? 'ordinante'; // Default a ordinante

    if (empty($nome) || empty($cognome) || empty($email) || empty($password)) {
        $error_message = "Tutti i campi sono obbligatori.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Formato email non valido.";
    } elseif (strlen($password) < 8) {
        $error_message = "La password deve essere lunga almeno 8 caratteri.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error_message = "La password deve contenere almeno una lettera maiuscola.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error_message = "La password deve contenere almeno un numero.";
    } elseif (preg_match('/\s/', $password)) {
        $error_message = "La password non deve contenere spazi.";
    } else {
        // Controlla se l'email esiste già
        $stmt_check = $conn->prepare("SELECT id_utente FROM Utente WHERE email = ?");
        $stmt_check->bind_param("s", $email);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            $error_message = "Questa email è già registrata.";
        } else {
            // Hash della password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt_insert = $conn->prepare("INSERT INTO Utente (nome, cognome, email, password, tipo) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert->bind_param("sssss", $nome, $cognome, $email, $hashed_password, $tipo_utente);

            if ($stmt_insert->execute()) {
                if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
                    $success_message = "Registratazione del tecnico completata con successo";
                } else {
                    $success_message = "Registrazione completata con successo! Ora puoi effettuare il <a href='login.php'>login</a>.";
                }
            } else {
                $error_message = "Errore durante la registrazione: " . $conn->error;
            }
            $stmt_insert->close();
        }
        $stmt_check->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrazione - Master's Market</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="main-header-outside">
        <h1>Master's Market</h1>
    </header>
    <div class="container register-container">
        <h2><?php echo (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') ? 'Registrazione Tecnici' : 'Registrazione Utente'; ?></h2>
        <?php if (!empty($success_message)): ?>
            <p class="success"><?php echo $success_message; ?></p>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        
        <?php if (empty($success_message)): // Mostra il form solo se non c'è un messaggio di successo ?>
        <form action="register.php" method="POST">
            <div>
                <label for="nome">Nome:</label>
                <input type="text" id="nome" name="nome" required>
            </div>
            <div>
                <label for="cognome">Cognome:</label>
                <input type="text" id="cognome" name="cognome" required>
            </div>
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required title="La password deve essere lunga almeno 8 caratteri, contenere almeno una lettera maiuscola, un numero e nessuno spazio.">
            </div>
            <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') : ?>
                <input type="hidden" name="tipo_utente" value="tecnico">
            <?php else : ?>
                <input type="hidden" name="tipo_utente" value="ordinante">
            <?php endif; ?>
            <div>
                <button type="submit">Registrati</button>
            </div>
        </form>
        <?php endif; ?>
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin'): ?>
            <div class="back-button" style="margin-top:20px;">
                <button type="button" onclick="window.history.back()">Torna indietro</button>
            </div>
        <?php else: ?>
            <p>Hai già un account? <a href="login.php">Accedi qui</a>.</p>
        <?php endif; ?>
    </div> <!-- Chiusura .container -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Master's Market. Tutti i diritti riservati.</p>
    </footer>
</body>
</html>
