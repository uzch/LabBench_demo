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

After loading the CSV files, the first visit to `login.php` may reseed the demo users with password hashes. Use one of these accounts:

- `yasar@labbench.com` / `hash123`
- `uzayr@labbench.com` / `hash234`
- `ugonna@labbench.com` / `hash345`
- `zuhaib@labbench.com` / `hash456`

## Demo path

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
