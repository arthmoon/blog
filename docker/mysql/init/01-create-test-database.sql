-- Отдельная база для интеграционных тестов.
--
-- Выполняется только при первой инициализации тома MySQL. Если том уже
-- создан, база не появится — нужен docker compose down -v.

CREATE DATABASE IF NOT EXISTS blog_test
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON blog_test.* TO 'blog'@'%';

FLUSH PRIVILEGES;
