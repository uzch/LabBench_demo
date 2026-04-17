/*
	LabBench
	Phase 3; Task C
	create.sql
*/

CREATE DATABASE IF NOT EXISTS LabBench;
USE LabBench;

CREATE TABLE Users (
    user_id INT NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (user_id)
) ENGINE=InnoDB;

CREATE TABLE Workspaces (
    workspace_id INT NOT NULL AUTO_INCREMENT,
    workspace_name VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (workspace_id)
) ENGINE=InnoDB;

CREATE TABLE WorkspaceMembers (
    workspace_id INT NOT NULL,
    user_id INT NOT NULL,
    member_role VARCHAR(20) NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (workspace_id, user_id),
    CONSTRAINT fk_workspacemembers_workspace
        FOREIGN KEY (workspace_id)
        REFERENCES Workspaces(workspace_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_workspacemembers_user
        FOREIGN KEY (user_id)
        REFERENCES Users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE Projects (
    project_id INT NOT NULL AUTO_INCREMENT,
    workspace_id INT NOT NULL,
    project_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id),
    CONSTRAINT fk_projects_workspace
        FOREIGN KEY (workspace_id)
        REFERENCES Workspaces(workspace_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_projects_created_by_user
        FOREIGN KEY (created_by_user_id)
        REFERENCES Users(user_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE Datasets (
    dataset_id INT NOT NULL AUTO_INCREMENT,
    project_id INT NOT NULL,
    dataset_name VARCHAR(150) NOT NULL,
    modality VARCHAR(50) NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    PRIMARY KEY (dataset_id),
    CONSTRAINT fk_datasets_project
        FOREIGN KEY (project_id)
        REFERENCES Projects(project_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE DatasetVersions (
    dataset_version_id INT NOT NULL AUTO_INCREMENT,
    dataset_id INT NOT NULL,
    version_tag VARCHAR(50) NOT NULL,
    row_count INT NOT NULL DEFAULT 0,
    schema_hash VARCHAR(64) NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (dataset_version_id),
    CONSTRAINT fk_datasetversions_dataset
        FOREIGN KEY (dataset_id)
        REFERENCES Datasets(dataset_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE Models (
    model_id INT NOT NULL AUTO_INCREMENT,
    project_id INT NOT NULL,
    model_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (model_id),
    CONSTRAINT fk_models_project
        FOREIGN KEY (project_id)
        REFERENCES Projects(project_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE Runs (
    run_id INT NOT NULL AUTO_INCREMENT,
    model_id INT NOT NULL,
    dataset_version_id INT NOT NULL,
    created_by_user_id INT NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'queued',
    code_version_tag VARCHAR(100) NULL DEFAULT NULL,
    notes TEXT NULL DEFAULT NULL,
    PRIMARY KEY (run_id),
    CONSTRAINT fk_runs_model
        FOREIGN KEY (model_id)
        REFERENCES Models(model_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_runs_dataset_version
        FOREIGN KEY (dataset_version_id)
        REFERENCES DatasetVersions(dataset_version_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_runs_created_by_user
        FOREIGN KEY (created_by_user_id)
        REFERENCES Users(user_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE RunParams (
    run_id INT NOT NULL,
    param_key VARCHAR(100) NOT NULL,
    param_value VARCHAR(100) NOT NULL,
    PRIMARY KEY (run_id, param_key),
    CONSTRAINT fk_runparams_run
        FOREIGN KEY (run_id)
        REFERENCES Runs(run_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE RunMetrics (
    run_id INT NOT NULL,
    metric_key VARCHAR(50) NOT NULL,
    metric_value DECIMAL(10,4) NOT NULL,
    step INT NOT NULL,
    recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (run_id, metric_key, step),
    CONSTRAINT fk_runmetrics_run
        FOREIGN KEY (run_id)
        REFERENCES Runs(run_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ModelRegistry (
    model_version_id INT NOT NULL AUTO_INCREMENT,
    model_id INT NOT NULL,
    source_run_id INT NOT NULL,
    stage VARCHAR(20) NOT NULL DEFAULT 'staging',
    approved_by_user_id INT NOT NULL,
    approved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (model_version_id),
    CONSTRAINT fk_modelregistry_model
        FOREIGN KEY (model_id)
        REFERENCES Models(model_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_modelregistry_source_run
        FOREIGN KEY (source_run_id)
        REFERENCES Runs(run_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_modelregistry_approved_by_user
        FOREIGN KEY (approved_by_user_id)
        REFERENCES Users(user_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE AuditLog (
    log_id INT NOT NULL AUTO_INCREMENT,
    workspace_id INT NOT NULL,
    actor_user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    action_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    CONSTRAINT fk_auditlog_workspace
        FOREIGN KEY (workspace_id)
        REFERENCES Workspaces(workspace_id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_auditlog_actor_user
        FOREIGN KEY (actor_user_id)
        REFERENCES Users(user_id)
        ON DELETE RESTRICT
) ENGINE=InnoDB;
