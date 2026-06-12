<?php

declare(strict_types=1);

return [
    'tables' => [
        'roles',
        'users',
        'materials',
        'warehouses',
        'suppliers',
        'boms',
        'bom_items',
        'purchase_orders',
        'purchase_order_items',
        'work_orders',
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
        "CREATE TABLE IF NOT EXISTS suppliers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            contact_name VARCHAR(120) NULL,
            phone VARCHAR(64) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS boms (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            project_code VARCHAR(64) NOT NULL DEFAULT 'STANDARD',
            project_name VARCHAR(190) NOT NULL DEFAULT '标准项目',
            parent_material_id BIGINT UNSIGNED NOT NULL,
            version VARCHAR(64) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_boms_parent_material FOREIGN KEY (parent_material_id) REFERENCES materials(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE boms ADD COLUMN IF NOT EXISTS project_code VARCHAR(64) NOT NULL DEFAULT 'STANDARD' AFTER id",
        "ALTER TABLE boms ADD COLUMN IF NOT EXISTS project_name VARCHAR(190) NOT NULL DEFAULT '标准项目' AFTER project_code",
        "CREATE TABLE IF NOT EXISTS bom_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            bom_id BIGINT UNSIGNED NOT NULL,
            component_material_id BIGINT UNSIGNED NOT NULL,
            quantity DECIMAL(18, 6) NOT NULL,
            scrap_rate DECIMAL(9, 4) NOT NULL DEFAULT 0,
            CONSTRAINT fk_bom_items_bom FOREIGN KEY (bom_id) REFERENCES boms(id),
            CONSTRAINT fk_bom_items_component_material FOREIGN KEY (component_material_id) REFERENCES materials(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS purchase_orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            supplier_id BIGINT UNSIGNED NULL,
            order_no VARCHAR(64) NOT NULL UNIQUE,
            supplier_name VARCHAR(190) NOT NULL,
            expected_date DATE NULL,
            status VARCHAR(32) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS supplier_id BIGINT UNSIGNED NULL AFTER id",
        "CREATE TABLE IF NOT EXISTS purchase_order_items (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id BIGINT UNSIGNED NOT NULL,
            material_id BIGINT UNSIGNED NOT NULL,
            quantity DECIMAL(18, 6) NOT NULL,
            unit_price DECIMAL(18, 6) NOT NULL,
            CONSTRAINT fk_purchase_order_items_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id),
            CONSTRAINT fk_purchase_order_items_material FOREIGN KEY (material_id) REFERENCES materials(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS work_orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_no VARCHAR(64) NOT NULL UNIQUE,
            bom_id BIGINT UNSIGNED NOT NULL,
            planned_quantity DECIMAL(18, 6) NOT NULL,
            due_date DATE NULL,
            status VARCHAR(32) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_work_orders_bom FOREIGN KEY (bom_id) REFERENCES boms(id)
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
