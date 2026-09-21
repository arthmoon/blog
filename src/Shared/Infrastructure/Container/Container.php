<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Container;

/**
 * Контейнер зависимостей на фабриках, без автосвязывания.
 *
 * Автосвязывание по типам выглядит удобнее, но требует рефлексии на каждом
 * запросе и прячет граф зависимостей: чтобы понять, что во что подставляется,
 * пришлось бы читать конструкторы. Здесь вся сборка описана явно в одном
 * файле конфигурации и читается сверху вниз.
 *
 * Имена методов те же, что в PSR-11, но интерфейс не реализуется: он нужен,
 * когда контейнер потребляет чужой код, а у нас такого потребителя нет.
 */
final class Container
{
    /** @var array<string, callable(self): mixed> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Цепочка сервисов, которые сейчас собираются. Нужна, чтобы цикл
     * превратился во внятное сообщение, а не в исчерпание стека.
     *
     * @var list<string>
     */
    private array $resolving = [];

    /**
     * @param callable(self): mixed $factory
     */
    public function set(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }

    /**
     * Сервис создаётся при первом обращении и переиспользуется дальше.
     * Соединение с базой и Smarty поднимать на каждый вызов незачем.
     */
    public function get(string $id): mixed
    {
        if (\array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new ServiceNotFound($id);
        }

        if (\in_array($id, $this->resolving, true)) {
            throw new CircularDependency($this->resolving, $id);
        }

        $this->resolving[] = $id;

        try {
            $instance = ($this->factories[$id])($this);
        } finally {
            array_pop($this->resolving);
        }

        return $this->instances[$id] = $instance;
    }
}
