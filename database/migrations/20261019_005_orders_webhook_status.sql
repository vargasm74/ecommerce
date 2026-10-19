-- Estado/auditoria del proveedor para webhooks de Mercado Pago.

ALTER TABLE orders
    ADD COLUMN provider_status VARCHAR(50) NULL AFTER provider_order_id,
    ADD COLUMN provider_status_detail VARCHAR(100) NULL AFTER provider_status,
    ADD COLUMN paid_at DATETIME NULL AFTER error_message,
    ADD COLUMN last_webhook_at DATETIME NULL AFTER paid_at,
    ADD COLUMN last_webhook_request_id VARCHAR(128) NULL AFTER last_webhook_at;
