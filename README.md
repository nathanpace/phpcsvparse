# PHP CSV Parser

A small CLI PHP parser that reads CSV files row-by-row, validates email addresses, normalizes names, detects duplicates, and prints dry-run output.  
Cleansed data is stored in a PostgreSQL database, connection details of which need to be passed command line arguments.

## Requirements
The following requirements are needed to run this parser:

- Ubuntu (although this should run fine on any platform as long as PHP is available at the command line)
- PHP 8.3
- PostgreSQL (at least version 13)
- `user_upload.php` and all files in the `includes/` directory in the project root
- CSV file with header row (`name,surname,email`) - this does not need to be in the same directory as the script, but needs to be readable

No extra third-party packages are required in order to use this script, it is designed to be a stand alone solution.

## Usage

To perform an upload, run the following at a terminal prompt:
```bash
php user_upload.php --file path/to/input.csv -h <database host> -u <database username> -p <database password>
```

Create the database table before importing data by running this command:  
(If, on running this command, the table already exists, it can be dropped and re-built if required).
```bash
php user_upload.php -h <database host> -u <database username> -p <database password> --create_table
```

You can perform a "dry run" of the import by running the following command:  
The result of the process will be shown to STDOUT, showing cleansed rows of data, duplicate email addresses and email addresses with invalid formats.  
(no database parameters needed as it only works on the supplied file):
```bash
php user_upload.php --file path/to/import.csv --dry_run
```

A basic "help" and usage message can be display in STDOUT by running either of these commands:
```bash
php user_upload.php
php user_upload.php --help
```

## Assumptions
- The database has been configured with a user and password which has permissions to create tables and insert data - these will need to be passed (along with the host) on the command line as per the usage instructions above
- The supplied CSV file has a header row as its first row, which matches the database fields the data is to be inseerted into
- The "dry run" will only check for duplicates in the file; it will not take into acount whether the emails already exist in the database as no dtabase parameters are required for this action 

## Possible future improvements?