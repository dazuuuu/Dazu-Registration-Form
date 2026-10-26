-- Migration 049: Default superadmin account for the /superadmin dashboard.
-- Only created when no user with this email exists yet. Change the password after the first sign-in.
INSERT INTO `users` (`username`, `email`, `password_hash`, `role_id`, `is_active`, `email_verified`, `account_status`)
SELECT 'superadmin.dazuhub', 'admin@dazuhub.com', '$2y$12$bIM0EmkZdFLxgbgBRt9oreJ8vPco/.0NXCf26PHUrk3gnfq3LBvKC',
       (SELECT `id` FROM `roles` WHERE `role_name` = 'superadmin' LIMIT 1), 1, 1, 'active'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE LOWER(`email`) = 'admin@dazuhub.com')
  AND EXISTS (SELECT 1 FROM `roles` WHERE `role_name` = 'superadmin');

INSERT INTO `user_profiles` (`user_id`, `first_name`, `last_name`)
SELECT u.`id`, 'Super', 'Admin' FROM `users` u
WHERE LOWER(u.`email`) = 'admin@dazuhub.com' AND u.`username` = 'superadmin.dazuhub'
  AND NOT EXISTS (SELECT 1 FROM `user_profiles` p WHERE p.`user_id` = u.`id`);
