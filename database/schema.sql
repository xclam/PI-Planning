-- À appliquer avant déploiement.
CREATE TABLE IF NOT EXISTS pis (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    feature_sprint_id INT NULL,
    feature_sprint_name VARCHAR(255) NULL,
    enabler_sprint_id INT NULL,
    enabler_sprint_name VARCHAR(255) NULL,
    UNIQUE KEY uq_pis_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ticket_ai_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_key VARCHAR(40) NOT NULL,
    ai_summary TEXT NULL,
    ai_action TEXT NULL,
    latest_comment TEXT NULL,
    issue_updated VARCHAR(40) NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_ticket_key (ticket_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Idempotence de la création d'US : évite les doublons Jira si on relance.
CREATE TABLE IF NOT EXISTS created_us (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pi_id INT UNSIGNED NOT NULL,
    kind ENUM('feature','enabler') NOT NULL,
    source_key VARCHAR(40) NOT NULL,
    us_key VARCHAR(40) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_pi_source (pi_id, source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;