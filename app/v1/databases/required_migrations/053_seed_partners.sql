-- Migration 053: the institute's partner logos shown in the "Our Technology & Placement Partners" strip.
-- Each is added only if a partner with that name is not already there, so re-running (or a site that added its own) is safe.
-- Logos are the files in public/assets/images/partners/. Edit or remove them later under Admin, then Partners.

INSERT INTO `partners` (`name`, `logo`, `website_url`, `sort_order`, `is_active`)
SELECT 'Google Developers', 'assets/images/partners/google.svg', 'https://developers.google.com', 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `partners` WHERE `name` = 'Google Developers');

INSERT INTO `partners` (`name`, `logo`, `website_url`, `sort_order`, `is_active`)
SELECT 'Microsoft Learn', 'assets/images/partners/microsoft.svg', 'https://learn.microsoft.com', 2, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `partners` WHERE `name` = 'Microsoft Learn');

INSERT INTO `partners` (`name`, `logo`, `website_url`, `sort_order`, `is_active`)
SELECT 'AWS Academy', 'assets/images/partners/aws.svg', 'https://aws.amazon.com/training/awsacademy/', 3, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `partners` WHERE `name` = 'AWS Academy');

INSERT INTO `partners` (`name`, `logo`, `website_url`, `sort_order`, `is_active`)
SELECT 'Cisco Networking', 'assets/images/partners/cisco.svg', 'https://www.netacad.com', 4, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `partners` WHERE `name` = 'Cisco Networking');

INSERT INTO `partners` (`name`, `logo`, `website_url`, `sort_order`, `is_active`)
SELECT 'CompTIA Partner', 'assets/images/partners/comptia.svg', 'https://www.comptia.org', 5, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `partners` WHERE `name` = 'CompTIA Partner');

INSERT INTO `partners` (`name`, `logo`, `website_url`, `sort_order`, `is_active`)
SELECT 'Safaricom Talent', 'assets/images/partners/safaricom.svg', 'https://www.safaricom.co.ke', 6, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `partners` WHERE `name` = 'Safaricom Talent');
