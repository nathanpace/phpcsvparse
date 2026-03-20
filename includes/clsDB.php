<?php
/**
 * clsDB.php
 *
 * Class file for PHP CSV Parser project.
 * Contains database-related functions and methods for the project.
 *
 * @author Nathan Pace
 */

class clsDB
{
    // Database params
    private string $username;
    private string $password;
    private string $host;
    private string $dbname;
    private int $port;

    // PDO object. Needs to be instantiated before used 
    private ?\PDO $pdo = null;

    // These values match the error codes returned by Postgres based on the relevant SQL error
    const TABLE_NOT_EXIST = '42P01';
    const CONNECTION_FAIL = '08006';
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
     * 
     * @return void
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
                echo "Users table created successfully.\n";
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
     * 
     * @return void
     */
    private function dropTable(): void
    {
        $sql = 'DROP TABLE IF EXISTS users';
        try {
            $pdo = $this->getPdo();
            $pdo->exec($sql);
            echo "Users table dropped successfully.\n";
        } catch (\PDOException $e) {
            $this->handlePdoException($e);
        }
    }

    /**
     * Checks to see if the users table already exists by running simple query
     * 
     * @return bool If table exists or not
     */
    private function tableExists(): bool
    {
        try {
            $pdo = $this->getPdo();
            $pdo->query('SELECT 1 FROM users LIMIT 1');
            return true;
        } catch (\PDOException $e) {
            // PDO exception thrown; check the error code and return false if it matches the correct constant
            // Pass any other exception to exception handling function
            if ($e->errorInfo[0] === self::TABLE_NOT_EXIST) {
                return false;
            } else {
                $this->handlePdoException($e);
            }
        }
    }

    /**
     * Attempts to insert the supplied data rows into the database.
     * Rows with duplicate email addresses are ignored; a message is written to stdout instead.
     * 
     * @param array $data data to be inserted into the databse
     * @return @void
     */
    public function insertData(array $data): void
    {
        // Keep count of number of entries to be inserted, number of successful inserts, 
        // and number of duplicate email addresses
        $entries = count($data);
        $inserts = 0;
        $duplicates = 0;

        echo "Number of entries to insert: " . $entries . "\n\v";

        $sql = 'INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email)';
        
        try {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare($sql);

            // Try/catch block round each row so duplicates can be handled 
            foreach ($data as $row) {
                try {
                    $stmt->execute([
                        ':name' => $row['name'],
                        ':surname' => $row['surname'],
                        ':email' => $row['email'],
                    ]);
                    $inserts++;
                } catch (\PDOException $e) {
                    // PDO exception thrown; check the error code, increment duplicate counter if code matches correct constant,
                    // and carry on processing any remaining rows
                    // Pass any other exception to exception handling function
                    if ($e->errorInfo[0] === self::DUPLICATE_ENTRY) {
                        echo "Email address " . $row['email'] . " already exists in the database, ignoring.\n";
                        $duplicates++;
                        continue;
                    } else {
                        $this->handlePdoException($e);
                    }
                }
            }

            echo "\nProcess complete. Inserts: " . $inserts . ". Duplicates: " . $duplicates . "\n";
        } catch (\PDOException $e) {
            $this->handlePdoException($e);
        }
    }

     /**
     * Do a "dry-run" of inserting the data into the DB
     * No data will be inserted but email addresses will be checked for existence in the DB
     * 
     * @param array $data data to be checked
     * @return @void
     */
    public function dryRun(array $data): void
    {
        // Cnumber of entries to be inserted, and store inserts and ignores
        $entries = count($data);
        $inserts = [];
        $ignores = [];

        echo "+------------------------------------------------+\n";
        echo "| Now dry-running data inserts against database. |\n";
        echo "+------------------------------------------------+\n";
        echo "Number of inserts to attempt: $entries\n\n";

        // No row will be returned from this query if the email address does not exist.
        $sql = 'SELECT 1 AS "exists" FROM users WHERE email = :email';
        
        try {
            $pdo = $this->getPdo();
            $stmt = $pdo->prepare($sql);

            foreach ($data as $id => $row) {

                $stmt->execute([
                    ':email' => $row['email'],
                ]);
                $found = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                // False return value = no row found, so assume row can be inserted
                if ($found === false) {
                    $inserts[$id] = implode(",", $row);
                } else {
                    $ignores[$id] = implode(",", $row);
                }
            }
        } catch (\PDOException $e) {
            $this->handlePdoException($e);
        }

        // Output information on inserts/duplicates to screen
        
        if (count($inserts) > 0) {
            echo "These rows would be successfully inserted:\n";
            echo "-----------------------------------------\n";
            foreach ($inserts as $id => $data) {
                echo "Row " . $id+2 . ": {$data}\n";
            }
            echo "\n";
        } 
 
        if (count($ignores) > 0) {
            echo "These rows would NOT be inserted as the email addresses already exist in the DB:\n";
            echo "--------------------------------------------------------------------------------\n";
            foreach ($ignores as $id => $data) {
                echo "Row " . $id+2 . ": {$data}\n";
            }
        } else {
            echo "No email addresses in the data already exist in the database, so all data should be inserted ";
        }
        echo "\n";
    }

    /**
     * Checks to see if the PDO conection has been established
     * 
     * @throws Exception if the PDO has not been created
     * 
     * @return \PDO the PDO connection
     */
    private function getPdo(): \PDO
    {
        if ($this->pdo === null) {
            throw new \Exception("Database connection failed.");
        }
        return $this->pdo;
    }

    /**
     * Attempt to establish the DB connection
     * 
     * @return void
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
     * 
     * @param object $e the exception object
     * @throws Exception Depending on error code, throw exception
     * 
     * @return void
     */
    private function handlePdoException(object $e): void
    {
        switch ($e->errorInfo[0]) {
            case self::CONNECTION_FAIL:
                throw new \Exception("Could not connect to database, please check host/username/password details.");
            default:
                throw new \Exception("Database error: " . $e->getMessage());
        }
    }
}