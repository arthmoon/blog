<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Config;

use App\Shared\Infrastructure\Config\DatabaseConfig;
use PHPUnit\Framework\TestCase;

final class DatabaseConfigTest extends TestCase
{
    /** @var list<string> */
    private const array VARIABLES = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];

    /** @var array<string, string|false> */
    private array $backup = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $name) {
            $this->backup[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->backup as $name => $value) {
            if (false === $value) {
                putenv($name);
            } else {
                putenv(sprintf('%s=%s', $name, $value));
            }
        }
    }

    public function testBuildsDsn(): void
    {
        $config = new DatabaseConfig('mysql', 3306, 'blog', 'blog', 'secret');

        self::assertSame('mysql:host=mysql;port=3306;dbname=blog;charset=utf8mb4', $config->dsn());
    }

    public function testReadsEnvironment(): void
    {
        putenv('DB_HOST=db');
        putenv('DB_PORT=3307');
        putenv('DB_NAME=blog_test');
        putenv('DB_USER=root');
        putenv('DB_PASSWORD=secret');

        $config = DatabaseConfig::fromEnvironment();

        self::assertSame('db', $config->host);
        self::assertSame(3307, $config->port);
        self::assertSame('blog_test', $config->database);
        self::assertSame('root', $config->user);
        self::assertSame('secret', $config->password);
    }

    public function testPortAndPasswordHaveDefaults(): void
    {
        putenv('DB_HOST=db');
        putenv('DB_NAME=blog');
        putenv('DB_USER=root');

        $config = DatabaseConfig::fromEnvironment();

        self::assertSame(3306, $config->port);
        self::assertSame('', $config->password);
    }

    public function testMissingRequiredVariableIsReported(): void
    {
        putenv('DB_HOST=db');
        putenv('DB_NAME=blog');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB_USER/');

        DatabaseConfig::fromEnvironment();
    }
}
