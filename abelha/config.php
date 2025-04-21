<?php
// config.php - Configuration settings for database connection
if (!function_exists('conectarBanco')) {
    // Use environment variables for PostgreSQL connection
    $db_url = getenv('DATABASE_URL');
    $pghost = getenv('PGHOST');
    $pgport = getenv('PGPORT');
    $pguser = getenv('PGUSER');
    $pgpassword = getenv('PGPASSWORD');
    $pgdatabase = getenv('PGDATABASE');
    
    // Função para compatibilidade com código existente (simulando mysqli)
    class PgSQLAdapter {
        public $conn;
        public $insert_id;
        public $affected_rows;
        public $error;
        private $closed = false;
        
        public function __construct($conn) {
            $this->conn = $conn;
        }
        
        // Método para acessar o recurso de conexão diretamente
        public function get_raw_resource() {
            return $this->conn;
        }
        
        public function query($sql) {
            if ($this->closed) {
                $this->error = "PostgreSQL connection has been closed";
                return false;
            }
            
            $result = pg_query($this->conn, $sql);
            if (!$result) {
                $this->error = pg_last_error($this->conn);
                return false;
            }
            
            // Get last insert ID if applicable
            if (stripos($sql, 'INSERT INTO') !== false) {
                // Extract table name from INSERT statement
                if (preg_match('/INSERT INTO\s+([^\s\(]+)/i', $sql, $matches)) {
                    $table = trim($matches[1]);
                    $seq = $table . '_id_seq';
                    $idResult = pg_query($this->conn, "SELECT currval('$seq') as id");
                    if ($idResult && $row = pg_fetch_assoc($idResult)) {
                        $this->insert_id = $row['id'];
                    }
                }
            }
            
            // Get affected rows 
            $this->affected_rows = pg_affected_rows($result);
            
            return new PgSQLResult($result);
        }
        
        public function prepare($sql) {
            if ($this->closed) {
                $this->error = "PostgreSQL connection has been closed";
                return false;
            }
            
            // Converter ? para $1, $2, etc.
            $param_index = 1;
            $sql = preg_replace_callback('/\?/', function($matches) use (&$param_index) {
                return '$' . $param_index++;
            }, $sql);
            
            return new PgSQLStatement($this->conn, $sql);
        }
        
        public function begin_transaction() {
            if ($this->closed) {
                $this->error = "PostgreSQL connection has been closed";
                return false;
            }
            return pg_query($this->conn, "BEGIN");
        }
        
        public function commit() {
            if ($this->closed) {
                $this->error = "PostgreSQL connection has been closed";
                return false;
            }
            return pg_query($this->conn, "COMMIT");
        }
        
        public function rollback() {
            if ($this->closed) {
                $this->error = "PostgreSQL connection has been closed";
                return false;
            }
            return pg_query($this->conn, "ROLLBACK");
        }
        
        public function real_escape_string($value) {
            if ($this->closed) {
                return $value; // Best effort if closed
            }
            return pg_escape_string($this->conn, $value);
        }
        
        public function close() {
            if (!$this->closed) {
                $this->closed = true;
                return pg_close($this->conn);
            }
            return true;
        }
    }
    
    class PgSQLStatement {
        private $conn;
        private $sql;
        private $params = [];
        private $types = '';
        private $result = null;
        public $error = '';
        private $closed = false;
        public $insert_id = null;
        
        public function __construct($conn, $sql) {
            $this->conn = $conn;
            $this->sql = $sql;
        }
        
        public function bind_param($types, ...$params) {
            if ($this->closed) {
                $this->error = "Statement has been closed";
                return false;
            }
            
            $this->types = $types;
            $this->params = $params;
            return true;
        }
        
        public function execute() {
            if ($this->closed) {
                $this->error = "Statement has been closed";
                return false;
            }
            
            // Check if we have all parameters
            if (strlen($this->types) !== count($this->params)) {
                $this->error = "Parameter count mismatch: expected " . strlen($this->types) . ", got " . count($this->params);
                return false;
            }
            
            // Convert data types for PostgreSQL
            $converted_params = [];
            for ($i = 0; $i < strlen($this->types); $i++) {
                $value = $this->params[$i];
                switch ($this->types[$i]) {
                    case 'i': // integer
                        $converted_params[] = (int)$value;
                        break;
                    case 'd': // double/float
                        $converted_params[] = (float)$value;
                        break;
                    case 's': // string
                    default:
                        $converted_params[] = $value;
                        break;
                }
            }
            
            try {
                // Check for valid connection
                if (!is_resource($this->conn) && !($this->conn instanceof \PgSql\Connection)) {
                    $this->error = "Invalid PostgreSQL connection";
                    return false;
                }
                
                $this->result = pg_query_params($this->conn, $this->sql, $converted_params);
                
                if (!$this->result) {
                    $this->error = pg_last_error($this->conn);
                    return false;
                }
                
                // Store the last insert ID if this is an INSERT query
                if (stripos($this->sql, 'INSERT INTO') !== false) {
                    if (preg_match('/INSERT INTO\s+([^\s\(]+)/i', $this->sql, $matches)) {
                        $table = trim($matches[1]);
                        $seq = $table . '_id_seq';
                        $idResult = pg_query($this->conn, "SELECT currval('$seq') as id");
                        if ($idResult && $row = pg_fetch_assoc($idResult)) {
                            $this->insert_id = $row['id'];
                        }
                    }
                }
                
                return true;
            } catch (Exception $e) {
                $this->error = "Error executing query: " . $e->getMessage();
                return false;
            }
        }
        
        public function get_result() {
            if ($this->closed) {
                $this->error = "Statement has been closed";
                return false;
            }
            
            if (!$this->result) {
                return false;
            }
            
            return new PgSQLResult($this->result);
        }
        
        public function close() {
            if ($this->closed) {
                return true;
            }
            
            if ($this->result) {
                pg_free_result($this->result);
                $this->result = null;
            }
            
            $this->closed = true;
            return true;
        }
    }
    
    class PgSQLResult {
        private $result;
        public $num_rows;
        
        public function __construct($result) {
            $this->result = $result;
            $this->num_rows = pg_num_rows($result);
        }
        
        public function fetch_assoc() {
            return pg_fetch_assoc($this->result);
        }
        
        public function fetch_all($resulttype = MYSQLI_ASSOC) {
            $rows = [];
            while ($row = pg_fetch_assoc($this->result)) {
                $rows[] = $row;
            }
            return $rows;
        }
        
        public function free() {
            return pg_free_result($this->result);
        }
        
        // Get the raw resource result for direct usage
        public function get_raw_result() {
            return $this->result;
        }
    }
    
    // Função para conectar ao banco e retornar o adapter
    function conectarBanco() {
        global $pghost, $pgport, $pguser, $pgpassword, $pgdatabase;
        
        // Detectar ambiente (Replit vs. Local)
        $isReplit = getenv('REPL_ID') !== false || getenv('PGHOST') !== false;
        
        if ($isReplit) {
            // Conectar ao PostgreSQL no Replit
            try {
                $conn_string = "host=$pghost port=$pgport dbname=$pgdatabase user=$pguser password=$pgpassword";
                $pg_conn = pg_connect($conn_string);
                
                if (!$pg_conn) {
                    die("Erro de conexão ao PostgreSQL: " . pg_last_error());
                }
                
                return new PgSQLAdapter($pg_conn);
            } catch (Exception $e) {
                die("Erro de conexão ao PostgreSQL: " . $e->getMessage());
            }
        } else {
            // Conectar ao MySQL em ambiente local
            try {
                // Configuração para ambiente local (XAMPP)
                $host = 'localhost';
                $user = 'root';
                $password = '';
                $database = 'abelhinha_doce';
                
                // Para compatibilidade com install.php, verificamos se o banco existe
                // e se não, criamos antes de tentar conectar
                $mysqli_check = new mysqli($host, $user, $password);
                if (!$mysqli_check->connect_error) {
                    // Verificar se o banco de dados existe
                    $result = $mysqli_check->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
                    if ($result && $result->num_rows == 0) {
                        // Criar o banco de dados se não existir
                        $mysqli_check->query("CREATE DATABASE IF NOT EXISTS $database");
                    }
                    $mysqli_check->close();
                }
                
                // Agora conectar ao banco de dados
                $mysqli = new mysqli($host, $user, $password, $database);
                
                if ($mysqli->connect_error) {
                    die("Erro de conexão ao MySQL: " . $mysqli->connect_error);
                }
                
                return $mysqli;
            } catch (Exception $e) {
                die("Erro de conexão ao MySQL: " . $e->getMessage());
            }
        }
    }
}
?>
