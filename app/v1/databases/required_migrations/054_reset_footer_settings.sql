-- Migration 054: bring the website footer back to its original content.
-- An early version of the footer editor could save a footer with every line and section switched off. This clears the
-- footer overrides (footer_phone, footer_show_company, ...), so the footer shows its normal content again. Social links are kept.
DELETE FROM site_settings WHERE setting_key LIKE 'footer\_%';
