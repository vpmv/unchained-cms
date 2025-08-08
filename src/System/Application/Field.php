<?php

namespace App\System\Application;

use App\System\Application\Database\ApplicationReference;
use App\System\Application\Database\Column;
use App\System\Application\Database\Junction;
use App\System\Application\Database\JunctionList;
use App\System\Application\Database\ValueInterface;
use App\System\Application\Module\ApplicationModuleInterface;
use App\System\Application\Module\DashboardModule;
use App\System\Application\Module\FormModule;
use App\System\Application\Translation\TranslatableChoice;
use App\System\Configuration\ApplicationConfig;
use App\System\Configuration\ConfigStore;
use App\System\Constructs\Translatable;

class Field
{
    public const VALUE_DEFAULT = 0;
    public const VALUE_PUBLIC  = 1;
    public const VALUE_LOCAL   = 2;

    public const VALUE_UNSET = '-';

    public const CONSTRAINT_UNIQUE = 'unique';

    private $id;
    private $config       = [];
    private $moduleConfig = [];
    private $schema       = [];
    private $visibility   = [];
    private $extra        = [
        'ignored'    => false, // fixme: change name => not visible in form
        'constraint' => false,
        'pointer'    => [],
    ];

    private $data = [];

    public function __construct(
        private readonly string $appId, // fixme
        string $identifier,
        array $config,
        ConfigStore $configStore,
        ?ApplicationConfig $context = null,
    ) {
        $this->id = $identifier;

        $this->setConfiguration($config);
        $this->setSchema($config);
        $this->setSource($config, $configStore, $context);
        $this->setModuleConfiguration($config);
        $this->setExtra($config);
        $this->setVisibility($config);
    }

    public function getId()
    {
        return $this->id;
    }

    public function isSlug()
    {
        return $this->id == '_slug';
    }

    public function isRequired(): bool
    {
        return $this->moduleConfig['form']['required'];
    }

    public function getLabel(string $type = 'default'): ?string
    {
        return $this->moduleConfig['label'][$type] ?? null;
    }

    public function getDisplayType(): string
    {
        return $this->config['type'];
    }

    public function getFormType(bool $getTypeNamespace = false): string
    {
        $type = $this->config['form_type'];
        if ($getTypeNamespace) {
            $type = '\\Symfony\\Component\\Form\\Extension\\Core\\Type\\' . ucfirst($type) . 'Type';
        }

        return $type;
    }

    public function getDefaultValue()
    {
        return $this->config['default'];
    }

    public function getData(?string $key = null, $default = null)
    {
        if ($key) {
            return $this->data[$key] ?? $default;
        }

        return $this->data;
    }

    /**
     * @param array|string $key
     * @param null         $value
     */
    public function setData($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_replace($this->data, $key);

            return;
        }
        $this->data[$key] = $value;
    }

    public function getOutput(ApplicationModuleInterface $module): array
    {
        $data = [
            'visible'     => $this->isVisible($module),
            'value'       => $this->getData('value'),
            'raw'         => $this->getData('raw'),
            'link'        => $this->getData('url'),
            'title'       => $this->getData('title'),
            'reference'   => $this->getData('reference'),
            'transformed' => $this->getData('transformed', false),
            'field'       => [
                'type'        => $this->getDisplayType(),
                'form_type'   => $this->getFormType(),
                'column'      => $this->getId(),
                'default'     => $this->getDefaultValue(),
                'labels'      => [
                    'default'  => $this->getLabel(),
                    'enabled'  => $this->getLabel('enabled'),
                    'disabled' => $this->getLabel('disabled'),
                ],
                'source_id'   => $this->getSourceIdentifier(),
                'module'      => $this->getModuleConfig($module),
                'constraints' => $this->getConstraint(),
                //'source'    => $field->getSourceIdentifier() ? $this->getSource($field->getSourceIdentifier()) : null,
            ],
        ];

        if ($module instanceof DashboardModule) {
            if (!$data['field']['module']['sortable'] && $data['title']) {
                $data['field']['module']['sortable'] = true;
            }
        }

        return $data;
    }

    public function getModuleConfig(ApplicationModuleInterface $module): array
    {
        $config = $this->moduleConfig[$module->getName()] ?? [];
        if ($module instanceof FormModule) {
            $config += ['label' => $this->getLabel()];
        }

        return $config;
    }

    public function getSchema(bool $force = false): array
    {
        if ($force) {
            return $this->schema;
        }

        return $this->extra['ignored'] && $this->id != '_slug' ? [] : $this->schema;
    }

    public function getSourceIdentifier(): ?string
    {
        return $this->config['source']['id'] ?? null;
    }

    public function getSourceFields(): array
    {
        return $this->config['source']['visible'] ?? [];
    }

    public function hasConstraint(?string $type = null): bool
    {
        $hasConstraint = !empty($this->moduleConfig['constraint']);
        if (!$hasConstraint) {
            return false;
        } elseif (!$type) {
            return $hasConstraint;
        }

        return $this->moduleConfig['constraint'] == $type;
    }

    public function getConstraint()
    {
        return $this->extra['constraint'];
    }

    public function getChoiceOptions(): array
    {
        return $this->config['options'] ?? [];
    }

    public function isMultipleChoice(): bool
    {
        return $this->getFormType() == 'choice' && ($this->moduleConfig['form']['multiple'] ?? false);
    }

    public function getPointer(): ?array
    {
        if ($this->config['pointer']) {
            return $this->extra['pointer'];
        }

        return null;
    }

    /**
     * @param array $rowData
     *
     */
    public function setValue(Application $application, ValueInterface $value, ?ApplicationReference $reference = null): void
    {
        $this->setData([
            'value'       => null,
            'raw'         => $value ?? null,
            'url'         => null,
            'transformed' => false,
            'reference'   => null,
        ]);

        if ($value instanceof Column) {
            $this->setData('value', $value->getValue());
        } elseif ($value instanceof Junction) {
            $this->setData('value', $value->getValue());
            $this->setData('url', $application->getRoute($value->getApplication(), ['slug' => $value->getSlug()]));
            $this->setData('title', $value->getExposed());
        } elseif ($value instanceof JunctionList) {
            $values = $links = [];
            foreach ($value->getJunctions() as $junction) {
                $values[] = $junction->getValue();
                $links[] = $application->getRoute($junction->getApplication(), ['slug' => $junction->getSlug()]);
            }
            $this->setData('value', $values);
            $this->setData('url', $links);
        }

        if ($reference) {
            $route = $application->getRoute($reference->getApplicationAlias());
            $route->addParameters(['reference' => $reference->getValue()]);
            $this->setData('reference', $route);
        }

        if ($this->config['pointer']) {
            $pointerType = $this->extra['pointer']['type'];

            $context = [];
            foreach ($this->extra['pointer']['fields'] as $pointerFieldId) {
                $context[$pointerFieldId] = $application->getRepository()->getColumn($pointerFieldId)->getValue();
            }

            if ($pointerType == 'external') {
                $value = $application->resolveExtension($this->id, $context);
                if ($value instanceof Translatable) {
                    $value = $application->translate($value->getMessage(), $value->getArguments());
                }
                $this->setData('value', $value);
            } elseif ($pointerType != 'slug') { // fixme
                $this->setData('value', $context);
            }
        }

        $this->convertValue($application);
    }

    public function convertValue(Application $application, $mode = self::VALUE_DEFAULT)
    {
        $currentModule = $application->getCurrentModule();

        $publicHtmlPath = $application->getDirectory(ConfigStore::DIR_PUBLIC);
        $filesPath      = $application->getDirectory(ConfigStore::DIR_FILES);
        $imagesPath     = $application->getDirectory(ConfigStore::DIR_IMAGES);

        $value = $this->getData('value');
        if ($value === null || $value === self::VALUE_UNSET) {
            $this->setData('value');
            return;
        }

        if ($this->getSourceIdentifier()) {
            $filesPath  = $application->getDirectory(ConfigStore::DIR_FILES, $this->getSourceIdentifier());
            $imagesPath = $application->getDirectory(ConfigStore::DIR_IMAGES, $this->getSourceIdentifier());
        }

        switch ($this->getDisplayType()) {
            case 'boolean':
                $value = intval($value);
                break;
            case 'file':
                $this->setData('view', $filesPath . '/' . $value);
                $value = ($currentModule instanceof FormModule ? $publicHtmlPath . $filesPath : $filesPath) . '/' . $value;
                break;
            case 'image':
                $this->setData('view', $imagesPath . '/' . $value);
                $value = ($currentModule instanceof FormModule ? $publicHtmlPath . $imagesPath : $imagesPath) . '/' . $value;
                break;
            case 'choice':
                if ($this->getSourceIdentifier() || $value === null || $value === []) {
                    return; // value is set by source
                } elseif (!is_array($value) && !is_null($value)) {
                    $value = (array)$value;
                }

                $choiceOptions  = $this->getChoiceOptions();
                $multipleChoice = $this->moduleConfig['form']['multiple'] ?? false;
                if (in_array($currentModule->getName(), [
                        'dashboard',
                        'detail',
                    ]) && $choiceOptions) {
                    $realValue = [];
                    foreach ($value as $idx) {
                        $translatable = $choiceOptions[$idx];
                        if (!$translatable instanceof TranslatableChoice) {
                            $translatable = new TranslatableChoice($this, $translatable);
                        }
                        $realValue[] = $application->translate($translatable->getMessage());
                    }
                    $value = implode(', ', $realValue);
                } else { // fixme: get string value for form
                    if (!$multipleChoice) {
                        $value = $value[0];
                    }
                }
                break;
            case 'date':
            case 'datetime':
                $value = new \DateTime($value);
                if (!$currentModule instanceof FormModule && ($transformer = $this->getTransformer('date'))) {
                    $this->setData('transformed', true);
                    $value = $application->timeElapsedString($value, $transformer['math_round'], $transformer['suffix'], $transformer['math_round_to']);
                }
                break;
            case 'time':
                $value = new \DateTime($value);
                break;
            case 'text':
            case 'varchar':
                if ($this->getSourceIdentifier() && is_array($this->getData('url'))) {
                    return; // maintain source values
                }
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                if (!$currentModule instanceof FormModule && ($transformer = $this->getTransformer('text'))) {
                    $this->setData('transformed', true);
                    if ($transformer['abbr'] ?? false) {
                        preg_match_all('/\b[A-Za-z]/', $value, $matches);
                        if ($matches[0] ?? null) {
                            $matches[0][] = '';
                            $value = implode('. ', $matches[0]);
                        }
                    }
                    if ($transformer['suffix']) {
                        $value .= ' ' . $transformer['suffix'];
                    }
                }
                break;
            case 'number':
                if (!$currentModule instanceof FormModule && ($transformer = $this->getTransformer())) {
                    $this->setData('transformed', true);

                    if ($transformer['math_round'] && strlen(intval($value)) != strlen($value)) {
                        $value = call_user_func_array($transformer['math_round'], [
                            $value,
                            $transformer['math_round_precision'],
                        ]);
                    }
                    settype($value, $transformer['scalar']);
                    if ($transformer['suffix']) {
                        $value .= ' ' . $transformer['suffix'];
                    }
                }
                break;
        }
        $this->setData('value', $value);
    }

    private function getTransformer(?string $fieldType = null): mixed
    {
        $transformer = $this->extra['transformers'][$fieldType ?? $this->getDisplayType()] ?? [];
        if (empty($transformer['transform'])) {
            return null;
        }

        return $transformer;
    }

    /**
     * @param array $config
     *
     * @return void
     *
     * @fixme changing scalar value is illegal?
     */
    private function setTransformer(array $config): void
    {
        $transformers = [
            'date'   => [
                'suffix'        => ['key' => 'suffix', 'value' => null,],
                'math_round'    => ['key' => 'round', 'value' => 'floor', 'validate' => true],
                'math_round_to' => ['key' => 'round_to', 'value' => 'auto',],
            ],
            'number' => [
                'scalar'               => ['key' => 'scalar', 'value' => 'string'],
                'math_round'           => ['key' => 'round', 'value' => false, 'validate' => true],
                'math_round_precision' => ['key' => 'round_precision', 'value' => 2],
                'suffix'               => ['key' => 'suffix', 'value' => null],
            ],
            'text'   => [
                'suffix' => ['key' => 'suffix', 'value' => null],
                'abbr' => ['key' => 'abbr', 'value' => false],
            ],
        ];

        $validate = function (mixed $fn): bool|string {
            if (true === $fn) {
                return 'round';
            } elseif (false === $fn) {
                return false;
            }
            if (!in_array($fn, ['round', 'floor', 'ceil'])) {
                throw new \InvalidArgumentException(sprintf('Invalid option value <round: "%s"> for Field<%s.%s>; choices [ceil, floor, round]', $fn, $this->appId, $this->id));
            }
            return $fn;
        };

        // find transformer
        $transformerType = array_key_first($config);
        if ($transformer = $transformers[$transformerType] ?? false) {
            $config = $config[$transformerType];
            $result = $transformer + ['transform' => true];

            foreach ($transformer as $key => $cond) {
                $result[$key] = $config[$cond['key']] ?? $cond['value'];
                if ($cond['validate'] ?? false) {
                    $result[$key] = $validate($result[$key]);
                }
            }

            $this->extra['transformers'] = [$transformerType => $result];
        }
    }


    public function isVisible(?ApplicationModuleInterface $module): bool
    {
        if (!$module) {
            return $this->visibility['public'];
        }

        return $this->visibility[$module->getName()];
    }

    /**
     * Temporary method setting visibility for module
     *
     * @param bool $visible
     *
     * @fixme To be factored out
     */
    public function authenticateVisibility(ApplicationModuleInterface $module, bool $isAuthenticated = false): void
    {
        if ($this->visibility[$module->getName()] && !$this->visibility['public']) {
            $this->visibility[$module->getName()] = $isAuthenticated;
        }
    }

    private function setConfiguration(array $config)
    {
        $this->config = [
            'type'      => $config['type'] ?? (!empty($config['source']) ? 'choice' : 'text'), // see setSources(); modified by exposed source column
            'form_type' => $config['type'] ?? (!empty($config['source']) ? 'choice' : 'text'),
            'label'     => Property::displayLabel($config['label'] ?? $this->id),
            'default'   => $config['default'] ?? null,
            'source'    => $config['source'] ?? null,
            'pointer'   => !empty($config['pointer']),
        ];

        $formTypes = [
            'boolean'  => 'checkbox',
            'datetime' => 'dateTime',
            'image'    => 'file',
            'textbox'  => 'textarea',
            'url'      => 'text',
        ];
        if (isset($formTypes[$this->config['type']])) {
            $this->config['form_type'] = $formTypes[$this->config['type']];
        }

        switch ($this->config['type']) {
            case 'choice':
                $this->config['options'] = $config['options'] ?? [];
                foreach ($this->config['options'] as &$value) {
                    $value = new TranslatableChoice($this, $value);
                }

                if (is_array($this->config['default'])) {
                    $__d                     = $this->config['default'];
                    $this->config['default'] = [];
                    foreach ($__d as $dk) {
                        $this->config['default'][$dk] = $config['options'][$dk];
                    }
                } elseif ($this->config['default'] !== null) {
                    $this->config['default'] = [$config['default'] => $config['options'][$config['default']]] ?? null;
                }
                break;

        }
    }

    private function setSchema(array $config)
    {
        $schemaTypes = [
            'text'     => 'varchar',
            'image'    => 'varchar',
            'file'     => 'varchar',
            'url'      => 'varchar',
            'tags'     => 'text',
            'textbox'  => 'text',
            'date'     => 'date',
            'datetime' => 'datetime',
            'time'     => 'time',
            'choice'   => 'int',
            'number'   => 'int',
            'boolean'  => 'tinyint',
            'checkbox' => 'tinyint',
            'rating'   => 'int',
            'range'    => 'int',
            'uuid'     => 'uuid',
        ];

        $this->schema = [
            'length'    => $config['length'] ?? null,
            'type'      => $schemaTypes[$config['type'] ?? 'text'],
            'type_meta' => null,
            'nullable'  => !filter_var($config['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'default'   => ($config['type'] ?? 'text') == 'uuid' ? 'uuid()' : ($config['default'] ?? ($config['required'] ?? false) ? '' : null),
            'options'   => [],
            'column'    => $this->id,
        ];

        switch ($this->config['type']) {
            case 'image':
            case 'file':
            case 'text':
            case 'url':
                $this->schema['length'] = $this->schema['length'] ?: 255;
                break;
            case 'choice':
                $this->schema['type']   = 'int';
                $this->schema['length'] = 11;
                if ($config['multiple'] ?? false) {
                    $this->schema['type']      = 'varchar';
                    $this->schema['type_meta'] = 'array';
                    $this->schema['length']    = 255;
                }
                break;
            case 'boolean':
            case 'checkbox':
                $this->schema['type']   = 'tinyint';
                $this->schema['length'] = 1;

                break;
            case 'number':
            case 'rating':
            case 'range':
                $this->schema['type']   = 'int';
                $this->schema['length'] = strlen($config['max'] ?? 11);
                break;
            case 'float':
                $this->schema['type'] = 'float';
                break;
        }
    }

    private function setSource(array $config, ConfigStore $configStore, ?ApplicationConfig $context = null)
    {
        if (!empty($config['source'])) {
            $sourceIdentifier = $config['source'];
            $visibleFields    = [];

            if (is_array($sourceIdentifier)) {
                $sourceIdentifier = $sourceIdentifier['source'];
                $visibleFields    = $sourceIdentifier['fields'] ?? [];
            } else {
                if (strpos($sourceIdentifier, '.')) {
                    [$sourceIdentifier, $visibleFields] = explode('.', $sourceIdentifier);
                }
            }
            $contextSourceConfig = $context->getSource($sourceIdentifier);
            $foreignColumn       = $contextSourceConfig['foreign_column'];

            $this->schema['column'] = $foreignColumn;
            $this->config['source'] = [
                'id'      => $sourceIdentifier,
                'visible' => (array)$visibleFields,
            ];
            // reference field with source to column
            $this->config['references'][$this->id] = [
                'context' => 'schema',
                'value'   => $foreignColumn,
            ];
            // reverse lookup
            $this->config['references'][$foreignColumn] = [
                'context' => 'column_source',
                'value'   => $this->id,
            ];

            if ($contextSourceConfig['function'] || $contextSourceConfig['join_source']) {
                $this->extra['ignored'] = true;

                return; // stop reconfiguration
            }

            // convert display type
            if ($visibleFields) {
                $sourceAppId     = $contextSourceConfig['application'];
                $sourceAppConfig = $configStore->getApplication($sourceAppId);

                if (is_string($visibleFields)) {
                    $sourceField = $sourceAppConfig->getField($visibleFields);
                } elseif (is_array($visibleFields) && count($visibleFields)) {
                    if ($sourceAppConfig->getMeta('exposes')) {
                        $sourceField = (array)$sourceAppConfig->getMeta('exposes');
                    } else {
                        $sourceField = (array)array_filter($sourceAppConfig->getFields(), function ($field) {
                            return filter_var($field['public'] ?? true, FILTER_VALIDATE_BOOLEAN);
                        })[0];
                    }
                    //} else {
                    //    $sourceField = (array)array_filter($visibleFields, function ($field) use ($sourceAppConfig) {
                    //        return isset($sourceAppConfig['fields'][$field]) && filter_var($sourceAppConfig['fields'][$field]['public'] ?? true, FILTER_VALIDATE_BOOLEAN);
                    //    })[0];
                    //}
                    if (count($sourceField) > 1) {
                        return; // combined fields are always textual
                    }
                    $sourceField = $sourceField[0];
                }
                $this->config['type'] = $sourceField->getDisplayType(); // source DisplayType overrules our DisplayType, whilst maintaining the FormType
            }
        }
    }

    private function setModuleConfiguration(array $config)
    {
        $this->moduleConfig = [
            'sortable' => filter_var($config['sortable'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'label'    => [
                'default' => $this->config['label'],
            ],
            'form'     => [
                'required' => filter_var($config['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'attr'     => [],
                'options' => [],
            ],
        ];

        if ($this->extra['ignored']) {
            return;
        }

        switch ($this->config['form_type']) {
            case 'date':
            case 'datetime':
                $yearsRange = [
                    (int)date('Y') - 10,
                    (int)date('Y') + 10,
                ];

                if (isset($config['year_min'])) {
                    $yearsRange[0] = strlen($config['year_min']) <= 3 ? (int)date('Y') - $config['year_min'] : $config['year_min'];
                }
                if (isset($config['year_max'])) {
                    $yearsRange[1] = strlen($config['year_max']) <= 3 ? (int)date('Y') + $config['year_max'] : $config['year_max'];
                }
                $this->moduleConfig['form']['years'] = range(...$yearsRange);
                break;
            case 'image':
            case 'file':
            case 'text':
                $this->moduleConfig['form']['attr']['maxlength'] = $config['maxlength'] ?? 255;
                break;
            case 'range':
            case 'rating':
                $choices                                   = $config['options'] ?? [1, 10];
                $this->moduleConfig['form']['attr']['min'] = $config['min'] ?? $choices[0];
                $this->moduleConfig['form']['attr']['max'] = $config['max'] ?? $choices[array_key_last($choices)];
                $this->moduleConfig['form']['required']    = filter_var($config['required'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $this->moduleConfig['form']['row_attr']    = $this->moduleConfig['form']['attr'];
                $this->config['form_type']                 = 'range';
                break;
            case 'choice':
                $this->moduleConfig['form']['required'] = filter_var($config['required'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $this->moduleConfig['form']['multiple'] = !empty($config['multiple']);
                $this->moduleConfig['form']['expanded'] = !empty($config['expanded']);
                $this->moduleConfig['form']['choices']  = $this->config['options'] ?? [];
                $this->moduleConfig['unique'] = filter_var($config['unique'] ?? false, FILTER_VALIDATE_BOOLEAN);

                $this->moduleConfig['form']['attr']['data-live-search'] = !empty($config['live_search']) ? 'true' : 'false'; // note: must be string <bool>

                if ($config['group'] ?? null) {
                    $this->moduleConfig['form']['options']['group'] = 'true';
                }

                if ($config['group_source'] ?? null) {
                    $this->moduleConfig['form']['options']['group'] = [
                        'source' => $config['group_source'],
                    ];
                    $this->moduleConfig['form']['attr']['data-group'] = 'true';
                    $this->moduleConfig['form']['attr']['data-hide-disabled'] = 'true';
                }
                if ($config['condition'] ?? null) {
                    if ($this->moduleConfig['form']['attr']['data-group'] ?? false) {
                        $this->moduleConfig['form']['attr']['data-group'] = $config['condition'];
                    } else {
                        $this->moduleConfig['form']['attr']['data-condition'] = $config['condition'];
                    }
                }
                break;
            case 'boolean':
            case 'checkbox':
                $this->moduleConfig['label']['enabled']  = $config['label_enabled'] ?? Property::displayLabel($this->moduleConfig['label']['default'] . '.enabled');
                $this->moduleConfig['label']['disabled'] = $config['label_disabled'] ?? Property::displayLabel($this->moduleConfig['label']['default'] . '.disabled');
                break;
            case 'number':
                $this->moduleConfig['form']['attr']['min'] = $config['min'] ?? -PHP_INT_MAX;
                $this->moduleConfig['form']['attr']['max'] = $config['max'] ?? PHP_INT_MAX;
                break;
            case 'float':
                $this->moduleConfig['form']['attr']['min'] = $config['min'] ?? -PHP_INT_MAX;
                $this->moduleConfig['form']['attr']['max'] = $config['max'] ?? PHP_INT_MAX;
                break;
        }
    }

    private function setExtra(array $config)
    {
        $this->extra['ignored'] = $this->extra['ignored'] || ($config['ignored'] ?? false);

        if ($config['pointer'] ?? false) {
            $this->extra['ignored'] = true;
            $this->extra['pointer'] = [
                'fields' => [],
                'type'   => $config['pointer']['type'] ?? false,
            ];

            if (!empty($config['pointer']['fields'])) {
                $this->extra['pointer']['fields'] = (array)$config['pointer']['fields'];
            } elseif (is_string($config['pointer']) || is_array($config['pointer'])) {
                $this->extra['pointer']['fields'] = (array)$config['pointer'];
            }
        }

        if ($this->moduleConfig['unique'] ?? null) {
            $this->extra['constraint'] = 'unique';
        }

        if (!empty($config['_transform'])) {
            $this->setTransformer($config['_transform']);
        }
    }

    private function setVisibility(array $config)
    {
        $classes = [
            'all'       => 'all',
            'visible'   => 'all',
            'invisible' => 'never',
            'detail'    => 'none',
            'large'     => 'tablet-l desktop',
            'small'     => 'mobile-p mobile-l tablet-p',
            'desktop'   => 'desktop',
            'portrait'  => 'mobile-p tablet-p',
            'landscape' => 'mobile-l tablet-l',
            'mobile'    => 'mobile-p',
            'tablet'    => 'tablet-l tablet-p',
        ];

        $this->visibility = [
            'public'    => filter_var($config['public'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'dashboard' => !empty($config['dashboard'] ?? true),
            'detail'    => filter_var($config['detail'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'form'      => !$this->extra['ignored'],
        ];

        // allow setting `field.visibility: 'value'`
        if (!is_array($v = $config['dashboard'] ?? 'all')) {
            $config['dashboard'] = ['visibility' => $v];
        }
        $this->moduleConfig['dashboard']['class'] = $classes[$config['dashboard']['visibility']] ?? 'all';
        $this->moduleConfig['dashboard']['sortable'] = !in_array($this->getDisplayType(), ['image', 'file']);
    }
}