<?php

declare(strict_types=1);

return [
    'tables' => [
        'roles',
        'users',
        'materials',
        'warehouses',
        'inventory_transactions',
    ],
    'sql' => [
        "CREATE TABLE IF NOT EXISTS roles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            role_id BIGINT UNSIGNED NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            name VARCHAR(120) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS materials (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            specification VARCHAR(190) NULL,
            base_unit VARCHAR(32) NOT NULL,
            material_type VARCHAR(32) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS warehouses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS inventory_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            material_id BIGINT UNSIGNED NOT NULL,
            warehouse_id BIGINT UNSIGNED NOT NULL,
            transaction_type VARCHAR(32) NOT NULL,
            quantity DECIMAL(18, 6) NOT NULL,
            reference_type VARCHAR(64) NULL,
            reference_no VARCHAR(64) NULL,
            occurred_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_inventory_material FOREIGN KEY (material_id) REFERENCES materials(id),
            CONSTRAINT fk_inventory_warehouse FOREIGN KEY (warehouse_id) REFERENCES warehouses(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
];

