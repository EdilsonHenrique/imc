<?php
// config.php
if (!function_exists('conectarBanco')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'bd_abelhinha');

    function conectarBanco() {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Erro de conexão: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8");
        return $conn;
    }
}
?>