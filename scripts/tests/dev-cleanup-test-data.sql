-- Removes all data created by the step test scripts (names start with "CRM "/"T- ", emails test-*@example.com, branch codes CTB).
SET FOREIGN_KEY_CHECKS = 0;
SET @users := (SELECT GROUP_CONCAT(id) FROM users WHERE email LIKE 'test-%@example.com');
SET @tb := (SELECT GROUP_CONCAT(id) FROM branches WHERE code IN ('CTB'));
SET @tc := (SELECT GROUP_CONCAT(id) FROM customers WHERE name LIKE 'CRM %' OR name LIKE 'T- %' OR FIND_IN_SET(branch_id, @tb));
DELETE FROM quotation_items WHERE quotation_id IN (SELECT id FROM quotations WHERE FIND_IN_SET(customer_id, @tc));
DELETE FROM quotations WHERE FIND_IN_SET(customer_id, @tc);
DELETE FROM lead_activities WHERE lead_id IN (SELECT id FROM leads WHERE name LIKE 'CRM %' OR name LIKE 'T- %' OR FIND_IN_SET(branch_id, @tb));
DELETE FROM leads WHERE name LIKE 'CRM %' OR name LIKE 'T- %' OR FIND_IN_SET(branch_id, @tb);
DELETE FROM customers WHERE name LIKE 'CRM %' OR name LIKE 'T- %' OR FIND_IN_SET(branch_id, @tb);
DELETE FROM inquiries WHERE name LIKE 'CRM %' OR name LIKE 'T- %';
DELETE FROM activity_logs WHERE FIND_IN_SET(user_id, @users) OR description LIKE '%CRM %' OR description LIKE '%T- %' OR description LIKE '%Test %' OR (subject_type = 'Branch' AND FIND_IN_SET(subject_id, @tb));
DELETE FROM personal_access_tokens WHERE FIND_IN_SET(tokenable_id, @users);
DELETE FROM branch_user WHERE FIND_IN_SET(user_id, @users) OR FIND_IN_SET(branch_id, @tb);
DELETE FROM users WHERE FIND_IN_SET(id, @users);
DELETE FROM document_sequences WHERE FIND_IN_SET(branch_id, @tb);
DELETE FROM branches WHERE FIND_IN_SET(id, @tb);
SET FOREIGN_KEY_CHECKS = 1;
