# LabBench Demo

LabBench is a PHP/MySQL demo application for tracking ML projects, datasets, models, runs, run parameters, run metrics, and model registry entries.

## Local setup

1. Put this folder inside your XAMPP `htdocs` directory, for example:
   `C:\xampp\htdocs\LabBenchTest`
2. Start Apache and MySQL in XAMPP.
3. Open a MySQL terminal from the project folder and run:
   ```sql
   DROP DATABASE IF EXISTS LabBench;
   SOURCE create.sql;
   SOURCE load.sql;
   ```
4. Open the app in a browser:
   `http://localhost/LabBenchTest/login.php`

## Demo credentials

After loading the CSV files, the first visit to `login.php` may reseed the demo users with password hashes. For the cleanest SQL injection demo, use the admin account because it can see all seeded workspaces. Use one of these accounts:

- `yasar@labbench.com` / `hash123`
- `uzayr@labbench.com` / `hash234`
- `ugonna@labbench.com` / `hash345`
- `zuhaib@labbench.com` / `hash456`

## Main demo path

1. Log in.
2. Open Projects.
3. Create or open a project.
4. Add a model and dataset if needed.
5. Open All Runs.
6. Log a run.
7. Add parameters and metrics.
8. Compare two runs.
9. Promote a run in Model Registry.
10. Check Audit Log.

## SQL injection assignment demo path

1. Log in.
2. Open **SQL Injection Demo** from the sidebar.
3. Paste this into **Code Version Tag** and leave the other boxes empty:
   ```text
   not-real') OR 1=1 #
   ```
4. Choose **Part A: Vulnerable SELECT** and run the search. It should return rows even though the code tag is fake.
5. Choose **Part B: Prepared Statement SELECT** and run the same search again. It should return zero rows because the input is handled as text, not SQL.
