-- Migration 070: Reset the superadmin login to admin@dazuhubs.com.
-- Moves the account seeded in 049 (admin@dazuhub.com) to the new email, resets its password,
-- and re-activates it. Creates the account if no superadmin login exists yet.

UPDATE `users`
SET `email` = 'admin@dazuhubs.com',
    `password_hash` = '$2y$12$4C1upxD4EBAUFHDr3ydCJenL7NgHzr7mUTxlRXZtEviJjQZsCGgVm',
    `role_id` = (SELECT `id` FROM (SELECT `id` FROM `roles` WHERE `role_name` = 'superadmin' LIMIT 1) r),
    `is_active` = 1,
    `email_verified` = 1,
    `account_status` = 'active',
    `must_change_password` = 0
WHERE LOWER(`email`) IN ('admin@dazuhubs.com', 'admin@dazuhub.com');

INSERT INTO `users` (`username`, `email`, `password_hash`, `role_id`, `is_active`, `email_verified`, `account_status`)
SELECT 'superadmin.dazuhub', 'admin@dazuhubs.com', '$2y$12$4C1upxD4EBAUFHDr3ydCJenL7NgHzr7mUTxlRXZtEviJjQZsCGgVm',
       (SELECT `id` FROM `roles` WHERE `role_name` = 'superadmin' LIMIT 1), 1, 1, 'active'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE LOWER(`email`) = 'admin@dazuhubs.com')
  AND NOT EXISTS (SELECT 1 FROM `users` WHERE LOWER(`username`) = 'superadmin.dazuhub')
  AND EXISTS (SELECT 1 FROM `roles` WHERE `role_name` = 'superadmin');

INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`)
SELECT u.`id`, 'Super', 'Admin' FROM `users` u
WHERE LOWER(u.`email`) = 'admin@dazuhubs.com'
  AND NOT EXISTS (SELECT 1 FROM `user_profiles` p WHERE p.`user_id` = u.`id`);
