<?php
class clsDB
{
    private string $username;
    private string $password;
    private string $host;
    private string $dbname;
    private int $port;

    private ?\PDO $pdo = null;

    // These values match the error codes returned by Postgres based on the relevant SQL error
    const TABLE_NOT_EXISTS = '42P01';
    const INVALID_USER_PASS = '08006';
    const DUPLICATE_ENTRY = '23505';

    /**
     * Class constructor - assign class variables and attempt connection
     */
    public function __construct(array $params)
    {
        $this->username = $params['username'];
        $this->password = $params['password'];
        $this->host = $params['host'];
        $this->dbname = $params['dbname'] ?? 'postgres';
        $this->port = $params['port'] ?? 5432;
        $this->connect();
    }

    /**
     * Attempt to create the user's table. 
     * If the table already exists, prompt user for confirmation that the table is to be dropped and rebuilt
     */
    public function createTable(): void
    {
        if ($this->tableExists() === false) {
            $sql = 'CREATE TABLE IF NOT EXISTS users (
                        name character varying(255),
                        surname character varying(255),
                        email character varying(255) NOT NULL UNIQUE
                    )';
            try {
                $pdo = $this->getPdo();
                $pdo->exec($sql);
                echo "Table 'users' created successfully.\n";
            } catch (\PDOException $e) {
                $this->handlePdoException($e);
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

    /**
     * Drops the users table if it exists
     */
    private function dropTable(): void
    {
        $sql = 'DROP TABLE IF EXISTS users';
        try {
            $pdo = $this->getPdo();
            $pdo->exec($sql);
            echo "Table 'users' dropped successfully.\n";
        } catch (\PDOException $e) {
            $this->handlePdoException($e);
        }
    }

    /**
     * Checks to see if the users table already exists
     */
    private function tableExists(): bool
    {
        try {
            $pdo = $this->getPdo();
            $pdo->query('SELECT 1 FROM users LIMIT 1');
            return true;
        } catch (\PDOException $e) {
            if ($e->errorInfo[0] === self::TABLE_NOT_EXISTS) {
                return false;
            }
            $this->handlePdoException($e);
        }
        return false;
    }

    /**
     * Attempts to insert the supplied data into the database
     */
    public function insertData(array $data): void
    {
        // TODO: insert count, proper handling of duplicate entries
        $sql = 'INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email) ON CONFLICT (email) DO NOTHING';
        try {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare($sql);
            foreach ($data as $row) {
                $stmt->execute([
                    ':name' => $row['name'],
                    ':surname' => $row['surname'],
                    ':email' => $row['email'],
                ]);
            }
            echo "Data inserted successfully.\n";
        } catch (\PDOException $e) {
            $this->handlePdoException($e);
        }
    }

    /**
     * Checks to see if the PDO has been created, throw exception if it hasn't.
     */
    private function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            throw new \Exception("Database connection failed.");
        }
        return $this->pdo;
    }

    /**
     * Attempt to make the DB connection
     */
    private function connect(): void
    {
        try {
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $this->host, $this->port, $this->dbname);
            $this->pdo = new \PDO($dsn, $this->username, $this->password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\PDOException $e) {
            $this->handlePdoException($e);
        }
    }

    /**
     * Wrapper function around exceptions thrown by PDO
     */
    private function handlePdoException(object $e): void
    {
        switch ($e->errorInfo[0]) {
            case self::TABLE_NOT_EXISTS:
                throw new \Exception("Users table does not exist, please re-run script with --create-table switch set");
            case self::INVALID_USER_PASS:
                throw new \Exception("Invalid username/password supplied, please check.");
            case self::DUPLICATE_ENTRY:
                throw new \Exception("Duplicate email address in DB, ignoring");
            default:
                throw new \Exception("Database error: " . $e->getMessage());
        }
    }
}