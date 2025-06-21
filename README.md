# MasterDump - Market

Benvenuto nel progetto **MasterDump - Market**, un database progettato per supportare un sistema di acquisti online guidato e controllato, ispirato ai classici eCommerce, ma pensato per l’utilizzo in contesti pubblici o istituzionali.

## 📋 Caratteristiche Principali

-  **Gestione Utenti**: Sistema multi-ruolo (Admin, Ordinante, Tecnico)

-  **Gestione Richieste**: Inserimento, modifica, eliminazione richieste

-  **Workflow Approvazione**: Sistema di approvazione prodotti

-  **Assegnazione Tecnici**: Gestione automatica e manuale delle assegnazioni

-  **Reporting**: Statistiche e report su richieste e performance

-  **Gestione Categorie**: Sistema gerarchico di categorizzazione prodotti  



## 🛠️ Requisiti di Sistema

-  **Database**: MySQL 8.0+ (gestito tramite MySQL Workbench)

-  **Web Server**: Apache tramite XAMPP  

-  **PHP**: 8.0+

-  **Browser**: Chrome, Firefox, Safari, Edge (versioni recenti)

-  **Strumenti di Sviluppo**: MySQL Workbench per gestione database

  

## 🗄️ Configurazione Database

### 1. Creazione Database

1.  **Avviare il servizio MySQL aprendo il prompt dei comandi** (come amministratore):

```cmd
net start MySQL80
```

2.  **Aprire MySQL Workbench**  

3.  **Connettersi al server MySQL locale** (127.0.0.1:3306)

4.  **Eseguire lo script di creazione**:  

```sql
source database/market_schema.sql
```

5.  **Verificare la creazione** delle tabelle e dei dati di test

> **Nota**: Il database è stato creato direttamente tramite MySQL Workbench. MySQL viene avviato tramite comando `net start MySQL80`, non tramite XAMPP Control Panel.



## 🌐 Configurazione Web  

### 1. Installazione

1.  **Installa XAMPP** se non già presente

2.  **Copia i file** nella directory htdocs di XAMPP:

-  **Windows**: `C:\xampp\htdocs\MasterDump\`

-  **Linux**: `/opt/lampp/htdocs/MasterDump/`

-  **macOS**: `/Applications/XAMPP/htdocs/MasterDump/`

### 2. Configurazione Apache

1.  **Avvia XAMPP Control Panel**

2.  **Avvia i servizi**:

- Clicca "Start" per Apache  

3.  **Verifica i servizi**:

- Apache dovrebbe mostrare "Running" in verde  

4. **Testa la connessione** aprendo: `http://localhost/MasterDump/web/`

### 3. Configurazione Connessione Database

Il file `web/db_connection.php` contiene i parametri di connessione. L'indirizzo `127.0.0.1` corrisponde alla connessione MySQL utilizzata:

```php
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "market";
```

> **Importante**: Assicurarsi che il servizio MySQL sia avviato con `net start MySQL80` prima di utilizzare l'applicazione.

**Adatta i parametri** al tuo ambiente:

- Cambia `password` con la tua password MySQL
- Modifica `username` se usi un utente diverso da `root`

  
  
## ⚠️ Nota sul file hash.php

Il file `utils/hash.php` serve **esclusivamente** per registrare utenti ADMIN nel database:

1. Accedere a `utils/hash.php` dal browser  

2. Inserire la password in chiaro nel form

3. Copiare l'hash generato

4. Inserire manualmente l'utente ADMIN nel database con la password hashata

**Nota**: Questo strumento è necessario perché gli utenti ADMIN sono gli unici che non hanno la possibilità di registrarsi dal sito web e devono essere inseriti direttamente nel database.



## 🔒 Sicurezza

- Le password sono hashate con algoritmi sicuri

- Protezione contro SQL injection tramite prepared statements

- Validazione input lato server

- Gestione sessioni sicura



---



_Progetto realizzato per il corso di **Laboratorio di Basi di Dati**, a cura di:_
| Nome | Cognome | Matricola |
|:-:|:-:|:-:|
| Davide | Odoardi | 292216 |
| Alessandro | Marinucci | 261682 |
| Mattia | Ramondo | 291659 |
