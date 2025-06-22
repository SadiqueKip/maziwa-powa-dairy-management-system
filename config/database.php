<?php
class Database {
    private $host = 'localhost';
    private $username = 'powascop_maziwa';
    private $password = '@Arcamax87?';
    private $database = 'powascop_maziwa';
    private $conn;

    public function connect() {
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
            return $this->conn;
        } catch (Exception $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            return false;
        }
    }

    public function close() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
?>
