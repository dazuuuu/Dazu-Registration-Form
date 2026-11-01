-- 096_receipt_note.sql
-- The NB the office can add to one payment receipt (shown under the amounts). The NB for every receipt is in Settings → Receipts.
ALTER TABLE payments ADD COLUMN receipt_note VARCHAR(600) NULL;
