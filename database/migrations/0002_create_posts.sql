-- Статьи.
--
-- published_at допускает NULL — это черновик. Заполненная дата в будущем
-- означает отложенную публикацию, поэтому читающие запросы фильтруют по
-- published_at <= NOW(), а не по «published_at IS NOT NULL».

CREATE TABLE posts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(200) NOT NULL,
    slug         VARCHAR(200) NOT NULL,
    description  VARCHAR(500) NOT NULL,
    body         MEDIUMTEXT   NOT NULL,
    image        VARCHAR(255) NULL DEFAULT NULL,
    views        INT UNSIGNED NOT NULL DEFAULT 0,
    published_at DATETIME     NULL DEFAULT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uniq_posts_slug (slug),

    -- Порядок «свежие сверху» с устойчивым разрешением одинаковых дат.
    KEY idx_posts_published_at (published_at DESC, id DESC),

    -- Сортировка по популярности на странице категории.
    KEY idx_posts_views (views DESC, id DESC)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
