CREATE DATABASE MasterDumpMarket;
USE MasterDumpMarket;
CREATE TABLE Utenti (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    ruolo ENUM('ordinante', 'tecnico', 'amministratore') NOT NULL,
    password VARCHAR(255) NOT NULL
);