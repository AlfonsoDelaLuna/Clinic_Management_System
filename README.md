# Clinic Management System

This web app management system can help nurses record students, faculty and staff their health condition, and their medicine needed to remedy provided by the nurse. The system can also save their patient record via file format like pdf.

## Process Before

The patient enters the clinic for an emergency like high fever or an accident within the school premises. The nurse will do an analysis like what was the problem, their condition, and the medicine needed to prevent the same scenario happening again. After that, the patient is resting on their clinic. The nurse will write down in a paper board like the patients name time in, time out, health condition, medicine used, preview health problems and a signature from the patient if it was fully rested. At the end of the day, the nurse stores the data in a storage drawer to use them as inverntory for the school and buy medicine for aditional storage. 

## Problem Encounter

The issue arise are the following: 1. Organized patient data - 

2. Transfering data from paper based to digital -
   
3. Easier inventory - 

## Objective 



## Files

- `clinic_db.sql`: This file contains the database schema for the clinic management system.
- `style.css`: CSS file for styling the application.
- `admin_history.php`: Displays the history of admin actions.
- `admin_inventory.php`: Manages the inventory of the clinic.
- `admin_loginsheet.php`: Handles admin login functionality.
- `check_name.php`: Checks if a name is available.
- `check_student.php`: Checks if a student exists.
- `clear_dashboard.php`: Clears the dashboard.
- `db_connect.php`: Contains the database connection details.
- `delete_item.php`: Deletes an item from the inventory.
- `edit_history.php`: Allows editing of the admin history.
- `export_inventory_excel.php`: Exports the inventory to an Excel file.
- `fetch_clinic_logs.php`: Fetches clinic logs.
- `guest_confirmation.php`: Confirms guest details.
- `guest_loginsheet.php`: Handles guest login functionality.
- `hash.php`: Handles password hashing.
- `index.php`: The main entry point of the application.
- `login.php`: Handles user login functionality.
- `logout.php`: Handles user logout functionality.
- `print_preview.php`: Generates a print preview.
- `process_add_entry.php`: Processes adding a new entry.
- `process_loginsheet.php`: Processes the login sheet.
- `process_update.php`: Processes updates to the system.

## Database Setup

1. Import the `clinic_db.sql` file into your MySQL database.
2. Update the database connection details in `db_connect.php` with your database credentials.

## Dependencies

This project uses Composer, a dependency manager for PHP, to manage dependencies. The dependencies are listed in the `composer.json` file. Composer also uses the `vendor` directory to store the installed packages and the autoloader.

To install the dependencies, run:

```

## Running the Application

1. Run `composer install` to install the dependencies.
2. Start your PHP server.
3. Navigate to the project directory in your browser.
composer install
```
