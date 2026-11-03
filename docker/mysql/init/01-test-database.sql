-- Database terpisah untuk test (phpunit.xml memakai rentalpro_test).
CREATE DATABASE IF NOT EXISTS rentalpro_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON rentalpro_test.* TO 'rentalpro'@'%';
FLUSH PRIVILEGES;
