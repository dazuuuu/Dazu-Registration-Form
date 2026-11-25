-- Tutor-authored "code/text answer" quiz questions, graded by exact text match, and per-question
-- retry tracking (up to ASG_QUIZ_MAX_ATTEMPTS attempts, pass at ASG_QUIZ_PASS_PERCENT) for every
-- quiz question type. ADD ONLY: no column dropped/retyped, no existing row's meaning changed.
--   qtype gains 'text_answer' (enum widening is additive/safe). options_json is reused for this
--   type to hold {"answer": "<expected text>"} instead of an MCQ options array -- no new column.
--   tutor_assignment_quiz_answers moves from "one row per (submission, question)" to "one row per
--   (submission, question, attempt)", matching the capstone_attempts (088_capstone.sql) attempt_no
--   pattern, so retry history is kept instead of being overwritten.
-- MigrationService records each file in schema_migrations once it runs cleanly, so this file only
-- ever executes once per database: the DROP KEY below does not need its own idempotency guard the
-- way a hand-rerun script would (MigrationService tolerates 1050/1060/1061/1068/1022/1826 on a
-- partial-failure replay, which is enough for this file's statements).

ALTER TABLE tutor_assignment_quiz_questions MODIFY COLUMN qtype ENUM('mcq','truefalse','text_answer') NOT NULL DEFAULT 'mcq';

ALTER TABLE tutor_assignment_quiz_answers ADD COLUMN attempt_no SMALLINT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE tutor_assignment_quiz_answers ADD COLUMN answer_text TEXT NULL;
ALTER TABLE tutor_assignment_quiz_answers ADD COLUMN is_correct TINYINT(1) NULL;
ALTER TABLE tutor_assignment_quiz_answers ADD COLUMN points_awarded DECIMAL(6,2) NULL;
ALTER TABLE tutor_assignment_quiz_answers ADD COLUMN graded_at DATETIME NULL;

ALTER TABLE tutor_assignment_quiz_answers DROP KEY uq_taqa;
ALTER TABLE tutor_assignment_quiz_answers ADD UNIQUE KEY uq_taqa (submission_id, question_id, attempt_no);
