-- Add tutor_name and ceo_name snapshot fields to certificates table
-- Also add generated_pdf_path for storing the generated PDF file path
ALTER TABLE certificates
    ADD COLUMN IF NOT EXISTS tutor_name VARCHAR(150) NULL AFTER tutor_signature_path,
    ADD COLUMN IF NOT EXISTS ceo_name VARCHAR(150) NULL AFTER director_signature_path,
    ADD COLUMN IF NOT EXISTS generated_pdf_path VARCHAR(255) NULL AFTER ceo_name;
