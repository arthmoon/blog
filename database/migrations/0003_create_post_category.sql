-- Связь статей и категорий: многие ко многим.
--
-- Два индекса по одной паре столбцов в разном порядке — не дублирование.
-- Первичный ключ (post_id, category_id) обслуживает вопрос «в каких
-- категориях эта статья», вторичный (category_id, post_id) — обратный
-- «какие статьи в этой категории», на котором стоят и главная страница,
-- и страница категории.

CREATE TABLE post_category (
    post_id     INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (post_id, category_id),
    KEY idx_post_category_category (category_id, post_id),

    CONSTRAINT fk_post_category_post
        FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_post_category_category
        FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
