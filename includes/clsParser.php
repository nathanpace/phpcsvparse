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

    private $parsedData;

    public function parseFile($filename) {
        // Check if file exists
        if (!file_exists($filename)) {
            throw new Exception("File not found: " . $filename);
        }

        // Attempt to open file
        $handle = fopen($filename, "r");
        if ($handle === false) {
            throw new Exception("Unable to open file: " . $filename);
        }

        // Parse CSV data into array
        $data = [];
        while (($row = fgetcsv($handle)) !== false) {
            $data[] = $row;
        }

        // Close file handle
        fclose($handle);

        $this->parsedData = $data;
    }

    public function getParsedData() {
        return $this->parsedData;
    }

    public function output() {
        // Output parsed data to console
        print_r($this->getParsedData());
    }

}