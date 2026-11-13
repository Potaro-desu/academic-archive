USE academic_archiving;
-- Demo password for all three accounts: ArchiveDemo2026!
-- Password hashes were generated using PHP PASSWORD_DEFAULT.
INSERT INTO users(name,email,password_hash,role) VALUES
('College Administrator','admin@college.edu','$2y$12$k21A6A51b3CfW.dUl5ZhkehsZKuGHyRiGicKAJd1/.RXUJFXVBc02','admin'),
('Demo Student','student@college.edu','$2y$12$k21A6A51b3CfW.dUl5ZhkehsZKuGHyRiGicKAJd1/.RXUJFXVBc02','student'),
('Demo Faculty','faculty@college.edu','$2y$12$k21A6A51b3CfW.dUl5ZhkehsZKuGHyRiGicKAJd1/.RXUJFXVBc02','faculty')
ON DUPLICATE KEY UPDATE name=VALUES(name), role=VALUES(role);

-- Sample records: one pending student link, one approved public faculty link, one private student link.
INSERT INTO submissions(user_id,category_id,title,description,keywords,academic_year,resource_type,external_url,visibility,status)
SELECT u.id,c.id,'Introduction to Database Systems','A sample learning resource about relational databases, tables, keys and SQL fundamentals.','database, SQL, learning','2025-2026','link','https://dev.mysql.com/doc/','public','pending'
FROM users u JOIN categories c ON c.name='Learning Materials'
WHERE u.email='student@college.edu' AND NOT EXISTS
(SELECT 1 FROM submissions s WHERE s.user_id=u.id AND s.title='Introduction to Database Systems');

INSERT INTO submissions(user_id,category_id,title,description,keywords,academic_year,resource_type,external_url,visibility,status,published_at)
SELECT u.id,c.id,'PHP Official Documentation','A public reference link for PHP language documentation and web development learning.','PHP, documentation, web development','2025-2026','link','https://www.php.net/manual/en/','public','approved',NOW()
FROM users u JOIN categories c ON c.name='Learning Materials'
WHERE u.email='faculty@college.edu' AND NOT EXISTS
(SELECT 1 FROM submissions s WHERE s.user_id=u.id AND s.title='PHP Official Documentation');

INSERT INTO submissions(user_id,category_id,title,description,keywords,academic_year,resource_type,external_url,visibility,status)
SELECT u.id,c.id,'Private Capstone Planning Notes','A demonstration private record. Only its owner and the admin can open it.','capstone, planning, private','2025-2026','link','https://example.com/private-demo-resource','private','pending'
FROM users u JOIN categories c ON c.name='Thesis and Capstone'
WHERE u.email='student@college.edu' AND NOT EXISTS
(SELECT 1 FROM submissions s WHERE s.user_id=u.id AND s.title='Private Capstone Planning Notes');
