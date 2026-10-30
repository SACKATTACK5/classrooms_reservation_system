INSERT INTO users (name, email, password, role)
SELECT
    'System Admin',
    'admin@edu.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1
    FROM users
    WHERE email = 'admin@edu.com'
);