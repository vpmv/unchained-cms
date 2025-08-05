<?php

namespace App\System\Application;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

readonly class ApplicationSchema
{
    /**
     * @param \Doctrine\DBAL\Connection       $connection
     * @param string                          $table
     * @param \App\System\Application\Field[] $fields
     */
    public function __construct(private Connection $connection, private string $table, private array $fields)
    {
        $this->validateTable(); // fixme: cache
    }

    public function countRecords(): int
    {
        $b     = $this->connection->createQueryBuilder()
            ->from($this->table, '_curr')
            ->select('COUNT(_curr.id)');
        $count = $b->executeQuery()->fetchOne();
        if (!$count) {
            return 0;
        }

        return (int)$count;
    }

    public function getData(array $conditions = [], array $columns = [], array $sort = [], array $joins = [], array $subQueries = []): array
    {
        $columns = $columns ?: ['*'];

        $b = $this->connection->createQueryBuilder()
            ->from($this->table, '_curr');

        foreach ($columns as $column) {
            if (is_array($column) && !empty($column['fields'])) {
                $column = $this->concatSelector($column['fields'], $column['alias'], $column['table'] ?? '_curr');
                $b->addSelect($column);
                continue;
            } elseif (is_array($column)) {
                throw new \InvalidArgumentException('Cannot process column configuration:' . json_encode($column));
            }
            $b->addSelect('_curr.' . $column);
        }

        foreach ($subQueries as $alias => $query) {
            $subBuilder = $this->connection->createQueryBuilder();
            $subBuilder
                ->select($query['function'] . '(' . $query['field'] . ')') // e.g.: count(id) / max(date) / avg(rating)
                ->from($query['from'], '_' . $alias);
            foreach ($query['conditions'] as $key => $cond) {
                if (is_array($cond)) {
                    if (!empty($cond['in'])) {// fixme: prettify
                        $subBuilder->andWhere("LOCATE(concat(concat(\",\",_curr.$key), \",\"), REPLACE(REPLACE(_$alias.$cond[in], \"]\", \",\"), \"[\", \",\")) > 0");
                    }
                    continue;
                }
                $subBuilder->andWhere("_curr.$key = _$alias.$cond");
            }
            $b->addSelect('(' . $subBuilder->getSQL() . ') as ' . $alias . '__' . $query['alias']);
        }

        foreach ($joins as $alias => $join) {
            if ($join['joinType'] == 'inner') {
                $b->innerJoin($join['table'] ?? '_curr', $join['from'], $alias, sprintf('%s.%s = %s.%s', $alias, $join['schema_column'], $join['table'] ?? '_curr', $join['column']));
            } elseif ($join['joinType'] == 'left') {
                $b->leftJoin($join['table'] ?? '_curr', $join['from'], $alias, sprintf('%s.%s = %s.%s', $alias, $join['schema_column'], $join['table'] ?? '_curr', $join['column']));
            } elseif ($join['joinType'] == 'right') {
                $b->rightJoin($join['table'] ?? '_curr', $join['from'], $alias, sprintf('%s.%s = %s.%s', $alias, $join['schema_column'], $join['table'] ?? '_curr', $join['column']));
            }

            foreach ($join['select'] as $joinColumn) {
                $joinColumn = $this->concatSelector($joinColumn['columns'], $alias . '__' . $joinColumn['alias'], $alias); // bookshelf__title,
                $b->addSelect($joinColumn);
            }
        }

        foreach ($conditions as $key => $val) {
            if (is_array($val)) {
                $b->andWhere("$key IN (" . implode(',', $val) . ")");
                continue;
            }

            // todo: configure condition logic
            if (in_array($key, ['_active'])) {
                $b->andWhere("`_curr`.`$key` >= :$key");
            } else {
                $b->andWhere("`_curr`.`$key` = :$key");
            }
        }

        if (($conditions['_active'] ?? 1) < 1) {
            $sort = ['_active' => 'DESC'] + $sort;
        }
        foreach ($sort as $key => $dir) {
            $b->addOrderBy('_curr.' . $key, $dir);
        }

        $b->setParameters($conditions);
        $rows = $b->executeQuery()->fetchAllAssociative() ?: [];

        return $rows;
    }

    public function delete(array $params): bool
    {
        if (!$params) {
            return false;
        }

        try {
            $this->connection->delete($this->table, $params);
        } catch (Exception $e) {
            return false;
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    public function persist(array $data, $id = null): void
    {
        $data = array_filter($data, function ($v) {
            return $v !== null; // strong type null only
        });

        $keys = $values = $updates = [];
        foreach ($data as $k => $v) {
            if ($v instanceof \DateTime) {
                $v = $v->format('Y-m-d H:i:s');
            }

            $key   = "`$k`";
            $value = $this->connection->quote($v);

            $keys[]    = $key;
            $values[]  = $value;
            $updates[] = "$key = $value";
        }

        if (!$keys || !$values) {
            return;
        }

        if ($id > 0) {
            $query = sprintf('UPDATE `%s` SET %s WHERE `id` = %s', $this->table, implode(', ', $updates), $id);
        } else {
            $query = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $this->table, implode(',', $keys), implode(',', $values)); // todo: id
        }


        $this->connection->executeQuery($query);
    }

    private function validateTable(): void
    {
        $res = $this->connection->executeQuery('SHOW TABLES LIKE "' . $this->table . '"')->fetchAllAssociative();
        if (empty($res)) {
            $this->createSchema();

            return;
        }

        $res        = $this->connection->executeQuery('SHOW COLUMNS FROM ' . $this->table)->fetchAllAssociative();
        $columns    = array_column($res, 'Field');
        $newColumns = [];
        foreach ($this->fields as $name => $field) {
            $def = $this->getColumnDefinition($name, $field);
            if ($def && !in_array($def['column'], $columns)) {
                $newColumns[] = $def;
            }
        }
        if (!in_array('_active', $columns)) {
            $newColumns[] = ['fmt' => '`_active` tinyint(1) default 1 not null'];
        }

        if ($newColumns) {
            $this->addColumns($newColumns);
        }
    }

    /**
     * @return void
     * @throws \Doctrine\DBAL\Exception
     *
     * @todo UUIDs not implemented;
     *       Functionally no difference when everything is converted to JSON
     */
    private function createSchema(): void
    {
        $cols = [
            '`id` int(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY',
            '`_active` tinyint(1) default 1 not null',
            //'`_uuid` uuid not null DEFAULT uuid()',
        ];
        foreach ($this->fields as $name => $field) {
            if ($def = $this->getColumnDefinition($name, $field)) {
                $cols[] = $def['fmt'];
            }
        }

        $query = sprintf('CREATE TABLE IF NOT EXISTS `%s` (%s) DEFAULT CHARACTER SET %s', $this->table, implode(', ', $cols), 'utf8');
        $this->connection->executeQuery($query); // todo fix exceptionslistener
    }

    private function addColumns(array $definitions): void
    {
        foreach ($definitions as &$definition) {
            $definition = 'ADD COLUMN ' . $definition['fmt'];
        }

        $query = sprintf('ALTER TABLE %s %s', $this->table, implode(', ', $definitions));
        $this->connection->executeQuery($query); // todo fix exceptionslistener
    }

    private function getColumnDefinition(string $name, Field $field): array
    {
        if ($schema = $field->getSchema()) {
            $result        = $schema;
            $result['fmt'] = sprintf('`%s` %s%s %s %s %s',
                $schema['column'] ?? $name,
                $schema['type'],
                $schema['length'] ? '(' . $schema['length'] . ')' : '',
                !$schema['nullable'] ? 'NOT NULL' : '',
                strtoupper(implode(' ', $schema['options'])),
                $schema['default'] ? ' DEFAULT ' . $schema['default'] : '',
            );

            return $result;
        }

        return [];
    }

    private function concatSelector(array $columns, string $name, ?string $tableAlias = null): string
    {
        if (count($columns) > 1) {
            foreach ($columns as &$col) {
                $col = "coalesce($tableAlias.$col)";
            }
            $select = 'concat_ws(" ", ' . implode(', ', $columns) . ')';
        } else {
            $select = $tableAlias . '.' . $columns[0];
        }

        return $select . ' as ' . $name;
    }
}