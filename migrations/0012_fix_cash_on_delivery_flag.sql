-- Dobierka / platba hotovostou pri osobnom odbere / nezname (prazdne) delivery_type
-- sa nesmie povazovat za "nezaplatenu platbu vopred" (viz OrderFeedParser).
UPDATE orders
SET is_cash_on_delivery = 1
WHERE delivery_type = ''
   OR delivery_type LIKE '%dobierka%'
   OR delivery_type LIKE '%hotovosť%';
