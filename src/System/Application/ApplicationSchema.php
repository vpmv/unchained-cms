<?php

namespace App\System\Application;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;

class ApplicationSchema
{
    private $connection;
    /** @var string */
    private $table;

    /** @var \App\System\Application\Field[] */
    private $fields = [];

    public function __construct(Connection $connection, $table, $fields)
    {
        $this->connection = $connection;
        $this->table      = $table;
        $this->fields     = $fields;

        $this->validateTable();
    }

    public function getData(array $conditions = [], array $columns = [], array $sort = [], array $joins = [], array $subQueries = [])
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
                ->select('count(id)')// fixme
                ->from($query['from'], '_' . $alias);
            foreach ($query['conditions'] as $key => $cond) {
                if (is_array($cond)) {
                    if (!empty($cond['in'])) {// fixme: prettify
                        $subBuilder->andWhere("LOCATE(\",\"+_curr.$key+\",\", REPLACE(REPLACE(_$alias.$cond[in], \"]\", \",\"), \"[\", \",\")) > 0"); // fixme: only works for json array
                    }
                    continue;
                }
                $subBuilder->andWhere("_curr.$key = _$alias.$cond");
            }
            $b->addSelect('(' . $subBuilder->getSQL() . ') as ' . $alias . '__' . $query['alias']);
        }

        foreach ($joins as $alias => $join) {
            if ($join['joinType'] == 'inner') {
                $b->innerJoin('_curr', $join['from'], $alias, $alias . '.' . $join['schema_column'] . ' = _curr.' . $join['column']);
            } elseif ($join['joinType'] == 'left') {
                $b->leftJoin('_curr', $join['from'], $alias, $alias . '.' . $join['schema_column'] . ' = _curr.' . $join['column']);
            } elseif ($join['joinType'] == 'right') {
                $b->rightJoin('_curr', $join['from'], $alias, $alias . '.' . $join['schema_column'] . ' = _curr.' . $join['column']);
            }

            foreach ($join['select'] as $joinColumn) {
                $joinColumn = $this->concatSelector($joinColumn['columns'], $alias . '__' . $joinColumn['alias'], $alias); // horses__name,
                $b->addSelect($joinColumn);
            }
        }

        foreach ($conditions as $key => $val) {
            if (is_array($val)) {
                $b->andWhere("$key IN (" . implode(',', $val) . ")");
                continue;
            }
            $b->andWhere("$key = :$key");
        }
        foreach ($sort as $key => $dir) {
            $b->addOrderBy('_curr.' . $key, $dir);
        }

        $b->setParameters($conditions);

        $rows = $b->execute()->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return $rows;
    }

    public function delete(array $params)
    {
        if (!$params) {
            return false;
        }

        try {
            $this->connection->delete($this->table, $params);
        } catch (DBALException $e) {
            return false;
        } catch (\InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    public function persist(array $data, $id = null)
    {
        $data = array_filter($data, function ($v) {
            return $v !== null; // strong type null only
        });

        foreach ($data as $k => $v) {
            if ($v instanceof \DateTime) {
                $v = $v->format('Y-m-d H:i:s');
            }
            $keys[]   = "`$k`";
            $values[] = $this->connection->quote($v);
        }

        if ($id > 0) {
            foreach ($keys as $i => $key) {
                $updates[] = "$key = $values[$i]";
            }
            $query = sprintf('UPDATE `%s` SET %s WHERE `id` = %s', $this->table, implode(', ', $updates), $id);
        } else {
            $query = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $this->table, implode(',', $keys), implode(',', $values)); // todo: id
        }

        $this->connection->executeQuery($query);
    }

    private function validateTable()
    {
        $res = $this->connection->executeQuery('SHOW TABLES LIKE "' . $this->table . '"')->fetchAll();
        if (empty($res)) {
            $this->createSchema();

            return;
        }

        $res     = $this->connection->executeQuery('SHOW COLUMNS FROM ' . $this->table)->fetchAll();
        $columns = array_column($res, 'Field');
        foreach ($this->fields as $name => $field) {
            $def = $this->getColumnDefinition($name, $field);
            if ($def && !in_array($def['column'], $columns)) {
                $this->addColumns([$def]);
            }
        }
    }

    private function createSchema()
    {
        $cols = ['`id` int(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY'];
        foreach ($this->fields as $name => $col) {
            if ($def = $this->getColumnDefinition($name, $col)) {
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
                $schema['default'] ? ' DEFAULT ' . $schema['default'] : ''
            );

            return $result;
        }

        return [];
    }

    private function concatSelector(array $columns, string $name, ?string $tableAlias = null)
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