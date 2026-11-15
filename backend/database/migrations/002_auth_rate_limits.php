<?php

declare(strict_types=1);

return [
    <<<'SQL'
CREATE TABLE IF NOT EXISTS auth_rate_limits (
    action ENUM('login', 'register') NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    window_started_at DATETIME(6) NOT NULL,
    attempt_count INT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (action, subject_hash),
    KEY idx_auth_rate_limits_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
SQL,
];
