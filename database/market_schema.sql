-- ------------------------------------------------------------
-- File:          market_schema.sql
-- Descrizione:   DDL per creazione tabelle del database Market
-- Data inizio:   21/05/2025
-- Data fine:     gg/mm/2025
-- Autori:        MasterDump
-- ------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS market CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE market;

-- 1) Utente
CREATE TABLE Utente (
  id_utente    INT AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(100) NOT NULL,
  cognome      VARCHAR(100) NOT NULL,
  email        VARCHAR(150) NOT NULL UNIQUE,
  password     VARCHAR(255) NOT NULL,
  tipo         ENUM('admin','ordinante','tecnico') NOT NULL
);

-- 2) Categoria
CREATE TABLE Categoria (
  id_categoria INT AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(100) NOT NULL,
  id_padre     INT          NULL,
  FOREIGN KEY (id_padre)
    REFERENCES Categoria(id_categoria)
    ON DELETE SET NULL
    ON UPDATE CASCADE
);

-- 3) Caratteristica
CREATE TABLE Caratteristica (
  id_caratt    INT AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(100) NOT NULL UNIQUE,
  tipo_valore  ENUM('numerico','testuale') NOT NULL
);

-- 4) Possiede
CREATE TABLE Possiede (
  id_categoria INT NOT NULL,
  id_caratt    INT NOT NULL,
  PRIMARY KEY (id_categoria, id_caratt),
  FOREIGN KEY (id_categoria)
    REFERENCES Categoria(id_categoria)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (id_caratt)
    REFERENCES Caratteristica(id_caratt)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

-- 5) RichiestaAcquisto
CREATE TABLE RichiestaAcquisto (
  id_richiesta     INT AUTO_INCREMENT PRIMARY KEY,
  id_categoria     INT                NOT NULL,
  data_inserimento DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note_generali    TEXT               NULL,
  data_chiusura    DATETIME           NULL,
  esito_chiusura   ENUM('accettato', 'respinto_non_conforme', 'respinto_non_funzionante') NULL,
  FOREIGN KEY (id_categoria)
    REFERENCES Categoria(id_categoria)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT chk_chiusura
    CHECK (data_chiusura IS NULL OR data_chiusura >= data_inserimento)
);

-- 6) Partecipazione
CREATE TABLE Partecipazione (
  id_richiesta INT                         NOT NULL,
  id_utente    INT                         NOT NULL,
  ruolo        ENUM('ordinante','tecnico') NOT NULL,
  PRIMARY KEY (id_richiesta, id_utente),
  FOREIGN KEY (id_richiesta)
    REFERENCES RichiestaAcquisto(id_richiesta)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (id_utente)
    REFERENCES Utente(id_utente)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
);

-- 7) ValoreRichiesta
CREATE TABLE ValoreRichiesta (
  id_valore    INT AUTO_INCREMENT PRIMARY KEY,
  id_richiesta INT                NOT NULL,
  id_caratt    INT                NOT NULL,
  valore       VARCHAR(255)       NOT NULL,
  indifferente BOOLEAN            NOT NULL DEFAULT FALSE,
  FOREIGN KEY (id_richiesta)
    REFERENCES RichiestaAcquisto(id_richiesta)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  FOREIGN KEY (id_caratt)
    REFERENCES Caratteristica(id_caratt)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  CONSTRAINT uq_valore
    UNIQUE (id_richiesta, id_caratt)
);

-- 8) ProdottoCandidato
CREATE TABLE ProdottoCandidato (
  id_cand            INT AUTO_INCREMENT PRIMARY KEY,
  id_richiesta       INT                     NOT NULL,
  produttore         VARCHAR(150)            NOT NULL,
  nome_prodotto      VARCHAR(150)            NOT NULL,
  codice_prodotto    VARCHAR(100)            NOT NULL UNIQUE,
  prezzo             DECIMAL(10,2)           NOT NULL,
  url                VARCHAR(255)            NULL,
  note               TEXT                    NULL,
  esito_revisione    ENUM('in_attesa','approvato','respinto') NOT NULL DEFAULT 'in_attesa',
  motivazione_rifiuto TEXT                   NULL,
  data_proposta      DATETIME                NOT NULL DEFAULT CURRENT_TIMESTAMP,
  data_ordine        DATETIME                NULL,
  FOREIGN KEY (id_richiesta)
    REFERENCES RichiestaAcquisto(id_richiesta)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT chk_date_order
    CHECK (data_ordine IS NULL OR data_ordine >= data_proposta)
);