<?php
/**
 * user_upload.php
 * 
 * Main command-line script for PHP CSV Parser project
 * 
 * @author Nathan Pace
 * 
 */

// Include required parser functions
include_once("./includes/clsParser.php");

// Include database connection functions
include_once("./includes/clsDB.php");

// Initiate expected arguments and formats
$argsShort = "u:p:h:";
$argsLong = ["file:","create_table","dry_run","help"];

// Get supplied command line arguments
$args = getopt($argsShort, $argsLong);

// Determine what to do based on args passed. 
// Order is important here to avoid conflicts. For example, if "help" is passed, we want to show the help message regardless of what other args are passed.
try {

     // Show help message
    if (isset($args["help"])) {
        showHelp();
        exit(0);
    }

    // Check filename arg, throw exception if not present
    if (empty($args["file"])) {
        throw new Exception("No filename supplied, exiting. Please provide a filename using the --file argument.");
    }

    // Filename supplied, so instantiate parser and attempt to parse file.
    $parser = new clsParser();
    $parser->parseFile($args["file"]);

    // Output data to console if dry run flag is set, and exit without attempting to connect to database or upload data.
    if (isset($args["dry_run"])) {
        $parser->output();
        exit(0);
    }

    // Check supplied DB parameters, and attempt connection if all are present.
    // If any are missing, throw exception.
    if (empty($args["u"]) || empty($args["p"]) || empty($args["h"])) {
        throw new Exception("Missing database parameters, exiting. Please provide all of username, password, and host.");
    }
    $db = new clsDB($args["u"], $args["p"], $args["h"]);

    // Create table if flag is set, and perform no other actions
    if (isset($args["create_table"])) {
        $db->createTable();
        exit(0);
    }

    // Attempt to insert parsed data into database
    $db->insertData($parser->getParsedData());

} catch (Exception $e) {
    echo $e->getMessage() . "\n";
}

/**
 * Display command-line usage instructions and options.
 *
 */
function showHelp(): void 
{
    echo <<<EOT
PHP CSV Parser - user_upload.php
-------------------------------
A command-line script to parse a CSV file and upload its contents to a database.

Usage: php user_upload.php --file=filename.csv [--create_table] [--dry_run] [-u username] [-p password] [-h host] [--help]

Options:
--file: Required. The CSV file to parse and upload to the database.
--create_table: Optional. If set, the script will create the database table and exit without parsing or uploading any data. Database parameters must be specified.
--dry_run: Optional. If set, the script will parse the file and output the parsed data to the console without uploading to the database.
-u username: Optional. The username for the database connection. Must be specified if a dry run is not being performed, or if --create_table is specified.
-p password: Optional. The password for the database connection. Must be specified if a dry run is not being performed, or if --create_table is specified.
-h host: Optional. The host for the database connection. Must be specified if a dry run is not being performed, or if --create_table is specified.
--help: Optional. If set, the script will display this help message and exit.

EOT;
}