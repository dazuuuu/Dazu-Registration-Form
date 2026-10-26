<?php
// app/v1/databases/releases.php — what admins see on System Updates. Each version groups the SQL files in
// required_migrations/ that ship with it, so the page shows "V1.2 update" instead of a pile of file names.
//
// Shipping an update: add the new .sql file(s) to required_migrations/, then add a version at the END of this list.
//   New feature            → next minor version: 1.2 → 1.3
//   Fix / small change     → patch version:       1.2 → 1.2.1
// Notes are for admins: say what changes for them, not how the database changes.
// A .sql file that is not listed here still runs; it shows as an unnamed patch after the latest version.

return [
    [
        'version' => '1.0',
        'title'   => 'Core platform',
        'date'    => '2026-09-01',
        'notes'   => ['Website, courses, registrations and payments', 'Student and tutor portals, certificates and ID cards', 'Store, blog, gallery and SMS'],
        'files'   => [
            '001_create_users_table.sql', '002_create_projects_tables.sql', '003_create_services_tables.sql', '004_create_blogs_tables.sql',
            '005_create_settings_tables.sql', '006_create_gallery_tables.sql', '007_create_products_tables.sql', '008_create_cart_tables.sql',
            '009_create_store_tables.sql', '010_fix_store_orders_schema.sql', '011_create_enquiries_table.sql', '012_create_testimonials_table.sql',
            '013_create_properties.sql', '014_add_property_sold.sql', '015_create_courses_table.sql', '016_create_registrations_payments_tables.sql',
            '017_student_portal_payments.sql', '019_lms_tutors_curriculum.sql', '020_registration_referrals.sql',
            '021_store_cart_saved_for_later_column.sql', '022_add_course_study_duration.sql', '023_blog_content_blocks.sql',
            '024_add_course_study_schedule.sql', '025_course_categories.sql', '026_user_resources.sql', '028_sms_module.sql',
            '029_sms_must_change_password.sql', '030_course_days_duration.sql', '031_team_members.sql', '032_newsletter_subscribers.sql',
            '033_course_requirements_and_free_flag.sql', '034_add_free_course_reason.sql', '035_add_course_dates_and_free_toggle.sql',
            '036_course_fee_structure.sql', '037_course_brochure.sql', '038_course_semesters_and_class_schedules.sql',
            '039_site_class_schedules.sql', '040_hero_slide_media_fields.sql', '041_campus_media.sql',
            '042_homepage_courses_and_category_featured_image.sql', '043_create_partners_table.sql', '044_seed_video_testimonials.sql',
            '045_tutor_student_assignments.sql', '046_backfill_tutor_students_from_course_tutors.sql', '047_tutor_payroll.sql',
            '048_payroll_pauses_and_employees.sql', '049_seed_superadmin.sql', '050_course_qualification.sql',
            '051_study_location_and_number_letters.sql', '052_schema_gap_fill.sql', '053_seed_partners.sql', '054_reset_footer_settings.sql',
            '055_extend_projects_student_sandbox.sql', '056_team_member_socials.sql', '057_certificates_and_lms.sql',
            '058_tutor_announcements.sql', '059_certificate_collected_status.sql', '060_missing_columns.sql',
            '061_tutor_course_builder_and_documents.sql', '062_portal_notifications.sql', '063_tutor_admin_rights.sql',
            '064_student_id_cards.sql',
        ],
    ],
    [
        'version' => '1.1',
        'title'   => 'Assignments and quizzes',
        'date'    => '2026-10-13',
        'notes'   => ['Tutors set assignments and quizzes, with rubrics, due dates and extensions', 'Students submit work and see their results'],
        'files'   => ['065_assignments_and_quizzes.sql', '066_assignments_full.sql'],
    ],
    [
        'version' => '1.2',
        'title'   => 'Open submissions',
        'date'    => '2026-10-13',
        'notes'   => ['Students hand in work without an assignment being set: a GitHub link, Google Drive link or document',
                      'Links are checked before they are accepted', 'Tutors grade the work or return it for changes'],
        'files'   => ['067_open_submissions.sql'],
    ],
    [
        'version' => '1.3',
        'title'   => 'Signatures and ID cards',
        'date'    => '2026-10-13',
        'notes'   => ['Tutors upload their signature and passport photo, and get a staff ID card automatically',
                      'Directors page: the director\'s signature and the trainer\'s signature now print on certificates',
                      'Students need a passport photo to request an ID card; approved ID cards are emailed to them'],
        'files'   => ['068_signatures_and_staff_ids.sql'],
    ],
    [
        'version' => '1.4',
        'title'   => 'Blog layouts',
        'date'    => '2026-10-13',
        'notes'   => ['Blog editor: split blocks put text on one side and an image or YouTube video on the other',
                      'Section lines to divide a post into parts', 'YouTube links pasted into the editor become playable videos'],
        'files'   => ['069_blog_split_and_divider_blocks.sql'],
    ],
];
