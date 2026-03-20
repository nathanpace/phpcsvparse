# PHP CSV Parser

A small CLI PHP parser that reads a CSV files row-by-row, validates email addresses, normalizes names, and detects and removes duplicates.  
Cleansed data is stored in a PostgreSQL database, connection details of which need to be passed command line arguments.
A "dry-run" can also be performed, either against the file alone or against the database to ascertain which rows in the file would be inserted or ignored.

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
```bash
php user_upload.php --file path/to/import.csv --dry_run
```
You can also add database paramters here to perform a dry run of the file against the database; this will check if a row's email address already exists in the database
```bash
php user_upload.php --file path/to/import.csv -h <database host> -u <database username> -p <database password> --dry_run
```

A basic "help" and usage message can be display in STDOUT by running either of these commands:
```bash
php user_upload.php
php user_upload.php --help
```

## Assumptions/Notes
- The database has been configured with a user and password which has permissions to create tables and insert data - these will need to be passed (along with the host) on the command line as per the usage instructions above
- The supplied CSV file has a header row as its first row, which matches the database fields the data is to be inseerted into
- When performing a "dry run" against the database, the script will do a small SELECT action for each email address in the supplied data.

## Possible future improvements?
- Allow more flexibility when creating the storage table in the DB (eg. table name/structure)
