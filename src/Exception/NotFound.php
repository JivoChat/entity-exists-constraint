<?php

declare(strict_types=1);

namespace JivoChat\Validator\Constraint\Exception;

use DomainException;

final class NotFound extends DomainException
{
    public static function entity(string $entity): self
    {
        return new self(sprintf('Entity %s not found', $entity));
    }
}