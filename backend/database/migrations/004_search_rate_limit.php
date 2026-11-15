<?php

declare(strict_types=1);

return [
    <<<'SQL'
ALTER TABLE auth_rate_limits
MODIFY action ENUM('login', 'register', 'search') NOT NULL
SQL,
];
