-- ----------------------------------------------------
-- File:          market_queries.sql
-- Descrizione:   Query per le 11 operazioni richieste
-- Data inizio:   21/05/2025
-- Data fine:     gg/mm/2025
-- Autori:        MasterDump
-- ----------------------------------------------------

-- 1) Inserimento di una richiesta di acquisto
INSERT INTO RichiestaAcquisto(id_categoria, data_inserimento, note_generali)
VALUES(?, NOW(), ?);
--  ^ placeholder #1: id_categoria  (es. 3 = “Notebook”)
--  ^ placeholder #2: note_generali (testo libero, es. 'Notebook per ufficio')

-- Registra l’ordinante in Partecipazione
INSERT INTO Partecipazione(id_richiesta, id_utente, ruolo)
VALUES(?, ?, 'ordinante');
--  ^ placeholder #3: id_richiesta (quella appena creata)
--  ^ placeholder #4: id_utente (chi crea la richiesta, es. 2 = “Anna”)

-- Inserimento dei valori per ciascuna caratteristica (per N caratteristiche, ripetere: (id_caratt, valore, indifferente))
INSERT INTO ValoreRichiesta(id_richiesta, id_caratt, valore, indifferente) VALUES(?, ?, ?, ?);
--  ^ #4: id_caratt    (es. 1 = “RAM”)
--  ^ #5: valore       (es. '16')
--  ^ #6: indifferente (0 = usa valore, 1 = “indifferente”)

-- -- -- -- --

-- 2) Associazione di un tecnico a una richiesta
INSERT INTO Partecipazione(id_richiesta, id_utente, ruolo) VALUES(?, ?, 'tecnico');
-- ^ placeholder #1: id_richiesta (es. 1)
-- ^ placeholder #2: id_utente (es. 3)

-- -- -- -- --

-- 3) Inserimento di un prodotto candidato
INSERT INTO ProdottoCandidato(id_richiesta, produttore, nome_prodotto, codice_prodotto, prezzo, url, note, esito_revisione, data_proposta)
VALUES(?, ?, ?, ?, ?, ?, ?, 'in_attesa', NOW());   -- Es. (1, 'Dell', 'XPS 13', 'DLXPS13', 1200.00, 'https://example.com/xps13', 'Notebook per prova')

-- Approvazione del candidato
UPDATE ProdottoCandidato SET esito_revisione = 'approvato', motivazione_rifiuto = NULL, data_ordine = NOW() WHERE id_cand = ?;   -- --  ^ placeholder: id_cand del prodotto da approvare

-- -- -- -- --

-- 4) Eliminazione della richiesta #1 (e record dipendenti)
DELETE FROM RichiestaAcquisto WHERE id_richiesta = ?;   -- ^ placeholder: id_richiesta da eliminare

-- -- -- -- --

-- 5) Lista delle richieste in corso (non chiuse) di un ordinante con candidato in attesa
SELECT R.id_richiesta, R.id_categoria, R.data_inserimento, R.note_generali, P.id_cand, P.produttore, P.nome_prodotto, P.esito_revisione
FROM RichiestaAcquisto R
JOIN Partecipazione O ON O.id_richiesta = R.id_richiesta AND O.ruolo = 'ordinante'
JOIN ProdottoCandidato P ON P.id_richiesta = R.id_richiesta WHERE O.id_utente = ?   -- placeholder: id dell’ordinante
AND R.data_chiusura IS NULL   -- non ancora chiuse
AND P.esito_revisione = 'in_attesa';

-- -- -- -- --

-- 6) Estrazione richieste non ancora assegnate ad alcun tecnico
SELECT R.id_richiesta, R.id_categoria, R.data_inserimento, R.note_generali
FROM RichiestaAcquisto R
LEFT JOIN Partecipazione T ON T.id_richiesta = R.id_richiesta AND T.ruolo = 'tecnico'
WHERE T.id_utente IS NULL;

-- -- -- -- --

-- 7) Richieste per un tecnico con prodotto approvato non ancora ordinato
SELECT R.id_richiesta, R.id_categoria, R.data_inserimento, R.note_generali, P.id_cand, P.produttore, P.nome_prodotto, P.prezzo, P.esito_revisione, P.data_ordine
FROM RichiestaAcquisto R
JOIN Partecipazione T ON T.id_richiesta = R.id_richiesta AND T.ruolo = 'tecnico'
JOIN ProdottoCandidato P ON P.id_richiesta = R.id_richiesta
WHERE T.id_utente = ?   -- placeholder: id del tecnico (es. 3)
AND P.esito_revisione = 'approvato'
AND P.data_ordine IS NULL;

-- -- -- -- --

-- 8) Dettaglio completo di una richiesta
SELECT R.id_richiesta, R.id_categoria, R.data_inserimento, R.data_chiusura, R.note_generali, R.esito_chiusura,
  O.id_utente AS ordinante, T.id_utente AS tecnico, P.id_cand, P.produttore, P.nome_prodotto, P.codice_prodotto,
  P.prezzo, P.url, P.note, P.esito_revisione, P.motivazione_rifiuto, P.data_ordine,
  V.id_valore, V.id_caratt, V.valore, V.indifferente
FROM RichiestaAcquisto R

-- chi mi dice chi è l’ordinante?
LEFT JOIN Partecipazione O ON O.id_richiesta = R.id_richiesta AND O.ruolo = 'ordinante'

-- chi mi dice il tecnico (se c’è)?
LEFT JOIN Partecipazione T ON T.id_richiesta = R.id_richiesta AND T.ruolo = 'tecnico'

-- un solo candidato, se ne è stato proposto uno
LEFT JOIN ProdottoCandidato P ON P.id_richiesta = R.id_richiesta

-- e tutti i valori inseriti dall’ordinante
LEFT JOIN ValoreRichiesta V ON V.id_richiesta = R.id_richiesta

WHERE R.id_richiesta = ?;   -- placeholder: ID della richiesta da dettagliare

-- -- -- -- --

-- 9) Conteggio richieste gestite da un tecnico
SELECT COUNT(*) AS totale_richieste
FROM Partecipazione P
WHERE P.ruolo = 'tecnico' AND P.id_utente = ?;   -- placeholder: ID del tecnico (es. 3)

-- -- -- -- --

-- 10) Spesa totale di un ordinante in un anno solare
SELECT SUM(C.prezzo) AS spesa_totale
FROM Partecipazione Par
JOIN RichiestaAcquisto R ON R.id_richiesta = Par.id_richiesta
JOIN ProdottoCandidato C ON C.id_richiesta = R.id_richiesta
WHERE Par.ruolo = 'ordinante'
  AND Par.id_utente = ?   -- placeholder #1: id_utente (es. 2)
  AND C.esito_revisione = 'approvato' AND R.esito_chiusura = 'accettato'
  AND YEAR(C.data_ordine) = ?;   -- placeholder #2: anno (es. 2025)

-- -- -- -- --

-- 11) Tempo medio di evasione
SELECT SEC_TO_TIME(AVG(TIMESTAMPDIFF(SECOND, R.data_inserimento, P.data_ordine))) AS tempo_medio
FROM RichiestaAcquisto R
JOIN ProdottoCandidato P ON P.id_richiesta = R.id_richiesta
WHERE P.data_ordine IS NOT NULL;

-- -- -- -- --

-- Chiusura richiesta acquisto (operazione aggiuntiva)
UPDATE RichiestaAcquisto 
SET data_chiusura = NOW(), esito_chiusura = ?
WHERE id_richiesta = ?;
-- ^ placeholder #1: esito_chiusura ('accettato', 'respinto_non_conforme', 'respinto_non_funzionante')
-- ^ placeholder #2: id_richiesta da chiudere