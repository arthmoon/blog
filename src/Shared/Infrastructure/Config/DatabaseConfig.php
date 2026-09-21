<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Config;

/**
 * Параметры подключения к базе из переменных окружения.
 */
final readonly class DatabaseConfig
{
    public function __construct(
        public string $host,
        public int $port,
        public string $database,
        public string $user,
        public string $password,
    ) {
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::required('DB_HOST'),
            (int) self::optional('DB_PORT', '3306'),
            self::required('DB_NAME'),
            self::required('DB_USER'),
            self::optional('DB_PASSWORD', ''),
        );
    }

    public function dsn(): string
    {
        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->database,
        );
    }

    private static function required(string $name): string
    {
        $value = getenv($name);

        if (false === $value || '' === $value) {
            throw new \RuntimeException(sprintf('Переменная окружения %s не задана. Скопируйте .env.example в .env.', $name));
        }

        return $value;
    }

    private static function optional(string $name, string $default): string
    {
        $value = getenv($name);

        return false === $value || '' === $value ? $default : $value;
    }
}
