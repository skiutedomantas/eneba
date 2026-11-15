<?php

declare(strict_types=1);

return [
    <<<'SQL'
ALTER TABLE carts
    MODIFY COLUMN user_id BIGINT UNSIGNED NULL,
    ADD COLUMN guest_token_hash CHAR(64) NULL AFTER user_id,
    ADD UNIQUE KEY uq_carts_guest_token_hash (guest_token_hash),
    ADD CONSTRAINT chk_carts_owner CHECK (
        (user_id IS NOT NULL AND guest_token_hash IS NULL)
        OR (user_id IS NULL AND guest_token_hash IS NOT NULL)
    )
SQL,
];
