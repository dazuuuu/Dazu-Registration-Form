# Dazu Training Institute — Registration System


## Project layout

- `public/` is the only web-facing folder (document root). Pages, assets and uploads live here, so URLs never depend on the backend layout.
- `app/v1/` is the one backend: `config/`, `models/`, `controllers/`, `services/`, `helpers/`, `templates/`, `lib/`, plus
  `databases/` (SQL migrations, `migrate.php`), `storage/` (private files), `vendor/` + `composer.json` (Composer), `tests/`,
  `bootstrap.php` and `init.php`.
- File paths are defined in one place: `app/v1/config/paths.php` (constants `BACKEND_ROOT`, `DATABASES_PATH`, `STORAGE_PATH`,
  `VENDOR_PATH`, `TESTS_PATH`, and helpers `backend_path()`, `database_path()`, `storage_path()`, `storage_resolve()`,
  `vendor_autoload_files()`, `url()`, `asset()`). Build paths with these, never with hard-coded `../..`.
- Run migrations: `php app/v1/databases/migrate.php` (or Admin portal → System Updates). Install packages: `cd app/v1 && composer install`.
- Run tests: `php app/v1/tests/payroll_calculator_test.php`.

## Apply for a certificate
- Student: **Certificates** page (`public/students/certificates/index.php`, panel `public/components/certificates/apply_panel.php`). The Apply button is grey until all four are true: course time finished, fees 100%, capstone passed, course average 50+. Then the student checks how their name will print and applies (`students/certificates/apply.php`).
- Tutor: **Certificate requests** (`public/tutor/certificates/`). The tutor sees the certificate with a PREVIEW mark and writes only the lines under the name (300 letters), then Returns (with a note), Approves, or Approves and sends. A mistake is fixed by a new version (the old one is marked Replaced). The tutor can mark a course finished by hand or move one student's end date (Mark course finished page).
- Rules: `app/v1/services/CertificateApplication.php` (`certificateReadiness()` is the one check; for capstone courses it uses `cap_certificate_readiness()`). The certificate itself is made, issued and emailed by the existing `CertificateService.php`. Admin sees status words only.
- Migration `092_certificate_applications.sql`. The scheduled job is the existing `app/v1/cron/capstone_jobs.php` (it also opens the Apply button when the course time passes and reminds the tutor after 3 days).

## Subjects (assignments)
- Tutor: **Subjects** button on the Assignments page (`public/tutor/assignments/subjects.php`). Every assignment, weekly task and **Monthly project** can be linked to a subject (Subject box on the assignment form; *New subject* adds one without leaving the form).
- The subject mark is never typed: `subjectMarks($db, $student, $course, $at = null)` in `app/v1/services/SubjectService.php` works it out through the existing `AssignmentAverage::compute()`. Status: Not yet / In progress / Final. Admin pages never use it.
- Migration `091_subjects.sql` (tables `course_subjects`, `course_subject_events`, column `tutor_assignments.subject_id`).

## Capstone project
- Tutor: **Capstone** under Teaching (`public/tutor/capstone/`). Student: **Capstone** under Assignments (`public/students/capstone/`), shown only after the tutor scheduled or opened one. Admin sees status words only (`app/v1/services/CapstoneStatus.php`; Certificates page, Settings → Capstone retake fees).
- Rules live in `app/v1/services/CapstoneService.php` (enforced on the server): marks out of 100 in four parts, pass 50, at most 2 returns, at most 5 retakes (student pays again), capstone counts in the course average and a pass is a certificate condition (for courses that have a capstone).
- Migrations 088 and 089. Cron (every 5 minutes, optional: the pages also run it): `php app/v1/cron/capstone_jobs.php --host=dazu.ac.ke --https`.
