<?php

namespace App\System\Application\Database;

use App\System\Application\ApplicationSchema;
use App\System\Application\Field;
use App\System\Application\Property;
use App\System\Configuration\ApplicationConfig;
use App\System\Configuration\ConfigStore;
use App\System\Constructs\Cacheable;
use App\System\Helpers\Timer;
use App\System\RepositoryManager;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;

class Repository extends Cacheable
{
    private string $appId;
    private array  $config = [];

    private bool              $auth   = false;
    private ApplicationSchema $schema;
    private mixed             $sortBy = [];

    private array $fields = [];
    /** @var string */
    private string $slugField    = 'id';
    private        $exposedField = null;
    private array  $joins        = [];
    private array  $subQuery     = [];

    private array $data       = [];
    private array $dataByPk   = [];
    private array $dataBySlug = [];

    private array $activeRow  = [];
    private int   $primaryKey = 0;
    private int   $iterator   = 0;

    public function __construct(
        private RepositoryManager $repositoryManager,
        EntityManagerInterface $entityManager,
        private Timer $timer,
        private ConfigStore $configStore,
        ApplicationConfig $applicationConfig,
    ) {
        $this->appId = $applicationConfig->appId;
        $this->auth = $repositoryManager->isAuthorizedFully();

        parent::__construct('repo.' . $applicationConfig->appId . '.auth-' . intval($this->auth) . '.');

        $this->sortBy = $applicationConfig->sort;
        $this->config['exposed_columns'] = (array)$applicationConfig->meta['exposes'];
        $this->config['foreign_column'] = Property::foreignKey($applicationConfig->appId);

        $this->schema = new ApplicationSchema(
            $entityManager->getConnection(),
            Property::schemaName($applicationConfig->appId),
            $applicationConfig->fields
        );
        $this->prepareJoins((array)$applicationConfig->sources);
        $this->prepareFields($applicationConfig->fields);
        $this->prepareExposed((array)$applicationConfig->meta['exposes']);
    }

    public function getForeignColumn()
    {
        return $this->config['foreign_column'];
    }

    public function getExposedColumns()
    {
        return $this->config['exposed_columns'];
    }

    public function getData(array $columns = []): array
    {
        if (!$this->data) {
            $this->fetchData();
        }

        $result = [];
        if ($columns) {
            foreach ($this->data as $pk => $row) {
                $result[$pk] = array_diff_key(array_fill_keys($columns, null), $this->data);
            }

            return $result;
        }

        return $this->data;
    }

    public function getActiveRecord(): array
    {
        if (!$this->activeRow) {
            throw new LogicException('No data selected');
        }

        return $this->activeRow;
    }

    public function getRecord(int $id)
    {
        $this->primaryKey = 0;
        $this->activeRow = $this->remember('record.' . $id, function () use ($id) {
            if (!$this->data) {
                $this->fetchData();
            }

            return $this->dataByPk[$id] ?? [];
        }, 3600); // 1 hour

        if ($this->activeRow) {
            $this->primaryKey = $id;
        }

        return $this->activeRow;
    }

    public function getRecordBySlug(string $slug): array
    {
        $this->activeRow = $this->remember('record.slug.' . $slug, function () use ($slug) {
            if (!$this->data) {
                $this->fetchData();
            }

            return $this->dataBySlug[$slug] ?? [];
        }, 3600);
        $this->primaryKey = $this->activeRow['pk'] ?? 0;

        return $this->activeRow;
    }

    public function next()
    {
        if (!$this->data) {
            $this->fetchData();
        }

        $this->timer->start('record.next');
        if (!$this->activeRow) {
            $this->iterator = 0;
        } else {
            $this->iterator++;
        }
        $this->activeRow = $this->data[$this->iterator] ?? [];
        $this->primaryKey = $this->activeRow['pk'] ?? 0;
        $this->timer->stop('record.next');

        return $this->activeRow;
    }

    public function prev()
    {
        if (!$this->data) {
            $this->fetchData();
        }

        if ($this->iterator == 0) {
            return [];
        } else {
            $this->iterator--;
        }
        $this->activeRow = $this->data[$this->iterator] ?? [];
        $this->primaryKey = $this->activeRow['pk'] ?? 0;

        return $this->activeRow;
    }

    public function reset(): void
    {
        $this->iterator = 0;
        $this->activeRow = [];
    }

    /**
     * @param string      $field
     * @param string|null $source
     * @param null        $default
     *
     * @return \App\System\Application\Database\Column
     */
    public function getColumn(string $field, ?string $source = null, $default = null): Column|Junction
    {
        if (!$this->activeRow) {
            throw new LogicException('No data selected');
        }

        $this->timer->start($this->appId . '.column.' . $field . '-' . $source);

        if ($source) {
            $this->timer->stop($this->appId . '.column.' . $field . '-' . $source);

            return $this->remember('junction.' . $source . '-' . $field . '.row.' . $this->activeRow['pk'], function () use ($field, $source, $default) {
                return $this->createJunction($field, $source, $default);
            }, 60);
        }
        $this->timer->stop($this->appId . '.column.' . $field . '-' . $source);

        return new Column($field, $this->activeRow[$this->getFieldColumn($field)] ?? $default);
    }

    private function createJunction(string $field, ?string $source = null, $default = null): ValueInterface
    {
        $this->timer->start('column-junction.' . $field);

        $fieldColumn = $this->getFieldColumn($field);
        if ($this->hasSource($source, $field)) {
            $fieldColumn = $this->getFieldColumn($field, true);
            $value = new Column($field, $this->activeRow[$fieldColumn] ?? $default);
        }

        $application = $this->config['sources'][$source]['application'];
        $repo = $this->repositoryManager->getRepository($application);
        $foreignColumn = $repo->getForeignColumn();
        $foreignKey = $this->activeRow[$foreignColumn] ?? null;
        if ($foreignKey === null) {
            // fixme: reverse application keys -> get query for this
            $this->timer->stop('column-junction.' . $field);
            if (!$value instanceof ValueInterface) {
                $value = new Column('');
            }

            return $value;
        }

        // fixme: replace with latter
        if (is_array(@unserialize($foreignKey))) {
            $value = unserialize($foreignKey);

            return $this->createJunctionList($source, $field, ...$value);
        } elseif (is_array(json_decode($foreignKey, true))) {
            $value = json_decode($foreignKey, true);

            return $this->createJunctionList($source, $field, ...$value);
        }

        $repo->getRecord($foreignKey);
        if (!isset($value) && $foreignColumn != $fieldColumn && isset($this->activeRow[$fieldColumn])) {
            $value = new Column($field, $this->activeRow[$fieldColumn] ?? null);
        } else {
            $fields = $this->fields[$field]['source']['fields']['columns'];
            $fieldAlias = $this->fields[$field]['source']['fields']['alias'];
            $fieldValue = [];
            foreach ($fields as $f) {
                $fieldValue[] = $repo->getColumn($f)->getValue();
            }
            $value = new Column($fieldAlias, implode(' ', $fieldValue));
        }

        // fixme: replace with latter
        if (is_array(@unserialize($value->getValue()))) {
            $value = unserialize($value->getValue());

            return $this->createJunctionList($source, $field, ...$value);
        } elseif (is_array(json_decode($value->getValue(), true))) {
            $value = json_decode($value->getValue(), true);

            return $this->createJunctionList($source, $field, ...$value);
        }

        $exposed = $repo->getColumn('_exposed');
        $slug = $repo->getColumn('_slug');

        $this->timer->stop('column-junction.' . $field);

        return new Junction($source, $foreignKey, $value, $exposed, $slug);
    }

    private function createJunctionList(string $source, string $field, ...$primaryKeys): JunctionList
    {
        $this->timer->start('column-junction.list.' . $field);

        $application = $this->config['sources'][$source]['application'];
        $repo = $this->repositoryManager->getRepository($application);

        $list = [];
        foreach ($primaryKeys as $key) {
            $repo->reset();
            $repo->getRecord($key);

            $fields = $this->fields[$field]['source']['fields']['columns'];
            $fieldAlias = $this->fields[$field]['source']['fields']['alias'];
            $fieldValue = [];
            foreach ($fields as $f) {
                $fieldValue[] = $repo->getColumn($f)->getValue();
            }
            $value = new Column($fieldAlias, implode(' ', $fieldValue));
            $list[] = new Junction($source, $key, $value, $repo->getColumn('_exposed'), $repo->getColumn('_slug'));
        }
        $this->timer->stop('column-junction.list.' . $field);

        return new JunctionList($source, ...$list);
    }

    public function getColumns(array $columns, $default = null): array
    {
        if (!$this->activeRow) {
            throw new LogicException('No data selected');
        }

        $result = [];
        foreach ($columns as $c) {
            $result[$c] = $this->activeRow[$c] ?? $default;
        }

        return $result;
    }

    public function getApplicationReference(string $field): ?ApplicationReference
    {
        if (!$this->activeRow) {
            throw new LogicException('No data selected');
        }

        if (($source = ($this->fields[$field]['subQuery'] ?? null)) && ($exposed = $this->getExposedData())) {
            if ($this->getColumn($field, $source)->getValue()) {
                return new ApplicationReference($source, $exposed);
            }
        }

        return null;
    }

    /**
     * Get list of foreign data, associated by primary key
     *
     * @param string $sourceAlias
     * @param array  $columns
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getForeignData(string $sourceAlias, array $columns = []): array
    {
        $repo = $this->getForeignRepository($sourceAlias);
        $result = $repo->getData($columns);

        return $result;
    }

    public function filterData(array $conditions): void
    {
        $pk = $conditions['id'] ?? ($conditions['pk'] ?? null);
        if ($pk) {
            $this->data = $this->getRecord($pk);
        } elseif (isset($conditions[$this->slugField])) {
            $this->data = $this->getRecordBySlug($conditions[$this->slugField]);
        } else {
            $this->filterUnknownColumns($conditions);
            $this->fetchData($conditions);
        }
    }

    public function getExposedData($default = null)
    {
        if (!$this->activeRow) {
            throw new LogicException('No data selected');
        }
        if (!$this->exposedField) {
            throw new LogicException('Application doesnt support exposed field');
        }

        return $this->activeRow['_exposed'] ?? $default;
    }

    public function getCount(string $column, $value): int // todo
    {
        return count($this->data);
    }

    public function getDistinctCount(string $column, $value)
    {
        $data = array_filter(array_column($this->data, $column), function ($v) use ($value) {
            return $v == $value;
        });

        return count($data);
    }

    public function getDistinct(string $column): array
    {
        if (!$this->data) {
            $this->fetchData();
        }

        return array_unique(array_column($this->data, $column));
    }

    public function persist(array $data): void
    {
        $this->filterUnknownColumns($data);

        $cacheKeys = ['data', 'record.' . $this->primaryKey];
        $persistData = [];

        /**
         * @var Field $field
         * @var mixed $value
         */
        foreach ($data as $field => $value) {
            $field = $this->configStore->getApplicationField($this->appId, $field);
            switch ($field->getFormType()) {
                case 'file':
                    if ($value instanceof UploadedFile) {
                        $fileName = md5($value->getClientOriginalName() . time()) . '.' . $value->getClientOriginalExtension();
                        $fileType = $field->getDisplayType() == 'image' ? ConfigStore::DIR_IMAGES : ConfigStore::DIR_FILES;
                        $value->move($this->configStore->getDirectory($fileType, $this->appId, null, true), $fileName);

                        $value = $fileName;
                    }
                    break;
                case 'choice':
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                    break;
                case 'checkbox':
                    $value = intval($value);
                    break;
                case 'date':
                    if ($value instanceof DateTime) {
                        $value = $value->format('Y-m-d');
                    }
                    break;
                case 'datetime':
                    if ($value instanceof DateTime) {
                        $value = $value->format('Y-m-d H:i:s');
                    }
                    break;
                case 'time':
                    if ($value instanceof DateTime) {
                        $value = $value->format('H:i:s');
                    }
                    break;
            }
            $persistData[$field->getSchema(true)['column']] = $value;

            // remove potential junctions
            if ($field->getSourceIdentifier()) {
                $cacheKeys[] = 'junction.' . $field->getSourceIdentifier() . '-' . $field->getId() . '.row.' . $this->primaryKey;
            }
        }

        if ($this->slugField != 'id') {
            $slugField = $this->configStore->getApplicationField($this->appId, $this->slugField);
            try {
                $currentValue = $this->getColumn($this->slugField)->getValue();
            } catch (LogicException $e) {
                $currentValue = null;
            }

            $slugContext = [];
            foreach ($slugField->getPointer()['fields'] as $fieldId) {
                $field = $this->configStore->getApplicationField($this->appId, $fieldId);
                $slugFieldData = $data[$fieldId] ?? null;
                if ($slugFieldData instanceof DateTime) {
                    switch ($field->getFormType()) {
                        case 'date':
                        case 'datetime':
                            $slugFieldData = $slugFieldData->format('Y-m-d');
                            break;
                        case 'time':
                            $slugFieldData = $slugFieldData->format('H-i-s');
                            break;
                    }
                }
                $slugContext[] = $slugFieldData;
            }
            $newValue = new AsciiSlugger()->slug(implode('-', $slugContext))->toString();
            if ($currentValue != $newValue) {
                $cacheKeys[] = 'record.slug.' . $newValue;
                if (isset($this->dataBySlug[$newValue])) {
                    throw new InvalidArgumentException("There's already a record with this value for " . $this->slugField); // fixme
                }
                $persistData[$this->slugField] = $newValue;
            }

            $cacheKeys[] = 'record.slug.' . $currentValue;
        }

        $this->schema->persist($persistData, $this->primaryKey);
        $this->clearCacheKeys($cacheKeys);
    }

    public function deleteBy(array $params): void
    {
        if (!$this->schema->delete($params)) {
            throw new InvalidArgumentException('Could not delete records with given params');
        }

        $this->clearCacheKeys(['data']);
    }

    public function deleteRecord(int $primaryKey): void
    {
        if (!$this->getRecord($primaryKey)) {
            throw new InvalidArgumentException('There is no record with ID ' . $primaryKey);
        }

        if (!$this->schema->delete(['id' => $primaryKey])) {
            throw new InvalidArgumentException('Could not delete records with given params');
        }

        $this->clearCacheKeys(['data']);
    }

    private function fetchData(array $conditions = []): void
    {
        $key = 'data';
        if ($conditions) {
            $key .= '.cond.' . md5(json_encode($conditions));
        }
        $data = $this->remember($key, function () use ($conditions) {
            $result = [
                '_default' => [],
                '_by_pk'   => [],
                '_by_slug' => [],
            ];
            $data = $this->schema->getData($conditions, $this->getAuthorizedColumns(), $this->sortBy, $this->joins, $this->subQuery);
            foreach ($data as $row) {
                $result['_default'][] = $row;
                $result['_by_pk'][$row['pk']] = $row;
                $result['_by_slug'][$row['_slug']] = $row;
            }

            return $result;
        }, $conditions ? 30 : 60); // seconds

        $this->data = $data['_default'];
        $this->dataByPk = $data['_by_pk'];
        $this->dataBySlug = $data['_by_slug'];
    }

    private function getAuthorizedColumns(): array
    {
        $cols = [];
        foreach ($this->fields as $f) {
            if (!$f['authorized'] || !$f['column']) {
                continue;
            }
            $cols[] = $f['column'] . ($f['alias'] ? ' as ' . $f['alias'] : '');
        }
        $cols[] = $this->slugField . ' as _slug';
        if ($this->exposedField) {
            $cols[] = ['fields' => $this->exposedField, 'alias' => '_exposed', 'concatenate' => true];
        }

        return $cols;
    }

    private function filterUnknownColumns(array &$columns): void
    {
        $columns = array_filter($columns, function ($v) {
            return isset($this->fields[$v]) && $this->fields[$v]['authorized'];
        }, ctype_digit(key($columns)) ? 0 : ARRAY_FILTER_USE_KEY);
    }

    private function prepareJoins(array $sources): void
    {
        $this->config['sources'] = $this->remember('config.sources', function () use ($sources) {
            $config = [];

            foreach ($sources as $alias => $source) {
                $config[$alias] = [
                    'application' => $source['application'],
                    'foreign_key' => $source['foreign_column'],
                    'columns'     => [],
                ];

                if (in_array($source['function'], ['count', 'count_in'])) {
                    $this->config['sources'][$alias]['columns'][] = 'count';
                }
            }

            return $config;
        });

        $joins = $this->remember('config.joins', function () use ($sources) {
            $result = [
                'subQueries' => [],
                'joins'      => [],
            ];

            $i = 0;
            foreach ($sources as $alias => $source) {
                if (in_array($source['function'], ['count', 'count_in'])) {
                    $result['subQueries'][$alias] = [
                        'from'       => Property::schemaName($source['application']),
                        'conditions' => [
                            'id' => $source['column'],
                        ],
                        'alias'      => 'count',
                    ];
                    if ($source['function'] == 'count_in') {
                        $result['subQueries'][$alias]['conditions']['id'] = ['in' => $source['column']];
                    }
                    continue;
                }
                if (in_array($source['function'], ['find_in', 'find_in_max'])) {
                    $result['subQueries'][$alias] = [
                        'from'       => Property::schemaName($source['application']),
                        'conditions' => [
                            'id' => ['in' => $source['column']],
                        ],
                        'alias'      => 'find_in',
                    ];
                    if ($source['function'] == 'find_in_max') {
                        $result['subQueries'][$alias]['function'] = 'max';
                    }
                    continue;
                }

                $result['joins'][$alias] = [
                    'application'   => $source['application'],
                    'from'          => Property::schemaName($source['application']),
                    'joinType'      => 'left',
                    'table'         => null,
                    'column'        => $source['foreign_column'],
                    'schema_column' => $source['column'],
                    'select'        => [],
                    'position'      => $i,
                ];

                if ($source['join_source']) {
                    $result['joins'][$alias]['table'] = $source['join_source'];
                    $result['joins'][$alias]['position'] = count($sources); // make sure it's last to prevent unknown aliases
                }
                $i++;
            }

            uasort($result['joins'], function ($a, $b) {
                return $a['position'] <=> $b['position'];
            });

            return $result;
        });

        $this->joins = $joins['joins'];
        $this->subQuery = $joins['subQueries'];
    }

    /**
     * @param Field[] $fields
     */
    private function prepareFields(array $fields): void
    {
        $this->slugField = $this->remember('fields_slug', function () use ($fields) {
            foreach ($fields as $field) {
                if ($field->isSlug()) {
                    return $field->getId();
                }
            }

            return 'id';
        });
        $raw = $this->remember('fields_calculated', function () use ($fields) {
            $result = [
                'config'   => $this->config,
                'joins'    => $this->joins,
                'subQuery' => $this->subQuery,
                'fields'   => [
                    'pk' => [
                        'authorized'     => true,
                        'column'         => 'id',
                        'alias'          => 'pk',
                        'source'         => null,
                        'type'           => 'integer',
                        'formType'       => null,
                        'subQuery'       => false,
                        'pointer_fields' => [],
                    ],
                ],
            ];

            foreach ($fields as $alias => $field) {
                if ($field->isSlug()) {
                    continue;
                }

                $source = $this->getFieldSource($field);
                $subQuery = false;
                if ($source) {
                    $result['config']['sources'][$source['alias']]['columns'][] = $source['fields']['alias']; // add column reference to source config

                    if (!$field->isMultipleChoice()) {
                        $result['joins'][$source['alias']]['select'][] = $source['fields'];
                        $result['joins'][$source['alias']]['joinType'] = $field->isRequired() ? 'inner' : 'left';
                    }
                } elseif (!empty($this->subQuery[$field->getSourceIdentifier()])) {
                    $result['config']['sources'][$field->getSourceIdentifier()]['columns'][] = $field->getId(); // add column reference to source config
                    $result['subQuery'][$field->getSourceIdentifier()]['alias'] = $field->getId();
                    $subQuery = $field->getSourceIdentifier();
                }

                $result['fields'][$field->getId()] = [
                    'column'         => $field->getSchema()['column'] ?? null,
                    'authorized'     => $this->auth || $field->isVisible(null), // fixme: add proper module OR bypass module
                    'alias'          => null,
                    'source'         => $source,
                    'subQuery'       => $subQuery,
                    'type'           => $field->getDisplayType(),
                    'formType'       => $field->getFormType(),
                    'pointer_fields' => $field->getPointer()['fields'] ?? [],
                ];
            }

            return $result;
        });

        // overwrite attributes with new calculations
        $this->config = $raw['config'];
        $this->joins = $raw['joins'];
        $this->subQuery = $raw['subQuery'];

        $raw_fields = $raw['fields'];
        $this->fields = $this->remember('fields_pointers', function () use ($raw_fields) {
            $fields = $raw_fields;

            foreach ($fields as $field) {
                if ($field['authorized']) {
                    foreach ($field['pointer_fields'] as $alias) {
                        $fields[$alias]['authorized'] = true;
                    }
                }
            }

            return $fields;
        });
    }

    private function getFieldSource(Field $field): ?array
    {
        $source = null;
        if ($field->getSourceIdentifier() && isset($this->joins[$field->getSourceIdentifier()])) {
            $source = [
                'alias' => $field->getSourceIdentifier(),
            ];

            if ($field->getSourceFields()) {
                $source['fields'] = [
                    'columns' => $field->getSourceFields(),
                    'alias'   => $field->getId(),
                ];
            } else {
                $sourceRepo = $this->repositoryManager->getRepository($this->joins[$field->getSourceIdentifier()]['application']);
                $source['fields'] = [
                    'columns' => $sourceRepo->getExposedColumns(),
                    'alias'   => '_exposed', // {_alias}_exposed
                ];
            }
        }

        return $source;
    }

    private function prepareExposed(array $exposedFieldIdentifiers): void
    {
        $fields = [];
        foreach ($exposedFieldIdentifiers as $id) {
            if ($this->fields[$id]['column']) { // fixme: should authorize?
                $fields[] = $this->fields[$id]['column'];
            }
        }

        $this->exposedField = $fields;
    }

    private function hasSource(string $alias, ?string $column = null): bool
    {
        if (!empty($this->config['sources'][$alias])) {
            if ($column) {
                return in_array($column, $this->config['sources'][$alias]['columns']);
            }

            return true;
        }

        return false;
    }

    private function getForeignRepository(string $source): Repository
    {
        if (!$this->hasSource($source)) {
            throw new InvalidArgumentException('This application has no connection to a source aliased: ' . $source);
        }

        return $this->repositoryManager->getRepository($this->config['sources'][$source]['application']);
    }

    private function getFieldColumn(string $fieldId, bool $virtualColumn = false)
    {
        if (str_starts_with($fieldId, '_')) { // virtual field
            return $this->getVirtualField($fieldId);
        }

        if (!isset($this->fields[$fieldId])) {
            throw new InvalidArgumentException('Unknown field alias "' . $fieldId . '"');
        }

        if ($this->fields[$fieldId]['source'] && $virtualColumn) {
            return $this->fields[$fieldId]['source']['alias'] . '__' . $fieldId;
        } elseif ($this->fields[$fieldId]['subQuery']) {
            return $this->fields[$fieldId]['subQuery'] . '__' . $fieldId;
        }

        return $this->fields[$fieldId]['column'];
    }

    private function getVirtualField(string $field): ?string
    {
        return in_array($field, ['_exposed', '_slug', '_title']) ? $field : null;
    }
}