# LabBench Phase 4 Demo

LabBench is a PHP/MySQL web application for tracking machine learning experiment work across workspaces, projects, datasets, models, runs, run parameters, run metrics, and model registry entries.

This version is prepared for the CS 4347 Phase 4 database project demo and the Assignment #5 SQL Injection demo.

---

## 1. Application Overview

LabBench models the following workflow:

    Workspace → Project → Model → Run

The application demonstrates:

- User login and session handling
- Workspace-based project organization
- Database-connected PHP pages
- Create, read, update, and delete functionality
- Search and filtering
- Run logging
- Run parameter tracking
- Run metric tracking
- Run comparison
- Model registry review
- Audit log review
- SQL injection vulnerability demonstration
- SQL injection prevention using prepared statements

---

## 2. Technology Stack

| Layer | Technology |
|---|---|
| Front end | HTML, CSS |
| Back end | PHP |
| Database | MySQL |
| Database access | PDO / PDO_MYSQL |
| Local server option 1 | XAMPP Apache |
| Local server option 2 | PHP built-in development server |
| Data loading | SQL scripts + CSV files |

---

## 3. Required Local Tools

Install or have access to:

- XAMPP
- Apache through XAMPP Control Panel
- MySQL through XAMPP Control Panel
- PHP through XAMPP
- MySQL terminal or MySQL command line client
- Web browser

Recommended local project path:

    C:\xampp\htdocs\LabBenchTest

---

## 4. Expected Project Files

The project files should be directly inside:

    C:\xampp\htdocs\LabBenchTest

Expected structure:

    LabBenchTest/
    ├── audit_helpers.php
    ├── audit_log.php
    ├── compare_runs.php
    ├── create_project.php
    ├── create.sql
    ├── db.php
    ├── load.sql
    ├── log_run.php
    ├── login.php
    ├── logout.php
    ├── model_registry.php
    ├── project_detail.php
    ├── projects.php
    ├── run_detail.php
    ├── runs.php
    ├── sql_injection_demo.php
    ├── styles.css
    ├── users.php
    ├── workspace.php
    ├── workspace_members.php
    ├── Users.csv
    ├── Workspaces.csv
    ├── WorkspaceMembers.csv
    ├── Projects.csv
    ├── Datasets.csv
    ├── DatasetVersions.csv
    ├── Models.csv
    ├── Runs.csv
    ├── RunParams.csv
    ├── RunMetrics.csv
    ├── ModelRegistry.csv
    └── AuditLog.csv

The PHP files must not be nested inside another extracted ZIP folder.

Correct:

    C:\xampp\htdocs\LabBenchTest\login.php

Incorrect:

    C:\xampp\htdocs\LabBenchTest\LabBench_demo\login.php

---

## 5. Downloading the Files from GitHub

Using GitHub in the browser:

1. Go to the GitHub repository.
2. Switch to the `main` branch.
3. Click the green **Code** button.
4. Click **Download ZIP**.
5. Extract the ZIP.
6. Copy the extracted project files into:

    C:\xampp\htdocs\LabBenchTest

Make sure the PHP, SQL, and CSV files are directly inside `LabBenchTest`.

---

## 6. Configure `db.php`

Open:

    C:\xampp\htdocs\LabBenchTest\db.php

Verify the database settings:

    $dbHost = 'localhost';
    $dbName = 'LabBench';
    $dbUser = 'root';
    $dbPass = '';

If your MySQL root user has no password, leave:

    $dbPass = '';

If your MySQL root user has a password, change it locally:

    $dbPass = 'your_mysql_password_here';

Do not commit your personal MySQL password to GitHub.

The application uses PDO to connect PHP to MySQL. PDO is PHP’s database interface, and PDO_MYSQL is the driver used for MySQL database access.

---

## 7. Start XAMPP Services

Open **XAMPP Control Panel**.

Start:

- Apache
- MySQL

Both services should show as running.

---

## 8. Verify Apache and PHP

Before loading the database, confirm PHP works through Apache.

Inside:

    C:\xampp\htdocs\LabBenchTest

create a temporary file:

    php_test.php

Put this inside:

    <?php
    echo "PHP is working";
    ?>

Open in your browser:

    http://localhost/LabBenchTest/php_test.php

Expected result:

    PHP is working

Important:

- Do not double-click PHP files.
- Do not open PHP files using `file:///`.
- Always open PHP files through `http://localhost/...`.

If you see raw PHP code, Apache/PHP is not running correctly or the file was opened incorrectly.

If you get `404 Not Found`, confirm the file exists here:

    C:\xampp\htdocs\LabBenchTest\php_test.php

After this test works, delete `php_test.php`.

Do not commit `php_test.php` to GitHub.

---

## 9. Load the Database with MySQL Terminal

The database must be loaded before the PHP pages will work.

Open Command Prompt.

Go to the project folder:

    cd /d C:\xampp\htdocs\LabBenchTest

Start MySQL with local CSV loading enabled:

    mysql --local-infile=1 -u root -p

If your MySQL root user has no password, press Enter when prompted.

If `mysql` is not recognized, use the XAMPP MySQL executable:

    C:\xampp\mysql\bin\mysql.exe --local-infile=1 -u root -p

At the MySQL prompt, check whether local CSV loading is enabled:

    SHOW VARIABLES LIKE 'local_infile';

If it shows `OFF`, run:

    SET GLOBAL local_infile = 1;

Check again:

    SHOW VARIABLES LIKE 'local_infile';

Expected value:

    ON

This is required because `load.sql` imports data from CSV files using `LOAD DATA LOCAL INFILE`.

---

## 10. Rebuild the Database

At the `mysql>` prompt, run:

    DROP DATABASE IF EXISTS LabBench;
    SOURCE create.sql;
    SOURCE load.sql;

If the `SOURCE` commands fail because the files cannot be found, use full paths:

    DROP DATABASE IF EXISTS LabBench;
    SOURCE C:/xampp/htdocs/LabBenchTest/create.sql;
    SOURCE C:/xampp/htdocs/LabBenchTest/load.sql;

The CSV files must be directly inside:

    C:\xampp\htdocs\LabBenchTest

Required CSV files:

    Users.csv
    Workspaces.csv
    WorkspaceMembers.csv
    Projects.csv
    Datasets.csv
    DatasetVersions.csv
    Models.csv
    Runs.csv
    RunParams.csv
    RunMetrics.csv
    ModelRegistry.csv
    AuditLog.csv

---

## 11. Verify Database Load

After running `create.sql` and `load.sql`, run:

    USE LabBench;
    SHOW TABLES;

Expected tables:

    AuditLog
    Datasets
    DatasetVersions
    ModelRegistry
    Models
    Projects
    RunMetrics
    RunParams
    Runs
    Users
    WorkspaceMembers
    Workspaces

Optional row-count check:

    SELECT 'Users' AS table_name, COUNT(*) AS row_count FROM Users
    UNION ALL SELECT 'Workspaces', COUNT(*) FROM Workspaces
    UNION ALL SELECT 'WorkspaceMembers', COUNT(*) FROM WorkspaceMembers
    UNION ALL SELECT 'Projects', COUNT(*) FROM Projects
    UNION ALL SELECT 'Datasets', COUNT(*) FROM Datasets
    UNION ALL SELECT 'DatasetVersions', COUNT(*) FROM DatasetVersions
    UNION ALL SELECT 'Models', COUNT(*) FROM Models
    UNION ALL SELECT 'Runs', COUNT(*) FROM Runs
    UNION ALL SELECT 'RunParams', COUNT(*) FROM RunParams
    UNION ALL SELECT 'RunMetrics', COUNT(*) FROM RunMetrics
    UNION ALL SELECT 'ModelRegistry', COUNT(*) FROM ModelRegistry
    UNION ALL SELECT 'AuditLog', COUNT(*) FROM AuditLog;

---

## 12. Open the Application with XAMPP Apache

After Apache, MySQL, and the database are ready, open:

    http://localhost/LabBenchTest/login.php

---

## 13. Optional: Run with PHP Built-in Server

Use this only if you are not using XAMPP Apache.

Open Command Prompt:

    cd /d C:\xampp\htdocs\LabBenchTest

Load the database first:

    C:\xampp\mysql\bin\mysql.exe --local-infile=1 -u root -p

Inside MySQL:

    DROP DATABASE IF EXISTS LabBench;
    SOURCE create.sql;
    SOURCE load.sql;
    exit

Start the PHP built-in server:

    C:\xampp\php\php.exe -S localhost:8080

Open:

    http://localhost:8080/login.php

This option still requires MySQL to be running.

If PHP is already available in your system PATH, this shorter command may also work:

    php -S localhost:8080

Do not push temporary test files or personal local configuration changes to GitHub.

---

## 14. Demo Credentials

After loading the CSV files, use one of the seeded accounts:

| User | Email | Password |
|---|---|---|
| Yasar | yasar@labbench.com | hash123 |
| Uzayr | uzayr@labbench.com | hash234 |
| Ugonna | ugonna@labbench.com | hash345 |
| Zuhaib | zuhaib@labbench.com | hash456 |

Recommended demo login:

    yasar@labbench.com
    hash123

---

## 15. Main Phase 4 Demo Path

Use this path for the main database project demo:

1. Open:

    http://localhost/LabBenchTest/login.php

2. Log in with a seeded account.
3. Open **Projects**.
4. Create a new project or open an existing project.
5. Open the project detail page.
6. Click **Log Run**.
7. Create a run.
8. Open the run detail page.
9. Add a run parameter.
10. Add a run metric.
11. Update the run status or notes.
12. Open **All Runs**.
13. Use search/filter options.
14. Compare two runs.
15. Open **Model Registry**.
16. Open **Audit Log**.
17. Log out.

This demonstrates database connectivity, CRUD actions, query result pages, filtering, validation, and relationships across the LabBench schema.

---

## 16. Suggested Demo Run Data

For a clean demo, create a run using values similar to:

    Code Version Tag: ta-demo-run-v1
    Status: completed
    Notes: Demo run created during Phase 4 presentation

Example parameter:

    Parameter Key: learning_rate
    Parameter Value: 0.01

Example metric:

    Metric Key: accuracy
    Metric Value: 0.91
    Step: 0

Create a second run for comparison:

    Code Version Tag: ta-demo-run-v2
    Status: completed

Example parameter:

    Parameter Key: learning_rate
    Parameter Value: 0.02

Example metric:

    Metric Key: accuracy
    Metric Value: 0.94
    Step: 0

Then use **All Runs** to compare the two runs.

---

## 17. SQL Injection Assignment Demo

The SQL injection assignment is implemented in:

    sql_injection_demo.php

Open from the sidebar:

    SQL Injection Demo

Or go directly to:

    http://localhost/LabBenchTest/sql_injection_demo.php

The page includes two versions of the same search:

| Part | Description |
|---|---|
| Part A | Vulnerable SELECT query using direct input concatenation |
| Part B | Safe SELECT query using prepared statements |

---

## 18. SQL Injection Demo Payload

In **Code Version Tag**, enter:

    not-real') OR 1=1 #

Leave the other fields blank.

### Part A: Vulnerable SELECT

Choose:

    Part A: Vulnerable SELECT

Expected result:

    Rows are returned even though the code tag is fake.

This shows that the vulnerable query can be manipulated because user input is being treated as SQL code.

### Part B: Prepared Statement SELECT

Run the same input again, but choose:

    Part B: Prepared Statement SELECT

Expected result:

    Zero rows are returned.

This shows that the prepared statement treats the input as text instead of executable SQL logic.

---

## 19. SQL Injection Talking Points

Use this explanation during the demo:

The vulnerable version builds the SQL query by directly joining user input into the SQL string. The injected text changes the WHERE clause. The `OR 1=1` condition is always true, and the `#` character comments out the remaining part of the query.

The prepared-statement version keeps SQL code and user input separate. The database receives the query template first, then receives the input values as data. Because of that, the same injection string is treated as normal text and does not change the SQL logic.

---

## 20. Quick Functional Test Checklist

Before presenting, verify:

| Area | Expected Result |
|---|---|
| Apache | Running in XAMPP |
| MySQL | Running in XAMPP |
| `login.php` | Loads in browser |
| Database | `LabBench` exists |
| Tables | All 12 tables exist |
| CSV data | Loaded successfully |
| Login | Seeded account works |
| Projects | List/detail/create works |
| Runs | List/filter/create/detail works |
| Run parameters | Add/delete works |
| Run metrics | Add/delete works |
| Run comparison | Two runs compare successfully |
| Model registry | Page loads and displays data |
| Audit log | Page loads and displays records |
| SQLi Part A | Injection payload returns rows |
| SQLi Part B | Same payload returns zero rows |
| Logout | Session ends and protected pages require login |

---

## 21. Troubleshooting

### Problem: `404 Not Found`

Check that files are directly inside:

    C:\xampp\htdocs\LabBenchTest

Then open:

    http://localhost/LabBenchTest/login.php

### Problem: PHP code appears in the browser

Apache/PHP is not running correctly or the file was opened incorrectly.

Use:

    http://localhost/LabBenchTest/login.php

Do not use:

    file:///C:/xampp/htdocs/LabBenchTest/login.php

### Problem: Database connection failed

Check:

    db.php

Confirm:

    $dbName = 'LabBench';
    $dbUser = 'root';
    $dbPass = '';

If your MySQL root user has a password, update `$dbPass` locally.

### Problem: `mysql` is not recognized

Use the XAMPP MySQL executable:

    C:\xampp\mysql\bin\mysql.exe --local-infile=1 -u root -p

### Problem: CSV loading fails

Make sure local infile is enabled:

    SHOW VARIABLES LIKE 'local_infile';
    SET GLOBAL local_infile = 1;

Make sure the CSV files are directly inside:

    C:\xampp\htdocs\LabBenchTest

Then rerun:

    SOURCE C:/xampp/htdocs/LabBenchTest/load.sql;

### Problem: Login fails

Confirm the database was loaded:

    USE LabBench;
    SELECT user_id, full_name, email, is_active FROM Users;

Then use one of the seeded demo accounts.

### Problem: SQL injection demo returns no rows in Part A

Make sure the payload is entered exactly in **Code Version Tag**:

    not-real') OR 1=1 #

Leave the other fields blank.

### Problem: SQL injection demo returns rows in Part B

That should not happen. Confirm that **Part B: Prepared Statement SELECT** is selected.

---

## 22. Emergency Reset Before Demo

If test data becomes messy, rebuild the database.

Open MySQL:

    C:\xampp\mysql\bin\mysql.exe --local-infile=1 -u root -p

Then run:

    DROP DATABASE IF EXISTS LabBench;
    SOURCE C:/xampp/htdocs/LabBenchTest/create.sql;
    SOURCE C:/xampp/htdocs/LabBenchTest/load.sql;

Reopen:

    http://localhost/LabBenchTest/login.php

---

## 23. Final Demo Summary

LabBench demonstrates a working PHP/MySQL database application for managing machine learning experiment records. The demo covers login, projects, runs, run parameters, run metrics, run comparison, registry review, audit log review, and SQL injection prevention.

The SQL injection page demonstrates both the unsafe approach and the prepared-statement fix using the same search form and the same input payload.
