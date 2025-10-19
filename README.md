# Clinic Management System
A web-based Clinic Management System is designed to help school nurses efficiently record and manage the health information of students, faculty, and staff. It replaces the manual paper-based process with a secure and streamlined digital solution, allowing for better patient care, inventory management, and data analysis.

## The Problem
The traditional process of using paper boards and storage drawers for clinic records presents several challenges:

1.  **Manual Documentation:**  - Time-consuming, prone to errors, and records can be lost or damaged.
2.  **Data Storage Issues:** - Physical records are difficult to retrieve, lack backups, and are vulnerable to damage.
3.  **Inventory Tracking Problems:** - Inefficient tracking of medicine stock and expiration dates, leading to shortages or waste.
4.  **Delayed Response to Emergencies:** - Slow access to patient history hinders immediate care.   
5.  **Limited Data Analysis:** - Difficulty in identifying health trends and justifying budget needs.
6.  **Privacy Concerns:** - Paper records lack the security required for confidential health information.
7.  **Dependence on Nurse’s Availability:** - Access to records is disrupted if the nurse is absent.

## Objective
The primary objective of this system is to digitize and automate the clinic's daily operations to solve the problems listed above. Key goals include:

-   **Improve Efficiency:** 
    - Reduce the time spent on manual documentation, allowing the nurse to focus on patient care.
    
-   **Enhance Data Security:** 
    - Securely store patient records in a database with controlled access.
    
-   **Streamline Inventory Management:** 
    - Implement real-time tracking of medicine supplies and expiration dates.
    
-   **Enable Data-Driven Decisions:** 
    - Provide tools for generating reports and analyzing health trends.
    
-   **Ensure Continuity of Care:** 
    - Make records easily accessible to authorized personnel when needed.

## Features
-   **User Authentication:** 
    - Secure login for administrators (nurse) and guests (patients).

-   **Patient Log Management:** 
    - A digital log sheet to record patient visits, symptoms, and treatments.
    
-   **Medical History Tracking:** 
    - Easily access the complete medical history of any patient.
    
-   **Inventory Management:** 
    - Track medicine quantities, manage stock, and monitor expiration dates.
    
-   **Data Export:** 
    - Export inventory and patient logs to Excel for reporting and analysis.
    
-   **Print Preview:** 
    - Generate print-friendly versions of patient records.

## Technologies Used
-   **Frontend:** HTML, CSS, JavaScript
-   **Backend:** PHP
-   **Database:** MySQL
-   **Dependencies:** [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/) for Excel exporting (managed via Composer).

## Setup and Installation
Follow these steps to get the application running on your local machine.

### Prerequisites
-   A web server environment like XAMPP or WAMP.
-   [Composer](https://getcomposer.org/) for managing PHP dependencies.

### 1. Database Setup
1.  Create a new database in your MySQL server (e.g., via phpMyAdmin).
2.  Import the `clinic_db.sql` file into the newly created database. This will set up the required tables.
3.  Open `db_connect.php` and update the database credentials (`$servername`, `$username`, `$password`, `$dbname`) to match your environment.

### 2. Install Dependencies
1.  Open a terminal or command prompt in the project's root directory.
2.  Run the following command to install the required PHP packages:
    ```bash
    composer install
    ```

### 3. Running the Application
1.  Move the entire project folder into your web server's root directory (e.g., `htdocs` for XAMPP).
2.  Start your Apache and MySQL services from your server's control panel.
3.  Open your web browser and navigate to `http://localhost/your-project-folder-name` (e.g., `http://localhost/Clinic_Management_System`).

## File Descriptions
-   `index.php`: The login page and main entry point of the application.
-   `login.css`: Main stylesheet for the login page and dashboard.
-   `db_connect.php`: Handles the connection to the MySQL database.
-   `admin_*.php`: A collection of files for the administrator dashboard (history, inventory, loginsheet).
-   `guest_*.php`: Files related to the guest/patient login and confirmation process.
-   `process_*.php`: PHP scripts that handle form submissions and data processing.
-   `export_inventory_excel.php`: Generates and downloads an Excel file of the current inventory.
-   `clinic_db.sql`: The database schema file.
-   `composer.json` / `composer.lock`: Define the project dependencies.
-   `vendor/`: Directory where Composer installs the dependencies.
