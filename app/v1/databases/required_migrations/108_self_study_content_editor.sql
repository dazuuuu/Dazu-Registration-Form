-- 108_self_study_content_editor.sql
-- Self-study (step 5): the Content and Questions tabs of the tutor editor. NEW COLUMNS on tables created by migrations 104-107; no table of the live site is changed.
--  self_study_modules / topics / questions:  archived_at     an item students have started is archived (hidden, progress kept) instead of deleted
--  self_study_topics:                        edit_version    goes up by one on every save, so a second tutor who opens the same topic is warned instead of overwriting
--                                            updated_at, updated_by   who saved it last and when
--  self_study_questions:                     updated_at
--                                            (the topic tag of a quiz / exam question is the existing topic_id column: for a topic question it is the owner topic,
--                                             for a quiz or exam question it is the topic the question comes from, used by the result screen's "Read again" link)
--  self_study_answers:                       tutor_comment, marked_at, marked_by   the tutor's comment on a long answer
--  self_study_files:                         file_size, uploaded_by
ALTER TABLE self_study_modules ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE self_study_topics ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE self_study_topics ADD COLUMN edit_version INT NOT NULL DEFAULT 1;
ALTER TABLE self_study_topics ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE self_study_topics ADD COLUMN updated_by INT NULL;
ALTER TABLE self_study_questions ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE self_study_questions ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE self_study_answers ADD COLUMN tutor_comment VARCHAR(1000) NULL;
ALTER TABLE self_study_answers ADD COLUMN marked_at TIMESTAMP NULL DEFAULT NULL;
ALTER TABLE self_study_answers ADD COLUMN marked_by INT NULL;
ALTER TABLE self_study_files ADD COLUMN file_size INT NULL;
ALTER TABLE self_study_files ADD COLUMN uploaded_by INT NULL;
