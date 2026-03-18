<?php
/**
 * clsDB.php
 * 
 * Class file for PHP CSV Parser project.
 * Contains database connection functions and methods for the project.
 * 
 * @author Nathan Pace
 * 
 */

class clsDB 
{
    private string $username;
    private string $password;
    private string $host;
    private string $dbname;
    private int $port;

    private \PDO $pdo;

    public function __construct(array $params)
    {
        $this->username = $params['username'];
        $this->password = $params['password'];
        $this->host = $params['host'];
        $this->dbname = $params['dbname'] ?? 'postgres';
        $this->port = $params['port'] ?? 5432;

        $this->connect();
    }

    public function createTable(): void
    {
        // Implementation for creating table in the database

    }

    public function insertData(array $data): void
    {
        // Implementation for inserting data into the database

    }


    private function connect() 
    {

        try {
            $conStr = sprintf("pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s",
                $this->host,
                $this->port,
                $this->dbname,
                $this->username,
                $this->password);

            $this->pdo = new \PDO($conStr);

            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        }
        catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

}