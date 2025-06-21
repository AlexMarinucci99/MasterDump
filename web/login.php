<?php
session_start();
require_once 'db_connection.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['email']) && isset($_POST['password'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        // Prepara la query per evitare SQL injection
        $stmt = $conn->prepare("SELECT id_utente, nome, cognome, tipo, password FROM Utente WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            // Verifica la password (assumendo che le password nel DB siano hashate con password_hash)
            // Se le password sono in chiaro (NON RACCOMANDATO), usa: if ($password === $user['password'])
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id_utente'];
                $_SESSION['user_type'] = $user['tipo'];
                $_SESSION['user_name'] = $user['nome'] . ' ' . $user['cognome'];
                header("Location: index.php"); // Reindirizza alla pagina principale dopo il login
                exit();
            } else {
                $error_message = "Password errata.";
            }
        } else {
            $error_message = "Utente non trovato.";
        }
        $stmt->close();
    } else {
        $error_message = "Email e password sono obbligatori.";
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Master's Market</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="main-header-outside">
        <h1>Master's Market</h1>
    </header>
    <div class="container login-container">
        <h2>Login</h2>
        <?php if (!empty($error_message)): ?>
            <p class="error"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">Accedi</button>
            </div>
        </form>
        <p>Non hai un account? <a href="register.php">Registrati qui</a>.</p>
    </div> <!-- Chiusura .container -->
    <footer>
        <p>&copy; <?php echo date("Y"); ?> Master's Market. Tutti i diritti riservati.</p>
    </footer>
</body>
</html>