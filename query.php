<?php
ini_set('error_log', 'errorquery.log');
ini_set('log_errors', 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

error_log("Hello, errors new2!");

include 'config.php';
require 'vendor/autoload.php'; // Assicurati di avere questo file e che sia corretto

use OpenAI\Client;

$client = OpenAI::client('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');    
$usa_agent = true;

function logga($msg) {
    file_put_contents('log.txt', $msg . "\n", FILE_APPEND);
}

// Funzione per leggere il file di configurazione specifico del database
function get_database_specifics($dbname) {
    $config_file = __DIR__ . '/' . $dbname . '_specifichequery.config';
    if (file_exists($config_file)) {
        logga("Lettura py delle specifiche per il database $dbname");
        return file_get_contents($config_file);
    }
    return '';
}

// Verifica se lo script è eseguito da riga di comando o come richiesta web
if (php_sapi_name() == 'cli') {
    // Esecuzione da riga di comando
    if ($argc > 2) {
        $dbname = $argv[1];
        $question = $argv[2];
        $db_specifics == $argv[3];
        $save_db_specifics = $argv[4];
    } else {
        echo "Usage: php script.php <dbname> \"your question here\"\n";
        exit(1);
    }
} else {
    // Esecuzione come richiesta web
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $dbname = $data['dbname'];
        $question = $data['question'];
        $db_specifics = $data['db_specifics'];
        $save_db_specifics = $data['save_db_specifics'];   
        logga ("Richiesta POST: dbname=$dbname, question=$question, db_specifics=$db_specifics, save_db_specifics=$save_db_specifics"); 
    } else {
        echo json_encode(['error' => 'Invalid request method.']);
        exit(1);
    }
}
if (empty($db_specifics)) {
    $db_specifics = get_database_specifics($dbname);
}
else {
    $config_file = __DIR__ . '/' . $dbname . '_specifichequery.config';
    if ($save_db_specifics) {
        file_put_contents($config_file, $db_specifics);
        logga("Salvo in py le specifiche del database in $config_file");
    }
    else {
        logga("Non salvo le specifiche del database in $config_file");
    }
}
// Aggiungi specifiche alla domanda basate sul database

if (!empty($db_specifics)) {
    logga("Specifiche database: $db_specifics");
    $question .= " " . $db_specifics;
}

logga("Parto con question: $question");

if ($usa_agent) {
    // prendi la query dal file queryagent.py
    $comando = "bash -c 'source venv/bin/activate && python3 queryagent.py \"$dbname\" \"$question\" 2>&1'";
    logga("Eseguo: $comando");
    $output = shell_exec($comando);
    logga("Output recuperato dall'agente: $output");

    // Decodifica l'output JSON
    $response = json_decode($output, true);
    if (isset($response['sql_query'])) {
        $sql_query = $response['sql_query'];
    } else {
        logga("Errore: JSON non contiene sql_query.");
        echo json_encode(['error' => 'La risposta JSON non contiene sql_query.']);
        exit(1);
    }
} else {
    $database_structure = "
    La struttura del database è la seguente:
    - bf_accessi: (ID, Data, UtenteID)
    - utente: (id, nome, cognome, attivo, id_accesso)
    - accesso: (id, nome)
    - bf_consulenze: (ID, Data, Luogo, Rif_Operatore)
    - bf_interventi: (ID, Data, Luogo, Rif_Operatore)
    - bf_anagrafica: (ID, Nome, Cognome, Sesso, Nascita, Inserimento, FinePresaInCarico, Patologia, Stato, MotivoFine, Rif_Presidio)
    - presidio: (id, nome, Attivo)
    - bf_interventiPsicologi: (ID, DataStop)
    ";

    try {
        $response = $client->chat()->create([
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                ['role' => 'system', 'content' => 'Sei un assistente che aiuta a trasformare domande in query SQL in base alla struttura del database fornita.'],
                ['role' => 'user', 'content' => "Utilizzando la struttura del database, trasforma questa domanda in una query SQL: $question\n\n$database_structure"]
            ],
            'max_tokens' => 150,
        ]);

        // Estrazione della query SQL dalla risposta di OpenAI
        $sql_query = '';
        if (isset($response['choices'][0]['message']['content'])) {
            preg_match('/SELECT.*;/s', $response['choices'][0]['message']['content'], $matches);
            if (isset($matches[0])) {
                $sql_query = $matches[0];
            }
        }
    } catch (Exception $e) {
        file_put_contents('error.log', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['error' => $e->getMessage()]);
        exit(1);
    } finally {
        $conn->close();
    }
}

if (empty($sql_query)) {
    echo json_encode(['error' => 'La query SQL non è stata generata correttamente.']);
    exit(1);
}

// Controllo che la query non contenga istruzioni di scrittura
if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|REPLACE|TRUNCATE)\b/i', $sql_query)) {
    echo json_encode(['error' => 'La query SQL contiene istruzioni non consentite.']);
    exit(1);
}

$adesso = date('Y-m-d H:i:s');
file_put_contents('error.log', "Data: ".$adesso ."\nGenerated SQL Query: $sql_query\n", FILE_APPEND); 
logga("Query generata: $sql_query");

// Modifica la connessione al database per usare il database selezionato
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$result = $conn->query($sql_query);
if (!$result) { 
    echo json_encode(['error' => $conn->error]);
    exit(1);
}

$data = array();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
}

// Restituisci un JSON con i campi query (la query generata) e data (i risultati della query)
$response = ['query' => $sql_query, 'data' => $data];
logga("Risultato: " . json_encode($response));

echo json_encode($response);
?>
