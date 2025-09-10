# Clinic Management System

This web app management system can help nurses record students, faculty and staff their health condition, and their medicine needed to remedy provided by the nurse. The system can also save their patient record via file format like pdf.

## Process Before

The patient enters the clinic for an emergency like high fever or an accident within the school premises. The nurse will do an analysis like what was the problem, their condition, and the medicine needed to prevent the same scenario happening again. After that, the patient is resting on their clinic. The nurse will write down in a paper board like the patients name time in, time out, health condition, medicine used, preview health problems and a signature from the patient if it was fully rested. At the end of the day, the nurse stores the data in a storage drawer to use them as inventory for the school and buy medicine for additional storage. 

## Problem Encounter

The issue arise are the following: 

1. Manual Documentation
   
•	Writing details on a paper board is time-consuming.
•	Handwritten records can be prone to errors, illegibility, and loss/damage.

2. Data Storage Issues
•	Storing papers in a drawer risks misplacement, damage (fire, water, pests), or difficulty in retrieving records.
•	No backup system in case the physical records are lost.

3. Inventory Tracking Problems
•	Medicines are not tracked in real-time, which may cause shortages or overstock.
•	Lack of systematic monitoring of expiration dates.

4. Delayed Response to Emergencies
•	Nurse spends time writing records instead of focusing immediately on patient care.
•	Searching old records for past medical history is slow.

5. Limited Data Analysis
•	Difficult to analyze trends in illnesses, accidents, or medicine usage since everything is on paper.
•	Harder to justify budget requests for additional medicine without proper reports.

6. Privacy and Confidentiality Concerns
•	Paper records stored in drawers may not be secure, leading to potential breaches of student health information.

7. Dependence on Nurse’s Availability
•	If the nurse is absent, accessing records or continuing proper documentation may be disrupted.



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
