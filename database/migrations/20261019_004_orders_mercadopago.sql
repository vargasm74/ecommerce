-- Pedidos internos para desacoplar el ecommerce del proveedor de pago.
-- Los importes del catalogo legacy se conservan en USD y el importe de cobro
-- se snapshottea en la moneda del proveedor (ARS para Mercado Pago Argentina).

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'PENDING',
    source_currency CHAR(3) NOT NULL DEFAULT 'USD',
    currency CHAR(3) NOT NULL DEFAULT 'ARS',
    fx_rate DECIMAL(18,6) NOT NULL,
    subtotal_source DECIMAL(14,2) NOT NULL,
    tax_source DECIMAL(14,2) NOT NULL,
    shipping_source DECIMAL(14,2) NOT NULL,
    total_source DECIMAL(14,2) NOT NULL,
    total_amount DECIMAL(16,2) NOT NULL,
    country CHAR(2) NOT NULL,
    region VARCHAR(100) NOT NULL DEFAULT '',
    payment_provider VARCHAR(40) NOT NULL,
    provider_order_id VARCHAR(128) NULL,
    checkout_url TEXT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orders_idempotency (idempotency_key),
    KEY idx_orders_user_created (user_id, created_at),
    KEY idx_orders_provider_order (payment_provider, provider_order_id),
    CONSTRAINT fk_orders_user
        FOREIGN KEY (user_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    product_id INT(11) NOT NULL,
    title VARCHAR(255) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price_source DECIMAL(14,2) NOT NULL,
    subtotal_source DECIMAL(14,2) NOT NULL,
    tax_rate DECIMAL(7,4) NOT NULL,
    tax_amount_source DECIMAL(14,2) NOT NULL,
    tax_rule_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
