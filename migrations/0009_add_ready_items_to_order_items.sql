ALTER TABLE order_items
    ADD COLUMN ready_items INT NULL AFTER count;
