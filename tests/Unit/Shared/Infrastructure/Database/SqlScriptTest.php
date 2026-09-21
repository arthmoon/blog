<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Database;

use App\Shared\Infrastructure\Database\SqlScript;
use PHPUnit\Framework\TestCase;

final class SqlScriptTest extends TestCase
{
    public function testSplitsStatements(): void
    {
        $script = SqlScript::fromString('CREATE TABLE a (id INT); CREATE TABLE b (id INT);');

        self::assertSame(
            ['CREATE TABLE a (id INT)', 'CREATE TABLE b (id INT)'],
            $script->statements(),
        );
    }

    public function testTrailingSemicolonDoesNotProduceEmptyStatement(): void
    {
        self::assertCount(1, SqlScript::fromString("SELECT 1;\n\n")->statements());
    }

    public function testMissingTrailingSemicolonIsFine(): void
    {
        self::assertSame(['SELECT 1'], SqlScript::fromString('SELECT 1')->statements());
    }

    public function testStripsLineComments(): void
    {
        $sql = <<<'SQL'
            -- Категории блога
            CREATE TABLE categories (
                id INT
            );
            -- и всё
            SQL;

        $statements = SqlScript::fromString($sql)->statements();

        self::assertCount(1, $statements);
        self::assertStringStartsWith('CREATE TABLE categories', $statements[0]);
        self::assertStringNotContainsString('Категории блога', $statements[0]);
    }

    public function testEmptyScriptHasNoStatements(): void
    {
        self::assertSame([], SqlScript::fromString("\n  \n-- только комментарий\n")->statements());
    }

    public function testReadingMissingFileFails(): void
    {
        $this->expectException(\RuntimeException::class);

        SqlScript::fromFile('/nope/missing.sql');
    }
}
