-- The course-import template (CourseImportService) can now say which Subject a unit belongs to.
-- On apply, that subject is found or created in course_subjects for the course, and the new unit's
-- module is tagged with it, so it shows grouped under Subjects/Subject Library like any other subject.
ALTER TABLE course_modules ADD COLUMN subject_id INT UNSIGNED NULL AFTER course_id;
