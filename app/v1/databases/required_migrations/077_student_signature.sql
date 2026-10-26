-- Students upload their own signature in My Documents; it is printed on their student ID card.
ALTER TABLE user_profiles
    ADD COLUMN signature_path VARCHAR(255) NULL;
