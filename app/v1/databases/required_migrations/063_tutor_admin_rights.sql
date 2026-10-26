-- Tutor permissions: a tutor can be given admin rights (admin portal as well as their tutor portal) or stay tutor-only.
ALTER TABLE tutors ADD COLUMN can_admin TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE tutors ADD COLUMN admin_granted_by INT NULL;
ALTER TABLE tutors ADD COLUMN admin_granted_at TIMESTAMP NULL;
