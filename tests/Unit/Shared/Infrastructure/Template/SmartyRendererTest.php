<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Template;

use App\Shared\Infrastructure\Template\SmartyRenderer;
use PHPUnit\Framework\TestCase;

final class SmartyRendererTest extends TestCase
{
    private string $templates;

    private string $compiled;

    protected function setUp(): void
    {
        $root = sys_get_temp_dir() . '/blog-tpl-' . bin2hex(random_bytes(4));
        $this->templates = $root . '/templates';
        $this->compiled = $root . '/compiled';

        mkdir($this->templates, 0o775, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->templates, $this->compiled] as $directory) {
            foreach (glob($directory . '/*') ?: [] as $file) {
                unlink($file);
            }

            @rmdir($directory);
        }

        @rmdir(\dirname($this->templates));
    }

    public function testRendersTemplateToString(): void
    {
        $this->template('hello.tpl', 'Привет, {$name}!');

        self::assertSame('Привет, мир!', $this->renderer()->render('hello.tpl', ['name' => 'мир']));
    }

    /**
     * Ради этого теста и включалось глобальное экранирование: без него
     * заголовок статьи с тегами стал бы разметкой на странице.
     */
    public function testEscapesByDefault(): void
    {
        $this->template('unsafe.tpl', '{$value}');

        $output = $this->renderer()->render('unsafe.tpl', ['value' => '<script>alert(1)</script>']);

        self::assertStringNotContainsString('<script>', $output);
        self::assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testEscapingCanBeTurnedOffExplicitly(): void
    {
        $this->template('raw.tpl', "{\$value|escape:'off'}");

        self::assertSame('<b>жирный</b>', $this->renderer()->render('raw.tpl', ['value' => '<b>жирный</b>']));
    }

    public function testHelpersAreAvailableInTemplates(): void
    {
        $this->template('meta.tpl', '{$fmt->date($date)} — {$fmt->views($views)}');

        $output = $this->renderer()->render('meta.tpl', [
            'date' => new \DateTimeImmutable('2026-09-15'),
            'views' => 21,
        ]);

        self::assertSame('15 сентября 2026 — 21 просмотр', $output);
    }

    public function testCreatesCompileDirectoryOnDemand(): void
    {
        $this->template('hello.tpl', 'привет');

        self::assertDirectoryDoesNotExist($this->compiled);

        $this->renderer()->render('hello.tpl');

        self::assertDirectoryExists($this->compiled);
    }

    private function renderer(): SmartyRenderer
    {
        return new SmartyRenderer($this->templates, $this->compiled, debug: true);
    }

    private function template(string $name, string $contents): void
    {
        file_put_contents($this->templates . '/' . $name, $contents);
    }
}
