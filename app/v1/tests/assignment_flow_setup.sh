#!/bin/sh
# Builds the THROWAWAY database used by assignment_flow_test.php, then runs the test.
#   sh app/v1/tests/assignment_flow_setup.sh            (MySQL user root, no password)
#   MYSQL="mysql -uUSER -pPASS" sh app/v1/tests/assignment_flow_setup.sh
set -e
MYSQL=${MYSQL:-"mysql -uroot"}
DB=dazu_asg_e2e
DIR=$(cd "$(dirname "$0")/../databases/required_migrations" && pwd)
$MYSQL -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB;"
$MYSQL $DB -e "
CREATE TABLE users(id INT PRIMARY KEY, username VARCHAR(50), email VARCHAR(100));
CREATE TABLE user_profiles(user_id INT, first_name VARCHAR(50), last_name VARCHAR(50));
CREATE TABLE tutors(id INT PRIMARY KEY, user_id INT);
CREATE TABLE tutor_students(tutor_id INT, user_id INT);
CREATE TABLE courses(id INT PRIMARY KEY, title VARCHAR(50));
CREATE TABLE registrations(id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, course_id INT, status VARCHAR(20), full_name VARCHAR(50), registration_no VARCHAR(20));
CREATE TABLE site_settings(setting_key VARCHAR(50), setting_value VARCHAR(50));
INSERT INTO users VALUES (1,'tutor','t@x.com'),(2,'tutor2','t2@x.com'),(11,'amina','a@x.com'),(12,'brian','b@x.com'),(13,'cynthia','c@x.com');
INSERT INTO tutors VALUES (1,1),(2,2); INSERT INTO courses VALUES (1,'Cyber');
INSERT INTO tutor_students VALUES (1,11),(1,12),(1,13);
INSERT INTO registrations (user_id,course_id,status,full_name,registration_no) VALUES (11,1,'approved','Amina','R1'),(12,1,'approved','Brian','R2'),(13,1,'approved','Cynthia','R3');"
for m in 065_assignments_and_quizzes 066_assignments_full 083_assignments_daily_weekly; do $MYSQL -f $DB < "$DIR/$m.sql" 2>/dev/null || true; done
ASG_TEST_DSN="mysql:host=localhost;dbname=$DB" php "$(dirname "$0")/assignment_flow_test.php"
