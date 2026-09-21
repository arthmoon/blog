<?php

declare(strict_types=1);

namespace App\Blog\Domain\Exception;

/**
 * Статья обязана принадлежать хотя бы одной категории.
 *
 * В отличие от нарушенного формата значения, это бизнес-правило: его ловят
 * отдельно, чтобы показать редактору внятное сообщение, поэтому у него
 * собственный класс, а не общий InvalidArgumentException.
 */
final class PostWithoutCategory extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Статья должна принадлежать хотя бы одной категории.');
    }
}
