<?php

declare(strict_types=1);

namespace Tempest\Database\QueryStatements;

use Tempest\Database\DatabaseDialect;
use Tempest\Database\QueryStatement;
use Tempest\Database\UnsupportedDialect;

final readonly class SetForeignKeyChecksStatement implements QueryStatement
{
    use CanExecuteStatement;

    public function __construct(
        public bool $enable = true,
    ) {
    }

    public function compile(DatabaseDialect $dialect): string
    {
        return match ($dialect) {
            DatabaseDialect::MYSQL => sprintf('SET FOREIGN_KEY_CHECKS = %s', $this->enable ? '1' : '0'),
            DatabaseDialect::SQLITE => sprintf('PRAGMA foreign_keys = %s', $this->enable ? '1' : '0'),
            default => throw new UnsupportedDialect(),
        };
    }
}
