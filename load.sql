/*
    LabBench
    Database setup for the PHP/MySQL demo
    load.sql
*/

USE LabBench;

LOAD DATA LOCAL INFILE 'Users.csv'
INTO TABLE Users
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(full_name, email, password_hash, created_at, is_active);

LOAD DATA LOCAL INFILE 'Workspaces.csv'
INTO TABLE Workspaces
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(workspace_name, created_at);

LOAD DATA LOCAL INFILE 'WorkspaceMembers.csv'
INTO TABLE WorkspaceMembers
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(workspace_id, user_id, member_role, joined_at);

LOAD DATA LOCAL INFILE 'Projects.csv'
INTO TABLE Projects
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(workspace_id, project_name, description, created_by_user_id, created_at);

LOAD DATA LOCAL INFILE 'Datasets.csv'
INTO TABLE Datasets
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(project_id, dataset_name, modality, source_type);

LOAD DATA LOCAL INFILE 'DatasetVersions.csv'
INTO TABLE DatasetVersions
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(dataset_id, version_tag, row_count, schema_hash, created_at);

LOAD DATA LOCAL INFILE 'Models.csv'
INTO TABLE Models
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(project_id, model_name, description, created_at);

LOAD DATA LOCAL INFILE 'Runs.csv'
INTO TABLE Runs
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(model_id, dataset_version_id, created_by_user_id, started_at, ended_at, status, code_version_tag, notes);

LOAD DATA LOCAL INFILE 'RunParams.csv'
INTO TABLE RunParams
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(run_id, param_key, param_value);

LOAD DATA LOCAL INFILE 'RunMetrics.csv'
INTO TABLE RunMetrics
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(run_id, metric_key, metric_value, step, recorded_at);

LOAD DATA LOCAL INFILE 'ModelRegistry.csv'
INTO TABLE ModelRegistry
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(model_id, source_run_id, stage, approved_by_user_id, approved_at);

LOAD DATA LOCAL INFILE 'AuditLog.csv'
INTO TABLE AuditLog
FIELDS TERMINATED BY ','
OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\r\n'
IGNORE 1 ROWS
(workspace_id, actor_user_id, action_type, entity_type, entity_id, action_timestamp);