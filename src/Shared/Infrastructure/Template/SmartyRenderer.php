<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Template;

use Smarty\Smarty;

/**
 * Реализация на Smarty 5.
 *
 * Экранирование включено глобально через setEscapeHtml(true): расставлять
 * |escape руками — значит однажды забыть.
 *
 * Отключается оно только флагом вывода: {$value nofilter}. Через модификатор
 * не получится: авто-экранирование применяется после модификаторов
 * (Compile/PrintExpressionCompiler, строки 65 и 85), поэтому попытка вроде
 * |escape:'off' защиту не снимет — Smarty лишь сообщит о неизвестном типе
 * и вернёт строку как есть. Это удачно: опечатка в имени фильтра не
 * открывает дыру, а места с nofilter находятся поиском по одному слову.
 *
 * Кеширование результатов выключено. Оно имело бы смысл на статичных
 * страницах, но у нас на каждой есть счётчик просмотров, а разбираться
 * с {nocache} ради блога дороже, чем выигрыш.
 *
 * Smarty создаётся лениво: консольным командам шаблоны не нужны, и
 * незачем заводить каталог компиляции ради миграции.
 */
final class SmartyRenderer implements TemplateRenderer
{
    private ?Smarty $smarty = null;

    public function __construct(
        private readonly string $templateDir,
        private readonly string $compileDir,
        private readonly bool $debug = false,
    ) {
    }

    public function render(string $template, array $data = []): string
    {
        $smarty = $this->smarty();
        $smarty->assign($data);

        return $smarty->fetch($template);
    }

    private function smarty(): Smarty
    {
        if (null !== $this->smarty) {
            return $this->smarty;
        }

        if (!is_dir($this->compileDir) && !mkdir($this->compileDir, 0o775, true) && !is_dir($this->compileDir)) {
            throw new \RuntimeException(sprintf('Не удалось создать каталог компиляции %s.', $this->compileDir));
        }

        $smarty = new Smarty();
        $smarty->setTemplateDir($this->templateDir);
        $smarty->setCompileDir($this->compileDir);
        $smarty->setEscapeHtml(true);
        $smarty->setCaching(Smarty::CACHING_OFF);

        // В разработке шаблон перекомпилируется при каждом изменении,
        // в бою проверка отключается: файлы меняются только при выкате.
        $smarty->setCompileCheck($this->debug ? Smarty::COMPILECHECK_ON : Smarty::COMPILECHECK_OFF);

        $smarty->assign('fmt', new TemplateFunctions());

        return $this->smarty = $smarty;
    }
}
