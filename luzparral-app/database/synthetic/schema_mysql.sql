SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dataset_metadata (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dataset_key VARCHAR(80) NOT NULL UNIQUE,
    generator_version VARCHAR(20) NOT NULL,
    random_seed INT UNSIGNED NOT NULL,
    generated_at DATETIME NOT NULL,
    parameters_json JSON NOT NULL,
    notes VARCHAR(500) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    email_verified_at DATETIME NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(30) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    remember_token VARCHAR(100) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_users_role_active (role, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS communes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL UNIQUE,
    center_lat DECIMAL(10,7) NOT NULL,
    center_lon DECIMAL(10,7) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS feeders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commune_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_feeders_commune FOREIGN KEY (commune_id) REFERENCES communes(id),
    INDEX idx_feeders_commune_active (commune_id, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supply_points (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    synthetic_code VARCHAR(40) NOT NULL UNIQUE,
    customer_code VARCHAR(40) NOT NULL UNIQUE,
    commune_id BIGINT UNSIGNED NOT NULL,
    feeder_id BIGINT UNSIGNED NOT NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    criticality VARCHAR(35) NOT NULL DEFAULT 'normal',
    active BOOLEAN NOT NULL DEFAULT TRUE,
    installed_at DATE NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_supply_commune FOREIGN KEY (commune_id) REFERENCES communes(id),
    CONSTRAINT fk_supply_feeder FOREIGN KEY (feeder_id) REFERENCES feeders(id),
    INDEX idx_supply_filters (commune_id, feeder_id, criticality, active),
    INDEX idx_supply_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_name VARCHAR(80) NOT NULL,
    synthetic_file_name VARCHAR(180) NOT NULL,
    status VARCHAR(25) NOT NULL,
    total_rows INT UNSIGNED NOT NULL,
    accepted_rows INT UNSIGNED NOT NULL,
    rejected_rows INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    imported_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_batches_user FOREIGN KEY (imported_by) REFERENCES users(id),
    INDEX idx_batches_date_status (started_at, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS import_errors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_batch_id BIGINT UNSIGNED NOT NULL,
    source_row_number INT UNSIGNED NOT NULL,
    field_name VARCHAR(80) NULL,
    error_code VARCHAR(50) NOT NULL,
    message VARCHAR(300) NOT NULL,
    synthetic_reference VARCHAR(80) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_errors_batch FOREIGN KEY (import_batch_id) REFERENCES import_batches(id) ON DELETE CASCADE,
    INDEX idx_errors_batch_code (import_batch_id, error_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contingencies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    osf_code VARCHAR(40) NOT NULL UNIQUE,
    commune_id BIGINT UNSIGNED NOT NULL,
    feeder_id BIGINT UNSIGNED NOT NULL,
    source_batch_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL,
    priority VARCHAR(20) NOT NULL,
    cause VARCHAR(60) NOT NULL,
    description VARCHAR(300) NOT NULL,
    started_at DATETIME NOT NULL,
    estimated_restore_at DATETIME NULL,
    restored_at DATETIME NULL,
    latitude DECIMAL(10,7) NOT NULL,
    longitude DECIMAL(10,7) NOT NULL,
    affected_total INT UNSIGNED NOT NULL DEFAULT 0,
    critical_affected INT UNSIGNED NOT NULL DEFAULT 0,
    electrodependent_affected INT UNSIGNED NOT NULL DEFAULT 0,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_cont_commune FOREIGN KEY (commune_id) REFERENCES communes(id),
    CONSTRAINT fk_cont_feeder FOREIGN KEY (feeder_id) REFERENCES feeders(id),
    CONSTRAINT fk_cont_batch FOREIGN KEY (source_batch_id) REFERENCES import_batches(id),
    CONSTRAINT fk_cont_user FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_cont_filters (started_at, commune_id, status, priority, feeder_id),
    INDEX idx_cont_coordinates (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contingency_impacts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contingency_id BIGINT UNSIGNED NOT NULL,
    supply_point_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(25) NOT NULL,
    affected_at DATETIME NOT NULL,
    restored_at DATETIME NULL,
    outage_minutes INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_impacts_contingency FOREIGN KEY (contingency_id) REFERENCES contingencies(id) ON DELETE CASCADE,
    CONSTRAINT fk_impacts_supply FOREIGN KEY (supply_point_id) REFERENCES supply_points(id),
    UNIQUE KEY uq_impact_cont_supply (contingency_id, supply_point_id),
    INDEX idx_impacts_status_time (status, affected_at, restored_at),
    INDEX idx_impacts_supply (supply_point_id, affected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contingency_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contingency_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    note VARCHAR(300) NOT NULL,
    event_at DATETIME NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    source VARCHAR(25) NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_history_contingency FOREIGN KEY (contingency_id) REFERENCES contingencies(id) ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_history_cont_time (contingency_id, event_at),
    INDEX idx_history_event_time (event_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
