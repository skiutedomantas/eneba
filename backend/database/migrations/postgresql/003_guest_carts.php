<?php

declare(strict_types=1);

return [
    <<<'SQL'
ALTER TABLE carts
    ALTER COLUMN user_id DROP NOT NULL,
    ADD COLUMN guest_token_hash CHAR(64),
    ADD CONSTRAINT uq_carts_guest_token_hash UNIQUE (guest_token_hash),
    ADD CONSTRAINT chk_carts_owner CHECK (
        (user_id IS NOT NULL AND guest_token_hash IS NULL)
        OR (user_id IS NULL AND guest_token_hash IS NOT NULL)
    )
SQL,
];
