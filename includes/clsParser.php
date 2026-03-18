<?php
/**
 * clsParser.php
 * 
 * Class file for PHP CSV Parser project.
 * Contains parsing functions and methods for the project.
 * 
 * @author Nathan Pace
 * 
 */


class clsParser {

    private $headerRow = [];
    private $parsedData = [];
    private $parseErrors = [];


    public function parseFile($filename) {
        if (!file_exists($filename)) {
            throw new Exception("File not found: " . $filename);
        }

        $rows = array_map('str_getcsv', file($filename));
        $this->headerRow = array_shift($rows);
        $csv = array();
        foreach ($rows as $row) {
            $this->parsedData[] = array_combine($this->headerRow, $row);
        }
        $this->cleanseData();
    }

    public function getParsedData() {
        return $this->parsedData;
    }

    public function getHeaderRow() {
        return $this->headerRow;
    }

    public function output() {
        // Output parsed data to console
        echo(implode(", ", $this->headerRow) . "\n");
        foreach ($this->parsedData as $row) {
            echo(implode(", ", $row) . "\n");
        }
        
        for ($i = 0; $i < count($this->parseErrors); $i++) {
            echo("Error " . ($i + 1) . ": " . $this->parseErrors[$i] . "\n");
        }
        
    }


    private function cleanseData() {
        foreach ($this->parsedData as $index => $row) {
            foreach ($row as $key => $value) {

                if ($key === "name" || $key === "surname") {

                    // Perform specific cleansing operations for name and surname fields, such as removing non-alphabetic characters, etc.
                    $this->parsedData[$index][$key] = $this->cleanseName($value);
                }

                if ($key === "email") {
                    $this->parsedData[$index][$key] = $this->cleanseEmail($index, $value);
                }

            }
        }
    }

    private function cleanseName($name) {
  
        // Normalize apostrophe-separated parts first
        $parts = explode("'", strtolower($name));
        foreach ($parts as $i => $part) {
            // Normalize hyphen-separated parts inside each apostrophe segment
            $subParts = explode("-", $part);
            foreach ($subParts as $j => $sub) {
                $subParts[$j] = ucfirst($sub);
            }
            $parts[$i] = implode("-", $subParts);
        }

        return implode("'", $parts);
    }

    private function cleanseEmail($index, $email) {
        // Example cleansing function for email fields
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->parseErrors[] = "Row " . ($index + 1) . ": Invalid email format: " . $email;
            return $email;
        }
        return strtolower($email);
    }

}