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
        if ($this->tableExists() === false) {

            $sql = 'CREATE TABLE IF NOT EXISTS users (
                        name character varying(255),
                        surname character varying(255),
                        email character varying(255) NOT NULL UNIQUE
                    )';

            try {
                $this->pdo->exec($sql);
                echo "Table 'users' created successfully.\n";
            } catch (PDOException $e) {
                throw new Exception("Could not create 'users' table: " . $e->getMessage() . "\n");
            }
        } else {
            echo "Users table already exists. Drop and rebuild? [Y/n] ";
            $input = rtrim(fgets(STDIN));
            
            if ($input === 'Y') {
                $this->dropTable();
                $this->createTable();
            } 
        }
        
    }

    private function dropTable()
    {
        $sql = 'DROP TABLE IF EXISTS users';

        try {
            $this->pdo->exec($sql);
            echo "Table 'users' dropped successfully.\n";
        } catch (PDOException $e) {
            throw new Exception("Could not drop 'users' table: " . $e->getMessage() . "\n");
        }
    }

    private function tableExists()
    {
        $sql = 'SELECT 1 FROM users';
        try {
            $this->pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function insertData(array $data): void
    {
        // Use ON CONFLICT to ensure duplicate email addresses are not stored
        $sql = 'INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email) ON CONFLICT (email) DO NOTHING';

        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($data as $row) {
                $stmt->execute([
                    ':name' => $row['name'],
                    ':surname' => $row['surname'],
                    ':email' => $row['email']
                ]);
            }
            echo "Data inserted successfully.\n";
        } catch (PDOException $e) {
            throw new Exception("Could not insert data: " . $e->getMessage() . "\n");
        }
    }


    private function connect() 
    {

        try {
            $dsn = sprintf("pgsql:host=%s;port=%d;dbname=%s",
                $this->host,
                $this->port,
                $this->dbname
            );
            $this->pdo = new \PDO($dsn, $this->username, $this->password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);

        }
        catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage() . "\n");
        }
    }

}