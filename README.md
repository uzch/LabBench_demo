# LabBench Demo

LabBench is a PHP/MySQL web application for tracking machine learning experiment work across workspaces, projects, datasets, dataset versions, models, runs, run parameters, run metrics, model registry entries, users, and audit events.

This repository is organized for the database project demo and the SQL injection assignment demo. The working application lives in `public/`; the older static HTML screens are archived for reference only.

---

## Repository Layout

```text
LabBench_demo/
├── README.md
├── public/
│   ├── index.php
│   ├── login.php
│   ├── projects.php
│   ├── runs.php
│   ├── datasets.php
│   ├── model_registry.php
│   ├── sql_injection_demo.php
│   ├── ... other PHP pages and form handlers
│   └── assets/
│       └── styles.css
├── src/
│   ├── db.php
│   └── audit_helpers.php
├── database/
│   ├── schema/
│   │   └── create.sql
│   └── seed/
│       ├── load.sql
│       └── csv/
│           ├── Users.csv
│           ├── Workspaces.csv
│           ├── WorkspaceMembers.csv
│           ├── Projects.csv
│           ├── Datasets.csv
│           ├── DatasetVersions.csv
│           ├── Models.csv
│           ├── Runs.csv
│           ├── RunParams.csv
│           ├── RunMetrics.csv
│           ├── ModelRegistry.csv
│           └── AuditLog.csv
└── archive/
    └── static-html/
        └── original static mockups
```

### What belongs where

| Path | Purpose |
|---|---|
| `public/` | Runnable PHP pages, POST/GET handlers, and browser-facing assets. |
| `public/assets/styles.css` | Shared stylesheet for the PHP application. |
| `src/db.php` | PDO database connection settings. |
| `src/audit_helpers.php` | Shared helpers for sessions, escaping, validation, audit logging, permissions, flash messages, and sidebar rendering. |
| `database/schema/create.sql` | MySQL database and table creation script. |
| `database/seed/load.sql` | MySQL seed-data import script. |
| `database/seed/csv/` | CSV seed files loaded by `load.sql`. |
| `archive/static-html/` | Archived static HTML prototypes. These are not the runnable application. |

---

## Application Overview

LabBench models this workflow:

```text
Workspace → Project → Dataset / Model → Run → Parameters / Metrics → Registry Review
```

The demo includes:

- Login and session handling
- Workspace-based project organization
- Admin-only user management
- Workspace membership management
- CRUD pages for projects, datasets, dataset versions, models, runs, registry entries, workspaces, and members
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

## Technology Stack

| Layer | Technology |
|---|---|
| Front end | HTML and CSS |
| Back end | PHP |
| Database | MySQL |
| Database access | PDO / PDO_MYSQL |
| Local server option 1 | XAMPP Apache |
| Local server option 2 | PHP built-in development server |
| Seed data | SQL scripts and CSV files |

---

## Required Local Tools

Install or have access to:

- PHP with PDO_MYSQL enabled
- MySQL or MariaDB
- MySQL command-line client
- Web browser

For Windows/XAMPP demos, use:

- XAMPP Control Panel
- Apache through XAMPP
- MySQL through XAMPP
- PHP through XAMPP

Recommended XAMPP checkout path:

```text
C:\xampp\htdocs\LabBench_demo
```

---

## Configure the Database Connection

Open:

```text
src/db.php
```

Default local settings:

```php
$dbHost = 'localhost';
$dbName = 'LabBench';
$dbUser = 'root';
$dbPass = '';
```

If your local MySQL root account has a password, update `$dbPass` locally. Do not commit personal database credentials.

---

## Load or Rebuild the Database

The PHP pages require the `LabBench` database and seed rows.

### 1. Start MySQL

With XAMPP, open the XAMPP Control Panel and start **MySQL**.

### 2. Open a MySQL terminal from the repository root

Windows/XAMPP example:

```bat
cd /d C:\xampp\htdocs\LabBench_demo
C:\xampp\mysql\bin\mysql.exe --local-infile=1 -u root -p
```

If `mysql` is already on your PATH:

```bash
cd /path/to/LabBench_demo
mysql --local-infile=1 -u root -p
```

If the root account has no password, press Enter when prompted.

### 3. Confirm local CSV loading is enabled

At the `mysql>` prompt:

```sql
SHOW VARIABLES LIKE 'local_infile';
```

If the value is `OFF`, enable it:

```sql
SET GLOBAL local_infile = 1;
```

### 4. Rebuild and seed the database

Run these commands from the `mysql>` prompt while your terminal's current directory is the repository root:

```sql
DROP DATABASE IF EXISTS LabBench;
SOURCE database/schema/create.sql;
SOURCE database/seed/load.sql;
```

`database/seed/load.sql` imports CSV files from `database/seed/csv/`.

### 5. Verify the load

```sql
USE LabBench;
SHOW TABLES;
```

Expected tables:

```text
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
```

Optional row-count check:

```sql
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
```

---

## Run the Demo with XAMPP Apache

1. Place or clone the repository at:

   ```text
   C:\xampp\htdocs\LabBench_demo
   ```

2. Start **Apache** and **MySQL** in the XAMPP Control Panel.

3. Load the database using the instructions above.

4. Open the application:

   ```text
   http://localhost/LabBench_demo/public/login.php
   ```

You can also open:

```text
http://localhost/LabBench_demo/public/
```

`public/index.php` redirects to `login.php`.

Important:

- Do not double-click PHP files.
- Do not open PHP files using `file:///`.
- Always access the app through `http://localhost/...` so PHP executes through a server.

---

## Run the Demo with the PHP Built-in Server

Use this option if PHP is available locally and MySQL is already running.

From the repository root:

```bash
php -S localhost:8080 -t public
```

Then open:

```text
http://localhost:8080/login.php
```

The built-in server only serves PHP. MySQL must still be running, and the database must be loaded first.

---

## Demo Credentials

After loading the seed data, use one of these accounts:

| User | Email | Password |
|---|---|---|
| Yasar | yasar@labbench.com | hash123 |
| Uzayr | uzayr@labbench.com | hash234 |
| Ugonna | ugonna@labbench.com | hash345 |
| Zuhaib | zuhaib@labbench.com | hash456 |

Recommended demo login:

```text
yasar@labbench.com
hash123
```

---

## Main Demo Path

Use this path for the database project demo:

1. Open `http://localhost/LabBench_demo/public/login.php`.
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
13. Use search and filter options.
14. Compare two runs.
15. Open **Model Registry**.
16. Open **Audit Log**.
17. Log out.

This demonstrates database connectivity, CRUD actions, query result pages, filtering, validation, permissions, and relationships across the LabBench schema.

---

## Suggested Demo Run Data

Create one run with values similar to:

```text
Code Version Tag: ta-demo-run-v1
Status: completed
Notes: Demo run created during presentation
```

Example parameter:

```text
Parameter Key: learning_rate
Parameter Value: 0.01
```

Example metric:

```text
Metric Key: accuracy
Metric Value: 0.91
Step: 0
```

Create a second run for comparison:

```text
Code Version Tag: ta-demo-run-v2
Status: completed
```

Example parameter:

```text
Parameter Key: learning_rate
Parameter Value: 0.02
```

Example metric:

```text
Metric Key: accuracy
Metric Value: 0.94
Step: 0
```

Then use **All Runs** to compare the two runs.

---

## SQL Injection Assignment Demo

The SQL injection assignment is implemented in:

```text
public/sql_injection_demo.php
```

Open it from the sidebar:

```text
SQL Injection Demo
```

Or go directly to:

```text
http://localhost/LabBench_demo/public/sql_injection_demo.php
```

The page includes two versions of the same search:

| Part | Description |
|---|---|
| Part A | Vulnerable SELECT query using direct input concatenation. |
| Part B | Safe SELECT query using prepared statements. |

### Demo payload

In **Code Version Tag**, enter:

```text
not-real') OR 1=1 #
```

Leave the other fields blank.

### Part A: Vulnerable SELECT

Choose:

```text
Part A: Vulnerable SELECT
```

Expected result: rows are returned even though the code tag is fake. The vulnerable query can be manipulated because user input is treated as SQL code.

### Part B: Prepared Statement SELECT

Run the same input again, but choose:

```text
Part B: Prepared Statement SELECT
```

Expected result: zero rows are returned. The prepared statement treats the payload as text instead of executable SQL logic.

---

## Troubleshooting

### Browser shows raw PHP code

You opened the file directly instead of running it through a PHP server. Use one of these URLs:

```text
http://localhost/LabBench_demo/public/login.php
http://localhost:8080/login.php
```

Do not use a `file:///` URL.

### Browser shows a database connection error

Check that:

1. MySQL is running.
2. `src/db.php` has the correct username and password.
3. The `LabBench` database exists.
4. You ran both SQL scripts:

   ```sql
   SOURCE database/schema/create.sql;
   SOURCE database/seed/load.sql;
   ```

### CSV load fails

Check that:

1. You started MySQL with local infile enabled: `mysql --local-infile=1 -u root -p`.
2. `SHOW VARIABLES LIKE 'local_infile';` returns `ON`.
3. You are running `SOURCE database/seed/load.sql;` from the repository root.
4. The CSV files exist in `database/seed/csv/`.

### Page not found under XAMPP

Confirm the repository is located at:

```text
C:\xampp\htdocs\LabBench_demo
```

Then use:

```text
http://localhost/LabBench_demo/public/login.php
```

If you changed the folder name, update the URL to match your folder name.

---

## Development Notes

- Keep browser-facing PHP files in `public/`.
- Keep shared PHP helpers in `src/`.
- Keep schema and seed assets in `database/`.
- Keep archived mockups in `archive/static-html/`.
- Do not commit personal database passwords.
- Do not commit temporary PHP test files.
