-- Team member social links (each shows on the About page only when filled in).
ALTER TABLE team_members
    ADD COLUMN whatsapp  VARCHAR(50)  NULL AFTER email,
    ADD COLUMN tiktok    VARCHAR(255) NULL AFTER whatsapp,
    ADD COLUMN linkedin  VARCHAR(255) NULL AFTER tiktok,
    ADD COLUMN facebook  VARCHAR(255) NULL AFTER linkedin,
    ADD COLUMN instagram VARCHAR(255) NULL AFTER facebook;
