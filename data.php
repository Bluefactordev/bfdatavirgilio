<?php
include 'config.php';

// Funzione per eseguire query SQL e restituire i risultati
function get_data($conn, $sql) {
    $result = $conn->query($sql);
    $data = array();
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

// Accessi giornalieri
$accessi_giornalieri = get_data($conn, "SELECT DATE(Data) as giorno, COUNT(*) as totale FROM bf_accessi GROUP BY DATE(Data)");

// Utenti attivi e non attivi
$utenti_attivi = get_data($conn, "SELECT attivo, COUNT(*) as totale FROM utente GROUP BY attivo");

// Tipologia di accesso
$tipologia_accesso = get_data($conn, "SELECT a.nome, COUNT(u.id) as totale FROM utente u JOIN accesso a ON u.id_accesso = a.id GROUP BY a.nome");

// Consulenze per periodo
$consulenze_periodo = get_data($conn, "SELECT DATE(Data) as giorno, COUNT(*) as totale FROM bf_consulenze GROUP BY DATE(Data)");

// Consulenze per luogo
$consulenze_luogo = get_data($conn, "SELECT Luogo, COUNT(*) as totale FROM bf_consulenze GROUP BY Luogo");

// Consulenze per operatore
$consulenze_operatore = get_data($conn, "SELECT Rif_Operatore, COUNT(*) as totale FROM bf_consulenze GROUP BY Rif_Operatore");

// Interventi per periodo
$interventi_periodo = get_data($conn, "SELECT DATE(Data) as giorno, COUNT(*) as totale FROM bf_interventi GROUP BY DATE(Data)");

// Interventi per luogo
$interventi_luogo = get_data($conn, "SELECT Luogo, COUNT(*) as totale FROM bf_interventi GROUP BY Luogo");

// Interventi per operatore
$interventi_operatore = get_data($conn, "SELECT Rif_Operatore, COUNT(*) as totale FROM bf_interventi GROUP BY Rif_Operatore");

// Pazienti per sesso
$pazienti_sesso = get_data($conn, "SELECT Sesso, COUNT(*) as totale FROM bf_anagrafica GROUP BY Sesso");

// Età media dei pazienti
$eta_media_pazienti = get_data($conn, "SELECT AVG(YEAR(CURDATE()) - YEAR(Nascita)) as eta_media FROM bf_anagrafica WHERE Nascita IS NOT NULL");

// Pazienti per patologia
$pazienti_patologia = get_data($conn, "SELECT Patologia, COUNT(*) as totale FROM bf_anagrafica GROUP BY Patologia");

// Pazienti per stato
$pazienti_stato = get_data($conn, "SELECT Stato, COUNT(*) as totale FROM bf_anagrafica GROUP BY Stato");

// Pazienti per motivo di fine presa in carico
$pazienti_motivo_fine = get_data($conn, "SELECT MotivoFine, COUNT(*) as totale FROM bf_anagrafica GROUP BY MotivoFine");

// Presidi attivi e non attivi
$presidi_attivi = get_data($conn, "SELECT Attivo, COUNT(*) as totale FROM presidio GROUP BY Attivo");

// Pazienti per presidio
$pazienti_presidio = get_data($conn, "SELECT p.nome AS presidio, COUNT(a.ID) AS totale FROM bf_anagrafica a JOIN presidio p ON a.Rif_Presidio = p.id GROUP BY p.nome");

// Consulenze per presidio
$consulenze_presidio = get_data($conn, "SELECT p.nome AS presidio, COUNT(c.ID) AS totale FROM bf_consulenze c JOIN presidio p ON c.Rif_Presidio = p.id GROUP BY p.nome");

// Interventi psicologici per periodo
$interventi_psicologici_periodo = get_data($conn, "SELECT DATE(DataStop) as giorno, COUNT(*) as totale FROM bf_interventiPsicologi GROUP BY DATE(DataStop)");

// Tempo medio di permanenza in cura per presidio
$tempo_permanenza_presidio = get_data($conn, "SELECT p.nome AS presidio, AVG(DATEDIFF(a.FinePresaInCarico, a.Inserimento)) AS tempo_medio FROM bf_anagrafica a JOIN presidio p ON a.Rif_Presidio = p.id WHERE a.FinePresaInCarico IS NOT NULL GROUP BY p.nome");

// Tempo medio di permanenza in cura per età
$tempo_permanenza_eta = get_data($conn, "SELECT FLOOR((YEAR(CURDATE()) - YEAR(a.Nascita))/10)*10 AS eta_range, AVG(DATEDIFF(a.FinePresaInCarico, a.Inserimento)) AS tempo_medio FROM bf_anagrafica a WHERE a.FinePresaInCarico IS NOT NULL AND a.Nascita IS NOT NULL GROUP BY eta_range");

// Tempo medio di permanenza in cura per operatore
$tempo_permanenza_operatore = get_data($conn, "SELECT o.nome AS operatore, AVG(DATEDIFF(a.FinePresaInCarico, a.Inserimento)) AS tempo_medio FROM bf_anagrafica a JOIN bf_consulenze c ON a.ID = c.Rif_Paziente JOIN utente o ON c.Rif_Operatore = o.id WHERE a.FinePresaInCarico IS NOT NULL GROUP BY o.nome");

// Pazienti con tempo di permanenza negativo
$pazienti_tempo_negativo = get_data($conn, "SELECT ID, Nome, Cognome, Inserimento, FinePresaInCarico, DATEDIFF(FinePresaInCarico, Inserimento) AS tempo_permanenza FROM bf_anagrafica WHERE DATEDIFF(FinePresaInCarico, Inserimento) < 0");

// Pazienti con età non compresa tra 0 e 100 anni
$pazienti_eta_non_valida = get_data($conn, "SELECT ID, Nome, Cognome, Nascita, (YEAR(CURDATE()) - YEAR(Nascita)) AS eta FROM bf_anagrafica WHERE (YEAR(CURDATE()) - YEAR(Nascita)) < 0 OR (YEAR(CURDATE()) - YEAR(Nascita)) > 100");

// Medici con interventi più elevati
$medici_interventi_elevati = get_data($conn, "SELECT u.nome, u.cognome, COUNT(i.ID) as totale_interventi FROM bf_interventi i JOIN utente u ON i.Rif_Operatore = u.id GROUP BY u.nome, u.cognome ORDER BY totale_interventi DESC LIMIT 10");

$conn->close();

echo json_encode(array(
    'accessi_giornalieri' => $accessi_giornalieri,
    'utenti_attivi' => $utenti_attivi,
    'tipologia_accesso' => $tipologia_accesso,
    'consulenze_periodo' => $consulenze_periodo,
    'consulenze_luogo' => $consulenze_luogo,
    'consulenze_operatore' => $consulenze_operatore,
    'interventi_periodo' => $interventi_periodo,
    'interventi_luogo' => $interventi_luogo,
    'interventi_operatore' => $interventi_operatore,
    'pazienti_sesso' => $pazienti_sesso,
    'eta_media_pazienti' => $eta_media_pazienti,
    'pazienti_patologia' => $pazienti_patologia,
    'pazienti_stato' => $pazienti_stato,
    'pazienti_motivo_fine' => $pazienti_motivo_fine,
    'presidi_attivi' => $presidi_attivi,
    'pazienti_presidio' => $pazienti_presidio,
    'consulenze_presidio' => $consulenze_presidio,
    'interventi_psicologici_periodo' => $interventi_psicologici_periodo,
    'tempo_permanenza_presidio' => $tempo_permanenza_presidio,
    'tempo_permanenza_eta' => $tempo_permanenza_eta,
    'tempo_permanenza_operatore' => $tempo_permanenza_operatore,
    'pazienti_tempo_negativo' => $pazienti_tempo_negativo,
    'pazienti_eta_non_valida' => $pazienti_eta_non_valida,
    'medici_interventi_elevati' => $medici_interventi_elevati
));
?>
