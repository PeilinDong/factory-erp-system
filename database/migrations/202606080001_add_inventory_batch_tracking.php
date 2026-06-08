<?php

declare(strict_types=1);

return [
    'tables' => [
        'inventory_transactions.batch_no',
    ],
    'sql' => [
        "ALTER TABLE inventory_transactions
            ADD COLUMN IF NOT EXISTS batch_no VARCHAR(64) NULL AFTER reference_no",
        "CREATE INDEX IF NOT EXISTS idx_inventory_transactions_batch_no
            ON inventory_transactions (batch_no)",
    ],
];
