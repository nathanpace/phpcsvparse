<?php
/**
 * user_upload.php
 * 
 * Main command-line script for PHP CSV Parser project
 * 
 * @author Nathan Pace
 * 
 */

// Include required parserfunctions
include_once("./includes/clsParser.php");

// Include database connection functions
include_once("./includes/clsDB.php");

// Initiate expected arguments and formats
$args_short = "u:p:h:";
$args_long = ["file:","create_table","dry_run","help"];

// Get args from command line
$args = getopt($args_short, $args_long);


try {
    // Determine what to do based on args passed. 
    // Order is important here to avoid conflicts. For example, if "help" is passed, we want to show the help message regardless of what other args are passed.
    
    if (isset($args["help"])) {
        // Show help message
    }

    if (empty($args["file"])) {
        // No filename supplied, throw exception
        throw new Exception("No filename supplied");
    }

    // Set dry run flag if set
    $dry_run = isset($args["dry_run"]);

    // Attempt database conection
    if (empty($args["u"]) || empty($args["p"]) || empty($args["h"])) {
        throw new Exception("Missing database parameters. Please provide username, password, and host.");
    }

    $db = new clsDB($args["u"], $args["p"], $args["h"]);

    if (isset($args["create_table"])) {
        // Create table if flag is set, and perform no other actions
        $db->createTable();
        exit(0);
    }

    // At this point, we have a filename, and a database connection. 
    // Attempt file parsing and database insertion, unless dry run flag is set, in which case we will only parse the file and output the results.

} catch (Exception $e) {
    echo("Exception thrown: " . $e->getMessage());
}