<?php

declare(strict_types=1);

return [
    <<<'SQL'
CREATE TABLE auth_rate_limits (
    action VARCHAR(16) NOT NULL CHECK (action IN ('login', 'register')),
    subject_hash CHAR(64) NOT NULL,
    window_started_at TIMESTAMP(6) NOT NULL,
    attempt_count INTEGER NOT NULL CHECK (attempt_count >= 0),
    updated_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (action, subject_hash)
)
SQL,
    <<<'SQL'
CREATE INDEX idx_auth_rate_limits_window ON auth_rate_limits (window_started_at)
SQL,
];
