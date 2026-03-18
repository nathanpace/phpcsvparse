<?php
/**
 * clsParser.php
 *
 * Class file for PHP CSV Parser project.
 * Contains parsing functions and methods for the project.
 *
 * @author Nathan Pace
 */

class clsParser
{
    private array $headerRow = [];
    private array $parsedData = [];
    private array $parseErrors = [];

    /**
     * Parse a CSV file and populate parsed rows.
     * Also checks for file existence, valid CSV format, existence of data rows.
     * Handles any exceptions during file reading.
     *
     * @param string $filename Path to input CSV file.
     *
     * @throws Exception If file does not exist, is not a valid CSV, contains no data, or if there are issues reading the file.
     */
    public function parseFile(string $filename): void
    {
        // Initial file and data validity checks
        if (!file_exists($filename)) {
            throw new Exception('File not found: ' . $filename . ". Please provide a valid filename using the --file argument.");
        }

        if (mime_content_type($filename) !== 'text/csv') {
            throw new Exception('Invalid file type: ' . $filename . ". Please provide a valid CSV file.");
        }

        try {
            $rows = array_map('str_getcsv', file($filename));
        } catch (Exception $e) {
            throw new Exception('Unable to read file: ' . $e->getMessage() . ". Please ensure the file is a valid CSV and is readable.");
        }

        if ($this->isEmpty($rows)) {
            throw new Exception('File: ' . $filename . " is empty. Please provide a non-empty CSV file.");
        }

        // At this point, we have a valid CSV file with at least one row, so we can proceed with parsing.

        // Isolate header row from rest of data (assume the header row is the first in the file)
        $this->headerRow = array_shift($rows) ?? [];

        // Check rest of file again just in case the file only had a header row and no data rows, and throw exception if so.
        if ($this->isEmpty($rows)) {
            throw new Exception('No data rows found in file: ' . $filename . ". Please provide a CSV file with at least one data row.");
        }

        // Validate that number of columns in data rows matches header row, and collect parse errors for any mismatches. 
        // Store valid rows as associative arrays.
        foreach ($rows as $row) {
            if (count($row) !== count($this->headerRow)) {
                $this->parseErrors[] = 'Row ' . (count($this->parsedData) + 2) . ' has a mismatched column count.';
                continue;
            }

            $this->parsedData[] = array_combine($this->headerRow, $row);
        }

        // Cleanse parsed data values in place (names and email normalization).
        $this->cleanseData();
    }

    /**
     * Get parsed CSV data as an array of associative rows.
     */
    public function getParsedData(): array
    {
        return $this->parsedData;
    }

    /**
     * Get the parsed CSV header row.
     */
    public function getHeaderRow(): array
    {
        return $this->headerRow;
    }

    /**
     * Output the parsed data and any parse errors to the console.
     */
    public function output(): void
    {
        echo "\nParsed CSV Data:\n";
        echo "----------------\n";
        
        // Header row
        echo implode(',', $this->headerRow) . "\n";

        // Data row(s)
        foreach ($this->parsedData as $row) {
            echo implode(',', $row) . "\n";
        }

        // Parse errors, if any
        if (!empty($this->parseErrors)) {
            echo "\nParse Errors:\n";
            foreach ($this->parseErrors as $i => $error) {
                echo ($i + 1) . ") {$error}\n";
            }
        } else {
            echo "\nNo parse errors.\n";
        }
    }

    /**
     * Cleanse parsed data values in place (names and email normalization).
     */
    private function cleanseData(): void
    {
        foreach ($this->parsedData as $index => $row) {
            foreach ($row as $key => $value) {
                if ($key === 'name' || $key === 'surname') {
                    $this->parsedData[$index][$key] = $this->cleanseName($value);
                }

                if ($key === 'email') {
                    $this->parsedData[$index][$key] = $this->cleanseEmail($index, $value);
                }
            }
        }
    }

    /**
     * Normalize a name or surname string, capitalizing first letters around apostrophes and hyphens.
     * This is a simple normalization function and may not cover all edge cases for names, 
     * but it should handle common formats such as O'Rourke or Johnson-Thompson.
     */
    private function cleanseName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $name;
        }

        $parts = explode("'", strtolower($name));
        foreach ($parts as $i => $part) {
            $subParts = explode('-', $part);
            foreach ($subParts as $j => $sub) {
                $subParts[$j] = ucfirst($sub);
            }
            $parts[$i] = implode('-', $subParts);
        }

        return implode("'", $parts);
    }

    /**
     * Validate and normalize email values; collect parse errors for invalid emails.
     */
    private function cleanseEmail(int $index, string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return $email;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->parseErrors[] = 'Row ' . ($index + 1) . ': Invalid email format: ' . $email;
            return $email;
        }

        return strtolower($email);
    }

    /**
     * Check if a value is empty.
     */
    private function isEmpty($data): bool
    {
        return empty($data);
    }
}