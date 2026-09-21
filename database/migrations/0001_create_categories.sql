-- Категории блога.
-- slug уникален: по нему открывается страница, это часть публичного адреса.

CREATE TABLE categories (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    title       VARCHAR(150)  NOT NULL,
    slug        VARCHAR(200)  NOT NULL,
    description VARCHAR(1000) NOT NULL DEFAULT '',

    PRIMARY KEY (id),
    UNIQUE KEY uniq_categories_slug (slug)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;
