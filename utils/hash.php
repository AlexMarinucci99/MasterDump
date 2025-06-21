<?php
$password_originale = '';
$hashed_password = '';
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['password_input'])) {
        $error_message = "Per favore, inserisci una password.";
    } else {
        $password_originale = $_POST['password_input'];

        // Validazione della password
        $error_messages_arr = [];
        if (strlen($password_originale) < 8) {
            $error_messages_arr[] = "La password deve contenere almeno 8 caratteri.";
        }
        if (!preg_match('/[A-Z]/', $password_originale)) {
            $error_messages_arr[] = "La password deve contenere almeno una lettera maiuscola.";
        }
        if (!preg_match('/[0-9]/', $password_originale)) {
            $error_messages_arr[] = "La password deve contenere almeno un numero.";
        }
        if (preg_match('/\s/', $password_originale)) {
            $error_messages_arr[] = "La password non deve contenere spazi.";
        }

        if (empty($error_messages_arr)) {
            $is_valid = true;
        } else {
            $error_message = implode("<br>", $error_messages_arr);
            $is_valid = false;
        }

        if ($is_valid) {
            // Genera l'hash della password usando l'algoritmo predefinito (BCRYPT)
            $hashed_password = password_hash($password_originale, PASSWORD_DEFAULT);

            if ($hashed_password === false) {
                $error_message = "Errore durante la generazione dell'hash della password.";
            } else {
                $success_message = "Password originale: <strong>" . htmlspecialchars($password_originale) . "</strong><br>";
                $success_message .= "Password hashata: <strong>" . htmlspecialchars($hashed_password) . "</strong><br><br>";
                $success_message .= "Copia questa password hashata e inseriscila nel campo <strong>password</strong> nella tabella <strong>Utente</strong> del database per l'utente desiderato.";
            }
        } // Se non è valida, $error_message è già stato impostato dai controlli precedenti
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generatore Hash Password - Master's Market</title>
    <link rel="stylesheet" href="../web/style.css">
</head>
<body>
    <header class="main-header-outside">
        <h1>Generatore di Hash Password</h1>
    </header>
    <div class="container hash-container">
        <p>Inserisci la password desiderata nel campo sottostante e clicca su "Genera Hash".<br>
        La password deve rispettare i seguenti vincoli: almeno 8 caratteri, almeno una lettera maiuscola, almeno un numero, nessuno spazio.</p>

        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <p class="success-message"><?php echo $success_message; ?></p>
        <?php endif; ?>

        <form action="hash.php" method="POST">
            <div>
                <label for="password_input">Password da Hashare:</label>
                <input type="text" id="password_input" name="password_input" required>
            </div>
            <div>
                <button type="submit">Genera Hash</button>
            </div>
        </form>
        
        <p class="align-right-link"><a href="../web/index.php">Torna alla Home</a></p>
    </div>
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Master's Market. Tutti i diritti riservati.</p>
    </footer>
</body>
</html>