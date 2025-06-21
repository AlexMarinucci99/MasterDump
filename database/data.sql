-- ---------------------------------------------------------------------------------
-- File:          data.sql
-- Descrizione:   Popolamento database con dati di test per facilitare la correzione
-- Data:          22/06/2025
-- Autori:        MasterDump
-- ---------------------------------------------------------------------------------

USE market;

-- Disabilita safe update mode temporaneamente
SET SQL_SAFE_UPDATES = 0;

-- Pulizia dati esistenti (per test ripetibili)
SET FOREIGN_KEY_CHECKS = 0;
DELETE FROM ProdottoCandidato;
DELETE FROM Possiede;
DELETE FROM Caratteristica;
DELETE FROM Categoria;
DELETE FROM Utente;
SET FOREIGN_KEY_CHECKS = 1;

-- Reset AUTO_INCREMENT
ALTER TABLE Utente AUTO_INCREMENT = 1;
ALTER TABLE Categoria AUTO_INCREMENT = 1;
ALTER TABLE Caratteristica AUTO_INCREMENT = 1;
ALTER TABLE ProdottoCandidato AUTO_INCREMENT = 1;

-- ===================
-- INSERIMENTO UTENTI
-- ===================

-- 1 Admin
INSERT INTO Utente (nome, cognome, email, password, tipo) VALUES
('Mario', 'Rossi', 'admin@masterdump.com', '$2y$10$3ZWg46nJrELzKdJWJmdFduwF1ga5hX4/laba/59TaEL.nv7hdKhSG', 'admin');
-- Password: Password123

-- 2 Tecnici
INSERT INTO Utente (nome, cognome, email, password, tipo) VALUES
('Luca', 'Bianchi', 'luca.bianchi@masterdump.com', '$2y$10$3ZWg46nJrELzKdJWJmdFduwF1ga5hX4/laba/59TaEL.nv7hdKhSG', 'tecnico'),
('Sara', 'Verdi', 'sara.verdi@masterdump.com', '$2y$10$3ZWg46nJrELzKdJWJmdFduwF1ga5hX4/laba/59TaEL.nv7hdKhSG', 'tecnico');
-- Password per entrambi: Password123

-- 2 Ordinanti
INSERT INTO Utente (nome, cognome, email, password, tipo) VALUES
('Anna', 'Neri', 'anna.neri@masterdump.com', '$2y$10$3ZWg46nJrELzKdJWJmdFduwF1ga5hX4/laba/59TaEL.nv7hdKhSG', 'ordinante'),
('Marco', 'Gialli', 'marco.gialli@masterdump.com', '$2y$10$3ZWg46nJrELzKdJWJmdFduwF1ga5hX4/laba/59TaEL.nv7hdKhSG', 'ordinante');
-- Password per entrambi: Password123

-- ======================
-- INSERIMENTO CATEGORIE
-- ======================

-- Categorie principali
INSERT INTO Categoria (nome, id_padre) VALUES
('Informatica', NULL),
('Ufficio', NULL),
('Elettronica', NULL);

-- Sottocategorie
INSERT INTO Categoria (nome, id_padre) VALUES
('Computer', 1),
('Periferiche', 1),
('Mobili', 2),
('Cancelleria', 2),
('Audio/Video', 3),
('Smartphone', 3);

-- Sotto-sottocategorie
INSERT INTO Categoria (nome, id_padre) VALUES
('Notebook', 4),
('Desktop', 4),
('Monitor', 5),
('Tastiere', 5),
('Mouse', 5);

-- ============================
-- INSERIMENTO CARATTERISTICHE
-- ============================

INSERT INTO Caratteristica (nome, tipo_valore) VALUES
('RAM', 'numerico'),
('Storage', 'numerico'),
('Processore', 'testuale'),
('Scheda Grafica', 'testuale'),
('Dimensioni Schermo', 'numerico'),
('Risoluzione', 'testuale'),
('Colore', 'testuale'),
('Peso', 'numerico'),
('Prezzo Massimo', 'numerico'),
('Sistema Operativo', 'testuale');

-- ======================================
-- ASSOCIAZIONI CATEGORIA-CARATTERISTICA
-- ======================================

-- Notebook (id_categoria = 10)
INSERT INTO Possiede (id_categoria, id_caratt) VALUES
(10, 1), -- RAM
(10, 2), -- Storage
(10, 3), -- Processore
(10, 4), -- Scheda Grafica
(10, 5), -- Dimensioni Schermo
(10, 6), -- Risoluzione
(10, 8), -- Peso
(10, 9), -- Prezzo Massimo
(10, 10); -- Sistema Operativo

-- Desktop (id_categoria = 11)
INSERT INTO Possiede (id_categoria, id_caratt) VALUES
(11, 1), -- RAM
(11, 2), -- Storage
(11, 3), -- Processore
(11, 4), -- Scheda Grafica
(11, 9), -- Prezzo Massimo
(11, 10); -- Sistema Operativo

-- Monitor (id_categoria = 12)
INSERT INTO Possiede (id_categoria, id_caratt) VALUES
(12, 5), -- Dimensioni Schermo
(12, 6), -- Risoluzione
(12, 7), -- Colore
(12, 9); -- Prezzo Massimo

-- NOTA: I prodotti candidati non vengono inseriti nel popolamento base
-- perché richiedono un id_richiesta valido (NOT NULL nello schema).
-- I prodotti candidati dovranno essere creati dopo che ci sarà una richiesta
-- di acquisto valida in modo da essere utilizzabile come campo del prodotto candidato.

SELECT 'POPOLAMENTO COMPLETATO CON SUCCESSO!' as Messaggio;

-- Riabilita safe update mode
SET SQL_SAFE_UPDATES = 1;