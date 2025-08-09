<?php

namespace App\System\Application\Database;

use Doctrine\DBAL\Query\QueryBuilder;

enum ParamType
{
    case String;
    case Integer;
    case Boolean;
    case Array;
}

class QueryParam
{
    public function __construct(
        public string $column,
        public mixed $value,
        public ParamType $type = ParamType::String,
        public string $operator = '=',
        public string $context = '_curr',
    ) {
    }

    public static function create(string $column, mixed $value): QueryParam
    {
        return match ($value) {
            is_int($value) => new QueryParam($column, $value, ParamType::Integer),
            is_bool($value) => new QueryParam($column, $value, ParamType::Boolean),
            default => new QueryParam($column, $value, ParamType::String),
        };
    }

    public function parse(QueryBuilder $builder): void
    {
        if ($this->type == ParamType::Array || is_array($this->value)) {
            $builder->andWhere($this->column . " IN (" . implode(',', $this->value) . ")"); // fixme: context
            return;
        }

        $builder->andWhere($this->getSQL());
        $builder->setParameter($this->column, $this->value);
    }

    public function getSQL(): string
    {
        return $this->context . '.' . $this->column . ' ' . $this->operator . ' ' . $this->value;
    }
}