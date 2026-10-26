-- The two branch admins: dazuhubs@gmail.com (Thika) and karanjav494@gmail.com (Nairobi).
-- Both accounts get the admin role. karanjav494 also teaches: their tutor record, students and payroll stay exactly as
-- they are, and because the account is an admin with a tutor record they can switch between the tutor portal and the
-- admin portal (the global login opens the tutor portal; "Admin portal" in its menu opens the admin portal).
-- Only the role column of these two users changes. Safe to run twice.

UPDATE users
   SET role_id = (SELECT id FROM (SELECT id FROM roles WHERE role_name = 'admin') r)
 WHERE email IN ('dazuhubs@gmail.com', 'karanjav494@gmail.com')
   AND role_id <> 1;

-- Make sure both have their branch (084 may have run before an account existed).
INSERT IGNORE INTO staff_branches (user_id, branch, updated_at)
SELECT id, 'thika', NOW() FROM users WHERE email = 'dazuhubs@gmail.com';
INSERT IGNORE INTO staff_branches (user_id, branch, updated_at)
SELECT id, 'nairobi', NOW() FROM users WHERE email = 'karanjav494@gmail.com';
