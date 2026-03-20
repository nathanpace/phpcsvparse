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
// Order is important here; for example, if "help" is passed, show the help message regardless of what other args are passed.
try {

     // Show help message if no args submitted or the --help flag has been set
    if (count($args) === 0 || isset($args["help"])) {
        showHelp();
        exit(0);
    }

    // Check filename has been supplied
    if (!empty($args["file"])) {

        // Check if a dry run is requested
        if (isset($args["dry_run"])) {
            $parser = new clsParser();
            $parser->parseFile($args["file"]);
            $parser->output();

            // If database params have been supplied, dry-run the database process too.
            if (!empty($args["u"]) && !empty($args["p"]) && !empty($args["h"])) {
                $db = new clsDB([
                    "username" => $args["u"],
                    "password" => $args["p"],
                    "host" => $args["h"],
                ]);
                $db->dryRun($parser->getCleansedData(true));
            }
            exit(0);
        }
    } else {
        // Throw filename missing exception if no filename and create table has not been specified
        if (!isset($args["create_table"])) {
            throw new Exception("No filename supplied. Please provide a filename using the --file argument, or --create_table to create the users table");
        }
    }


    // Check supplied DB parameters; if all required are present, attempt connection.
    // If any are missing, throw exception.
    if (empty($args["u"]) || empty($args["p"]) || empty($args["h"])) {
        throw new Exception("Missing database parameters. Please provide username, password, and host.");
    }
    
    $db = new clsDB([
        "username" => $args["u"],
        "password" => $args["p"],
        "host" => $args["h"],
    ]);

    // Create table if flag is set, and perform no other actions
    if (isset($args["create_table"])) {
        $db->createTable();
        exit(0);
    }

    // Mising file should have been handled above, sanity check here
    if (empty($args["file"])) {
        throw new Exception("No filename supplied. Please provide a filename using the --file argument, or --create_table to create the users table.");
    }

    // Attempt to insert parsed data into database
    $parser = new clsParser();
    $parser->parseFile($args["file"]);
    $db->insertData($parser->getCleansedData());
    exit(0);
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
--dry_run: Optional. If set, the script will parse the file and output the parsed data to the console. Database parameters can be optionally added here for a dry run of database actions.
-u username: Optional. The username for the database connection. Must be specified if --create_table is specified; optional if --dry_run is specified.
-p password: Optional. The password for the database connection. Must be specified if --create_table is specified; optional if --dry_run is specified.
-h host: Optional. The host for the database connection. Must be specified if --create_table is specified; optional if --dry_run is specified.
--help: Optional. If set, the script will display this help message and exit.

EOT;
}