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
    private array $headerRow = []; // CSV file header row, stored separately for easy access and reference.
    private array $dataRows = [];  // CSV file data rows, as an array of associative arrays. Keys are column names from header row.


    private array $cleansedData = [];  // Parsed and cleansed data rows, as an array of associative arrays.
    private array $dirtyData = [];     // Data rows that could not be parsed successfully due to mismatched column counts, stored as raw arrays for reference and error reporting.
    private array $duplicateData = []; // Data rows that were identified as duplicates during parsing, stored as associative arrays for reference and error reporting.
    
    private array $parseErrors = [];   // Parse errors encountered (mismatched columns, invalid email)

    // Acceptable file mime types
    private array $allowedMimeTypes = [
        'text/csv',
        'text/plain', 
    ];

    /**
     * Parse a CSV file and populate parsed rows.
     *
     * Reads CSV with fopen/fgetcsv, validates header and row length,
     * tracks dirty rows, and then cleanses parsed data.
     *
     * @param string $filename Path to input CSV file.
     * @throws Exception If the file is missing, wrong type, unreadable, missing header, or missing data rows.
     */
    public function parseFile(string $filename): void
    {
        // Check file exists, is of an acceptable mime type, and can be opened
        if (!file_exists($filename)) {
            throw new Exception('File not found: ' . $filename . ". Please provide a valid filename using the --file argument.");
        }

        $mimeType = mime_content_type($filename) ?: '';
        if (!in_array($mimeType, $this->allowedMimeTypes, true)) {
            throw new Exception('Invalid file type for file ' . $filename . ". Please provide a valid CSV file.");
        }

        $handle = fopen($filename, 'r');
        if ($handle === false) {
            throw new Exception('Unable to open file: ' . $filename . ". Please ensure it is readable.");
        }

        // Assume the first row contains the column headers, isolate into separate variable
        // Also sanity check for blank clumn headers
        $header = fgetcsv($handle);
        if ($header === false || empty($header) || count(array_filter($header, fn($v) => $v !== null && $v !== '')) === 0) {
            fclose($handle);
            throw new Exception('No valid CSV header row found in file: ' . $filename);
        }
        $this->headerRow = $header;

        // Start getting remaining rows into object
        // Check for empty rows and mismatched column counts against header
        // Assign rows to multi-dimensional array keyed on column name if succesful, also add a row number
        // Close file once process is complete
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($row === [null] || empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            if (count($row) !== count($this->headerRow)) {
                $this->parseErrors[] = 'Row ' . $rowNumber . ' has a mismatched column count.';
                $this->dirtyData[] = $row;
                continue;
            }

            $assoc = array_combine($this->headerRow, $row);
            if ($assoc === false) {
                $this->parseErrors[] = 'Row ' . $rowNumber . ' could not be combined with header columns.';
                $this->dirtyData[] = $row;
                continue;
            }

            $assoc['_rowNumber'] = $rowNumber;
            $this->dataRows[] = $assoc;
        }

        fclose($handle);

        if (empty($this->dataRows)) {
            throw new Exception('No data rows found in file: ' . $filename . ". Please provide a CSV file with at least one data row.");
        }

        // At this stage, all data has been extracted from the file so it can now be cleansed
        $this->cleanseData();
    }

    /**
     * Get parsed CSV data rows as an array of associative arrays.
     *
     * @return array Parsed rows keyed by header columns.
     */
    public function getDataRows(): array
    {
        return $this->dataRows;
    }

    /**
     * Get 'dirty' data from CSV
     *
     * @return array Stored "dirty" rows
     */
    public function getDirtyData(): array
    {
        return $this->dirtyData;
    }

    /**
     * Get cleansed data rows.
     *
     * @param bool $matchHeaderOrder If true, return in original header order.
     * @return array Cleansed rows.
     */
    public function getCleansedData($matchHeaderOrder = false): array
    {
        // If data is to be displayed in the same column order as the original file,
        // reformat here if flag has been set
        // Otherwise, return data without reformatting 
        if ($matchHeaderOrder) {
            $headerRow = $this->getHeaderRow();
            $reformattedData = [];
            foreach ($this->cleansedData as $index => $row) {          
                foreach ($headerRow as $key) {
                    $reformattedData[$index][$key] = $row[$key];
                }
            }
            return $reformattedData;
        }
        return $this->cleansedData;
    }

    /**
     * Get the parsed CSV header row.
     *
     * @return array Header columns.
     */
    public function getHeaderRow(): array
    {
        return $this->headerRow;
    }

    /**
     * Output dry-run parse results to console.
     *
     * @return void
     */
    public function output(): void
    {
        echo "\n+-----------------------------+";
        echo "\n| CSV Parser - dry run output |";
        echo "\n+-----------------------------+\n\n";
        echo "The following cleansed rows from the CSV file would be written to the database:\n\n";
        
        // Display cleansed data row(s) - ensure values are in the same order as the header row.
        foreach ($this->getCleansedData(true) as $row) {
            echo implode(',', $row) . "\n";
        }

        // Show rows with duplicate email addresses, if any
        if (!empty($this->duplicateData)) {
            echo "\nThe following duplicate email addresses were found in the file; these rows would not be written to the database:\n\n";
            foreach ($this->duplicateData as $i => $row) {
                $rowNum = array_pop($row);
                echo "Row $rowNum: " . implode(',', $row) . "\n";
            }
        } else {
            echo "\nNo duplicate addresses found in the data.\n";
        }

        // Show parse errors, if any
        if (!empty($this->parseErrors)) {
            echo "\nThe following parse errors were found in the file; these rows would not be written to the database:\n\n";
            foreach ($this->parseErrors as $i => $error) {
                echo "$error\n";
            }
        } else {
            echo "\nNo parse errors encountered.\n";
        }
        echo "\n";
    }

    /**
     * Cleanse parsed rows (email validation + name normalization).
     *
     * @return void
     */
    private function cleanseData(): void
    {
        foreach ($this->getDataRows() as $index => $row) {

            // Get the row number - create it if it doesn't exist already
            $rowNumber = $row['_rowNumber'] ?? ($index + 2);

            // Only cleanse the name and email values if the email address has been validated
            // also, default name/email to blank if not supplied in row
            if ($this->validatedEmail($index, $row['email'] ?? '', $rowNumber)) {
                $this->cleanseValue($index, $row['name'] ?? '', 'name');
                $this->cleanseValue($index, $row['surname'] ?? '', 'surname');
            }
        }
    }

    /**
     * Validate and normalize email values for one row.
     *
     * @param int $index Data index in $dataRows.
     * @param string $email Raw email string.
     * @param int $rowNumber Original CSV row number for errors.
     * @return bool
     */
    private function validatedEmail(int $index, string $email, int $rowNumber): bool
    {
        // Check if email is empty, email address format is valid, and email address does not already exist in the file
        $email = trim($email);

        if ($email === '') {
            $this->parseErrors[] = 'Row ' . $rowNumber . ': Email field is empty.';
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->parseErrors[] = 'Row ' . $rowNumber . ': Invalid email format: ' . $email;
            return false;
        }

        $email = strtolower($email);

        // Only the first instance of an email address (with name and surname) will be stored;
        // all subsequent matching entries will be ignored
        if (array_search($email, array_column($this->cleansedData, 'email'), true) !== false) {
            $this->duplicateData[] = $this->dataRows[$index];
            return false;
        }

        // Email has passed all checks at this stage, so add to cleansed data array
        $this->cleansedData[$index]['email'] = $email;
        return true;
    }

    /**
     * Normalize a name/surname string (capitalizing after apostrophes/hyphens).
     *
     * @param int $index Row index for cleansedData writing.
     * @param string $data Raw name/surname value.
     * @param string $key Field key ('name' or 'surname').
     * @return void
     */
    private function cleanseValue(int $index, string $data, string $key): void
    {
        // This will correct hyphenated names and names with apostophes
        // o'rOUrKe => O'Rourke
        // wEbb-eLLis => Webb-Ellis
        // sMith-o'gRADy =? Smith-O'Grady
        $data = trim($data);
        if ($data === '') {
            $this->cleansedData[$index][$key] = $data;
            return;
        }

        $parts = explode("'", strtolower($data));
        foreach ($parts as $i => $part) {
            $subParts = explode('-', $part);
            foreach ($subParts as $j => $sub) {
                $subParts[$j] = ucfirst($sub);
            }
            $parts[$i] = implode('-', $subParts);
        }

        $this->cleansedData[$index][$key] = implode("'", $parts);
    }
}