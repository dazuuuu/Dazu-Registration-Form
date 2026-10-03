-- Certificates: "collected" (picked up) status for the admin's own tracking.
-- Statuses are admin-only; the public verify page shows the student's name, course and photo whatever the status.

ALTER TABLE certificates
    MODIFY status ENUM('requested','returned','being_prepared','awaiting_director_signature','issued','collected','withdrawn')
    NOT NULL DEFAULT 'requested';
