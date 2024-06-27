import os
import sys
import json
import pymysql
from sqlalchemy import create_engine
import logging
from langchain_openai import ChatOpenAI
from langchain_community.utilities import SQLDatabase
from langchain_core.prompts.chat import ChatPromptTemplate
from langchain_core.output_parsers.json import JsonOutputParser 

save_space = True # Se impostato a True, salva solo la struttura del database senza esempi di dati e relazioni tra le tabelle 
# Configurazione del logging
logging.basicConfig(filename='log.txt', level=logging.DEBUG, format='%(asctime)s - %(levelname)s - %(message)s')

def is_debug():
    return os.name == 'nt'

def logga(log):
    with open('log.txt', 'a') as f:
        f.write(log + '\n') 
        
    if is_debug():
        print(log)

logga("Inizializzazione del modulo queryagent.py")       

def get_db_password():
    if is_debug():    
        return 'Piononn1!'
    return 'tsz912!vA'

db_password = get_db_password()

llm = ChatOpenAI(api_key='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', model='gpt-4o')

db_config_template = {
    'host': 'localhost',
    'port': 3306,
    'user': 'root',
    'password': db_password,
    'database': ''
}

def get_database_structure(dbname):
    db_config = db_config_template.copy()
    db_config['database'] = dbname
    connection = pymysql.connect(**db_config)
    cursor = connection.cursor()

    cursor.execute("SHOW TABLES")
    tables = cursor.fetchall()

    database_structure = "La struttura del database è la seguente:\n"
    relationships = "Le relazioni tra le tabelle sono le seguenti:\n"
    for table in tables:
        table_name = table[0]
        database_structure += f"\n- {table_name}:\n"

        cursor.execute(f"SHOW COLUMNS FROM {table_name}")
        columns = cursor.fetchall()
        for column in columns:
            column_name = column[0]
            column_type = column[1]
            key = column[3]

            column_details = f"{column_name} {column_type}"
            if key == 'PRI':
                column_details += " PRIMARY KEY"
            elif key == 'MUL':
                column_details += " FOREIGN KEY"

            if 'enum' in column_type:
                cursor.execute(f"SHOW COLUMNS FROM {table_name} LIKE '{column_name}'")
                enum_column = cursor.fetchone()
                enum_type = enum_column[1]
                column_details += f" {enum_type}"

            database_structure += f"  - {column_details}\n"

        cursor.execute(f"SHOW CREATE TABLE {table_name}")
        create_table_stmt = cursor.fetchone()[1]
        for line in create_table_stmt.split('\n'):
            if 'FOREIGN KEY' in line:
                relationships += f"  - {line.strip()}\n"

        cursor.execute(f"SELECT * FROM {table_name} LIMIT 1")
        rows = cursor.fetchall()
        if rows:
            database_structure += "  Esempi di dati:\n"
            for row in rows:
                database_structure += f"    {row}\n"

    connection.close()
    if save_space:
        logga("ritorno solo la struttura del database senza esempi di dati e relazioni tra le tabelle")

        return database_structure
    logga("ritorno la struttura del database con esempi di dati e relazioni tra le tabelle")
    return database_structure + "\n" + relationships

def create_engine_for_db(dbname):
    connection_string = f"mysql+pymysql://{db_config_template['user']}:{db_config_template['password']}@{db_config_template['host']}:{db_config_template['port']}/{dbname}"
    try:
        engine = create_engine(connection_string)
        connection = engine.connect()
        connection.close()
        return SQLDatabase.from_uri(connection_string)
    except Exception as e:
        logga(f"Errore di connessione con SQLAlchemy: {e}")
        raise

def question_to_sql(dbname, question):
    database_structure = get_database_structure(dbname)

    chat_template = ChatPromptTemplate.from_messages(
        [
            ("system", "Sei un assistente che aiuta a trasformare domande in query SQL in base alla struttura del database fornita. Rispondi con un JSON che contiene solo la query SQL e eventuali commenti separati. Il JSON deve avere esattamente questi campi: 'query' per la query SQL e 'comment' per eventuali commenti. Ecco alcuni esempi di query che potrebbero aiutarti a comprendere il formato desiderato:\n"
                       "{examples}\n"
                       "Usa queste informazioni per creare la query. Quando desumi quale sia una tabella per n crto tipo di informazione, non basarti solo sul nome della tabella, ma anche sulle sue relazioni con le altre tabelle. Ad esempio una tabella prodotti deve essere referenziata da una tabella con le righe degli ordini, etc"),
            ("human", "{question}\n\n{database_structure}")
        ]
    )

    # Aggiungi esempi di query per migliorare il contesto
    examples = """
    Esempio 1:
    Domanda: "Mostra tutti i clienti che hanno effettuato un ordine nel 2023."
    Query: {"query": "SELECT * FROM customers c JOIN orders o ON c.customer_id = o.customer_id WHERE YEAR(o.order_date) = 2023", "comment": "Unisci le tabelle customers e orders per trovare i clienti che hanno effettuato ordini nel 2023."}
    
    Esempio 2:
    Domanda: "Quali prodotti sono stati ordinati almeno 10 volte?"
    Query: {"query": "SELECT product_id, COUNT(*) as order_count FROM order_items GROUP BY product_id HAVING order_count >= 10", "comment": "Conta il numero di ordini per ogni prodotto e seleziona quelli con almeno 10 ordini."}
    """
    examples=""
    
    messages = chat_template.format_messages(
        question=question,
        database_structure=database_structure,
        examples= examples
    )
    logga(f"Domanda: {messages}")
    response = llm.invoke(messages)
    return parse_response(response.content)

def parse_response(response_text):
    parser = JsonOutputParser() 
    try:
        response_json = parser.parse(response_text)
        query_key = next((key for key in response_json if 'query' in key.lower()), None)
        if query_key:
            logga(f"Query: {response_json[query_key]}")
            comment_key = next((key for key in response_json if 'comment' in key.lower()), None)
            if comment_key:
                logga(f"Commento: {response_json[comment_key]}")
            return response_json[query_key]
        else:
            raise ValueError("La risposta JSON non contiene una query.")
    except json.JSONDecodeError as e:
        logga(f"Errore nel parsing del JSON: {str(e)}")
        raise ValueError("La risposta non è un JSON valido.")

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python queryagent.py <dbname> \"your question here\"") 
        sys.exit(1)

    dbname = sys.argv[1]
    question = sys.argv[2]

    try:
        sql_query = question_to_sql(dbname, question) 
        print(json.dumps({"sql_query": sql_query}))
    except Exception as e:
        logga(json.dumps({"error": str(e)}))
        print(json.dumps({"error": str(e)}))
