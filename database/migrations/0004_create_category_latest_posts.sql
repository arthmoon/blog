-- Проекция «последние статьи категории» для главной страницы.
--
-- Денормализуется только порядок, но не содержимое: в таблице лежат
-- идентификаторы, а заголовки и описания по-прежнему берутся из posts.
-- Иначе правка заголовка требовала бы перестройки проекции, и мы получили
-- бы вторую копию данных вместо ускорения одной выборки.
--
-- Таблица наполняется проектором внутри той же транзакции, что и запись
-- статьи, — по событиям PostPublished, PostUnpublished и
-- PostCategoriesChanged. Обе стороны в одной MySQL, поэтому атомарность
-- достаётся бесплатно; внешний кэш такой гарантии не дал бы.
--
-- Сколько статей в ленте — решение представления, и оно живёт в проекторе.
-- В схеме этого числа намеренно нет: иначе замена тройки на пятёрку
-- потребовала бы миграции.

CREATE TABLE category_latest_posts (
    category_id INT UNSIGNED     NOT NULL,
    position    TINYINT UNSIGNED NOT NULL,
    post_id     INT UNSIGNED     NOT NULL,

    -- Ключ задаёт и принадлежность, и порядок вывода одной записью.
    PRIMARY KEY (category_id, position),

    -- Нужен внешнему ключу. InnoDB создал бы индекс сам, но объявленный
    -- явно он виден в схеме, а не только в выводе SHOW CREATE TABLE.
    KEY idx_category_latest_posts_post (post_id),

    CONSTRAINT fk_category_latest_posts_category
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_category_latest_posts_post
        FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
