# LabBench Runs Module + SQL Injection Demo Talking Points

## Must Cover / TA Is Looking For

- **Runs module scope**
  - Runs are the experiment-tracking part of LabBench.
  - Main tables: `Runs`, `RunParams`, `RunMetrics`.
  - Main pages: `runs.php`, `log_run.php`, `run_detail.php`, `compare_runs.php`.
  - Connects to overall project flow: `Workspace → Project → Model → Run`.

- **Phase 4 requirements**
  - Show the website is connected to MySQL.
  - Show real database-backed CRUD.
  - Show forms, query results, validation, search/filtering, and updates.
  - Show the interface is no longer static Phase 3 HTML.

- **SQL Injection Assignment requirements**
  - Show a vulnerable `SELECT` form.
  - Show how injection retrieves rows with incomplete/wrong input.
  - Show the same input blocked by prepared statements.
  - Explain that the vulnerable page is intentionally unsafe only for the assignment.

---

## Runs Module Talking Points

### Purpose / Motivation

- Runs are the core experiment records in LabBench.
- A project can have models and datasets, but the **run** captures what actually happened during an ML experiment.
- Each run records:
  - which model was used
  - which dataset version was used
  - who created the run
  - status
  - code version tag
  - timestamps
  - notes
  - parameters
  - metrics

### Database Design Points

- `Runs` is the parent table.
- `RunParams` and `RunMetrics` are child tables linked by `run_id`.
- `RunParams` stores hyperparameters/configuration values.
  - Example: `learning_rate = 0.01`
  - Example: `batch_size = 32`
- `RunMetrics` stores experiment results.
  - Example: `accuracy = 0.91`
  - Example: `loss = 0.25`
- This separates experiment setup from experiment outcomes.
- This design supports many parameters and many metrics per run.
- Foreign keys preserve relationships and prevent orphaned records.
- If a run is deleted, related params/metrics should be removed or protected by referential integrity depending on references.

### PHP Page Responsibilities

- `runs.php`
  - Main runs dashboard.
  - Lists runs from MySQL.
  - Joins run data with project/model/dataset/user context.
  - Supports filtering/searching.
  - Provides entry point for run comparison.

- `log_run.php`
  - Creates a new run.
  - Inserts into `Runs`.
  - Optionally inserts starting parameter into `RunParams`.
  - Optionally inserts starting metric into `RunMetrics`.
  - Uses logged-in user as creator.

- `run_detail.php`
  - Shows one run in detail.
  - Supports update/delete for the run.
  - Supports add/delete for parameters.
  - Supports add/delete for metrics.

- `compare_runs.php`
  - Takes two run IDs.
  - Shows run summaries side by side.
  - Compares metric values by metric key and step.

### What the TA Cares About

- Is this database-connected or just static?
- Do inserts actually create records in MySQL?
- Do updates actually persist?
- Do deletes work cleanly?
- Are related tables being used correctly?
- Are joins showing meaningful information?
- Is input validated?
- Are edge cases handled without crashing?
- Does the comparison page show a useful query/report?

---

## Runs Module Demo Steps

### Step 1 — Open Runs Dashboard

Open:

```text
http://localhost/LabBenchTest/runs.php
```

Show:

- Run list
- Project/model/dataset context
- Status
- Code version tag
- Creator
- Search/filter controls
- Compare controls

Talking points:

- This is a database-backed query/report page.
- The page is joining multiple tables, not showing hardcoded data.
- This gives users a readable experiment history.

---

### Step 2 — Show Search / Filter

Try filtering by:

```text
Status: completed
```

Try searching:

```text
v1
```

Talking points:

- Filtering is important because experiment history can grow quickly.
- Users need to narrow runs by project, status, or keyword.
- If nothing matches, the page should handle it cleanly.

Optional edge-case search:

```text
no-such-run-xyz
```

Expected:

```text
Clean empty result, no crash.
```

---

### Step 3 — Create a New Run

Open:

```text
http://localhost/LabBenchTest/log_run.php
```

Use this demo data:

```text
Code Version Tag: ta-demo-run-v1
Status: completed
Notes: Demo run created during Phase 4 presentation
Parameter Key: learning_rate
Parameter Value: 0.01
Metric Key: accuracy
Metric Value: 0.91
Step: 0
```

If timestamp fields are shown, leave them blank.

Talking points:

- This demonstrates the create part of CRUD.
- The run connects a model, dataset version, and logged-in user.
- The optional parameter and metric prove that parent and child records are created together.
- Blank timestamp fields let MySQL use default timestamps instead of inserting invalid `NULL` values.

Expected:

```text
Redirects to run_detail.php for the new run.
```

---

### Step 4 — Show Run Detail Page

On `run_detail.php`, point out:

- Run summary
- Code version tag
- Status
- Notes
- Model/dataset context
- Parameters table
- Metrics table
- Update form
- Add parameter form
- Add metric form

Talking points:

- This is the detailed read view for one run.
- It shows the parent `Runs` record and its child `RunParams` and `RunMetrics`.
- This page is where most of the run-level CRUD happens.

---

### Step 5 — Update Run

Update notes:

```text
Updated during demo
```

Optional status:

```text
Status: completed
```

Talking points:

- This demonstrates update functionality.
- The change persists in the database.
- The page reloads and shows the updated values.

Expected:

```text
Updated notes/status appear after submit.
```

---

### Step 6 — Add Parameter

Add:

```text
Parameter Key: batch_size
Parameter Value: 32
```

Talking points:

- This inserts a new child row into `RunParams`.
- Parameters describe experiment configuration.
- This lets users track what settings produced a result.

Expected:

```text
batch_size = 32 appears under parameters.
```

---

### Step 7 — Add Metric

Add:

```text
Metric Key: loss
Metric Value: 0.25
Step: 0
```

Talking points:

- This inserts a new child row into `RunMetrics`.
- Metrics describe experiment results.
- Multiple metrics can be stored for the same run.

Expected:

```text
loss = 0.25 appears under metrics.
```

---

### Step 8 — Delete Parameter or Metric

Delete one of these:

```text
batch_size
```

or:

```text
loss
```

Talking points:

- This demonstrates delete functionality for child records.
- Deleting a child record does not delete the parent run.
- The relationship is preserved correctly.

Expected:

```text
Selected parameter/metric disappears.
```

---

### Step 9 — Create Second Run for Comparison

Create another run:

```text
Code Version Tag: ta-demo-run-v2
Status: completed
Notes: Second run for comparison demo
Parameter Key: learning_rate
Parameter Value: 0.02
Metric Key: accuracy
Metric Value: 0.94
Step: 0
```

Talking points:

- A second run lets us demonstrate experiment comparison.
- The two runs have the same metric key, so the comparison page can line them up.

---

### Step 10 — Compare Runs

Go back to:

```text
http://localhost/LabBenchTest/runs.php
```

Select:

```text
Run 1: ta-demo-run-v1
Run 2: ta-demo-run-v2
```

Submit compare.

Talking points:

- This page compares two experiment runs side by side.
- Metrics are aligned by metric key and step.
- This is useful for deciding which model run performed better.

Point out:

```text
ta-demo-run-v1 accuracy = 0.91
ta-demo-run-v2 accuracy = 0.94
```

Expected:

```text
compare_runs.php shows both runs and their metric comparison.
```

---

## Runs Module Edge Cases to Mention

### Validation

Mention that the module checks:

- Required model cannot be blank.
- Required dataset version cannot be blank.
- Status must be valid.
- Metric value must be numeric.
- Metric step must be zero or positive.
- End time cannot be earlier than start time.
- Compare page should reject same-run comparison.
- Invalid run IDs should not crash the page.

### Quick Edge Tests to Show Only If Asked

Search with no result:

```text
no-such-run-xyz
```

Compare same run:

```text
Run 1: ta-demo-run-v1
Run 2: ta-demo-run-v1
```

Invalid metric value:

```text
Metric Key: precision
Metric Value: abc
Step: 0
```

Invalid metric step:

```text
Metric Key: recall
Metric Value: 0.88
Step: -1
```

Talking point:

- These checks protect data quality and make the app more reliable.

---

## SQL Injection Demo Talking Points

### Purpose / Motivation

- This is separate from the normal Runs module.
- It exists because Assignment #5 requires showing both:
  - vulnerable SQL behavior
  - prepared-statement prevention
- The vulnerable logic is intentionally isolated in `sql_injection_demo.php`.
- Normal app pages should use safe PDO/prepared statements.

### What the TA Is Looking For

- A form with several input fields.
- A `SELECT` query using user input.
- A vulnerable version that can be injected.
- A prepared-statement version that prevents the same attack.
- Clear explanation of why Part A fails and Part B is safe.

### Mechanism to Explain

- Vulnerable version:
  - input is directly joined into SQL text
  - user input can become SQL code
  - `OR 1=1` makes the condition true
  - `#` comments out the rest of the query

- Prepared version:
  - SQL template is sent separately
  - user input is bound as data
  - injection text is treated as a literal string
  - query logic does not change

---

## SQL Injection Demo Steps

### Step 1 — Open SQL Injection Demo

Open:

```text
http://localhost/LabBenchTest/sql_injection_demo.php
```

Talking points:

- This page is intentionally built for Assignment #5.
- It has a vulnerable SELECT and a safe prepared SELECT.
- We use the same input to show the difference.

---

### Step 2 — Normal Search

Enter a real code version tag if available:

```text
ta-demo-run-v1
```

Run:

```text
Part A: Vulnerable SELECT
```

Expected:

```text
Returns the matching run.
```

Then run:

```text
Part B: Prepared Statement SELECT
```

Expected:

```text
Also returns the matching run.
```

Talking point:

- With normal input, both versions can return the expected row.

---

### Step 3 — Fake Input Without Injection

Enter:

```text
not-real
```

Run Part A.

Expected:

```text
Zero rows.
```

Run Part B.

Expected:

```text
Zero rows.
```

Talking point:

- A fake code tag should normally return no results.

---

### Step 4 — SQL Injection Payload

Enter this in **Code Version Tag**:

```text
not-real') OR 1=1 #
```

Leave other fields blank.

Run:

```text
Part A: Vulnerable SELECT
```

Expected:

```text
Rows are returned even though the code tag is fake.
```

Talking points:

- This proves the vulnerable query can be manipulated.
- The input is not accurate, but data still comes back.
- `OR 1=1` makes the condition true.
- `#` comments out the remaining SQL.

---

### Step 5 — Prepared Statement Defense

Keep the same payload:

```text
not-real') OR 1=1 #
```

Run:

```text
Part B: Prepared Statement SELECT
```

Expected:

```text
Zero rows.
```

Talking points:

- Same exact input, different result.
- Prepared statement treats the payload as text.
- The SQL structure does not change.
- This is the correct prevention technique.

---

## Short Glance Version During Demo

### Runs

- My portion = Runs module.
- Tables = `Runs`, `RunParams`, `RunMetrics`.
- Pages = `runs.php`, `log_run.php`, `run_detail.php`, `compare_runs.php`.
- Purpose = track ML experiment executions.
- Show:
  - runs list
  - filter/search
  - create run
  - add param
  - add metric
  - update run
  - delete param/metric
  - compare two runs
- Motivation:
  - experiments need configuration + result tracking.
  - params = setup.
  - metrics = outcomes.
  - comparison helps decide best run.

### SQL Injection

- Page = `sql_injection_demo.php`.
- Assignment requires vulnerable SELECT + prepared-statement SELECT.
- Payload:

```text
not-real') OR 1=1 #
```

- Part A expected: returns rows.
- Part B expected: zero rows.
- Explain:
  - vulnerable = mixes code and data.
  - prepared = separates code and data.

---

## Copy/Paste Demo Values

### Run 1

```text
Code Version Tag: ta-demo-run-v1
Status: completed
Notes: Demo run created during Phase 4 presentation
Parameter Key: learning_rate
Parameter Value: 0.01
Metric Key: accuracy
Metric Value: 0.91
Step: 0
```

### Add Parameter

```text
Parameter Key: batch_size
Parameter Value: 32
```

### Add Metric

```text
Metric Key: loss
Metric Value: 0.25
Step: 0
```

### Run 2

```text
Code Version Tag: ta-demo-run-v2
Status: completed
Notes: Second run for comparison demo
Parameter Key: learning_rate
Parameter Value: 0.02
Metric Key: accuracy
Metric Value: 0.94
Step: 0
```

### SQL Injection Payload

```text
not-real') OR 1=1 #
```

### Fake Non-Injection Search

```text
not-real
```

### Search Term

```text
ta-demo-run
```

---

## Final Closing Point

- The Runs module turns the database schema into a working ML experiment-tracking workflow.
- It demonstrates relational design, CRUD, joins, validation, search/filtering, child tables, and comparison.
- The SQL injection demo separately proves why unsafe string-built SQL is dangerous and why prepared statements are the correct fix.
