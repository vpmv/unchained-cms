<?php

namespace App\System\Application;

use App\System\Application\Database\Repository;
use App\System\Application\Module\ApplicationModuleInterface;
use App\System\Application\Module\DashboardModule;
use App\System\Application\Module\DetailModule;
use App\System\Application\Module\FormModule;
use App\System\Application\Module\RedirectModule;
use App\System\Configuration\ApplicationConfig;
use App\System\Configuration\ConfigStore;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class Application
{
    /** @var \App\System\Configuration\ApplicationConfig */
    private ApplicationConfig $config;
    protected array           $data     = [];
    protected bool            $accepted = true;

    protected bool $isPublic        = true;
    protected bool $isAuthenticated = false;

    /** @var \App\System\Application\Module\ApplicationModuleInterface */
    protected ApplicationModuleInterface $module;

    /**
     * Application constructor.
     *
     * @param string                                             $applicationId
     * @param \Symfony\Component\HttpFoundation\RequestStack     $requestStack
     * @param \App\System\Configuration\ConfigStore              $configStore
     * @param \App\System\Application\Database\Repository        $repository
     * @param \Symfony\Component\Form\FormBuilderInterface       $formBuilder
     * @param \Symfony\Contracts\Translation\TranslatorInterface $translator
     */
    public function __construct(
        public string $appId,
        protected RequestStack $requestStack,
        protected ConfigStore $configStore,
        protected Repository $repository,
        public FormBuilderInterface $formBuilder, // fixme: ?
        protected TranslatorInterface $translator,
    ) {
        $this->config = $configStore->getApplicationConfig($this->appId);
        $this->isPublic = $configStore->isAuthorized($this->appId);
        $this->isAuthenticated = $configStore->isAuthenticated();
    }

    public function getRepository(): Repository
    {
        return $this->repository;
    }

    /**
     * @param string $message
     * @param array  $arguments
     *
     * @return string
     * @throws \Symfony\Component\Translation\Exception\InvalidArgumentException
     *
     * @todo translation helper
     */
    public function translate(string $message, array $arguments = [])
    {
        $translation = $this->translator->trans($message, $arguments, Property::schemaName($this->appId));
        $defaultOutput = str_replace(array_keys($arguments), array_values($arguments), $message);

        if ($translation == $message || $translation == $defaultOutput) {
            $translation = $this->translator->trans($message, $arguments);
        }

        return $translation;
    }

    public function getPublicUri(?string $source = null, bool $getPath = false, array $parameters = [])
    {
        if (!$this->configStore->isAuthorized($this->appId, $source)) {
            return null;
        }

        $uri = $this->configStore->getApplicationUri($this->appId, $source);
        if ($getPath) {
            $uri = [
                'route'  => 'dash_app',
                'params' => [
                    'app' => $uri,
                ],
            ];

            if (!empty($parameters['slug'])) {
                if (!$this->configStore->isAuthorized($this->appId, $source, 'detail')) {
                    return null;
                }

                $slug = trim($parameters['slug']);
                unset($parameters['slug']);
                $uri['params']['app'] .= '/' . $slug;
            }
            if (!empty($parameters)) {
                $uri['params']['?'] = $parameters;
            }
        }

        return $uri;
    }

    public function getDirectory(string $type = ConfigStore::DIR_FILES, ?string $sourceAlias = null)
    {
        return $this->configStore->getDirectory($type, $this->appId, $sourceAlias, false);
    }

    /**
     * Get active module name
     *
     * @param string $name Give name of module if module is enabled
     *
     * @return string
     */
    public function isModuleEnabled(?string $name = null): bool
    {
        return null !== $this->config->getModule($name);
    }

    public function getCurrentModule(): ApplicationModuleInterface
    {
        return $this->module;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->config->getFields();
    }

    public function getField(string $identifier, $defaultValue = null): Field
    {
        if (!isset($this->config->fields[$identifier])) {
            if ($identifier == '_slug') {
                return $this->getIdField();
            }

            throw new \LogicException('Unmapped field: ' . $identifier);
        }

        /** @var Field $field */
        $field = $this->config->fields[$identifier];
        if ($defaultValue) {
            $field->setData([
                'value'       => $defaultValue,
                'raw'         => $defaultValue,
                'url'         => null,
                'transformed' => false,
            ]);
        }

        return $field;
    }

    public function getIdField(): Field
    {
        return new Field($this->appId, 'id', [
            'type'      => 'number',
            'public'    => false,
            'dashboard' => false,
            'detail'    => false,
            'ignored'   => true,
        ], $this->configStore, $this->config);
    }

    public function getData(array $columns = []): array
    {
        return $this->repository->getData($columns);
    }

    public function getDistinctData(string $column): array
    {
        return $this->repository->getDistinct($column);
    }

    public function getFieldOptions(Field $field, ?int $currentValue = null): array
    {
        if ($field->getSourceIdentifier()) {
            $rawData = $this->getRepository()->getForeignData($field->getSourceIdentifier());
            $data = array_combine(array_column($rawData, 'pk'), array_column($rawData, '_exposed'));
        } else {
            $data = $field->getChoiceOptions();
        }
        if ($field->getConstraint() == 'unique') {
            if ($field->getSourceIdentifier()) {
                $foreignColumn = $this->configStore->getForeignColumn($this->appId, $field->getSourceIdentifier());
                $used = $this->getRepository()->getDistinct($foreignColumn);
            } else {
                $used = $this->getRepository()->getDistinct($field->getSchema()['column']);
            }

            $data = array_filter($data, function ($key) use ($used, $currentValue) {
                return ($currentValue && $key == $currentValue) || !in_array($key, $used);
            }, ARRAY_FILTER_USE_KEY);
        }

        return $data;
    }

    /**
     * @todo Not implemented
     */
    public function getPointers(): array
    {
        return [];
    }

    public function boot(?string $module = null): void
    {
        if ($module && !$this->config->getModule($module)) {
            $this->accepted = false;

            return;
        }

        $this->module = match ($module) {
            'detail' => new DetailModule($this, $this->getRequest()),
            'form' => new FormModule($this, $this->getRequest()),
            default => new DashboardModule($this, $this->getRequest()),
        };
    }

    public function apply(array $params = []): void
    {
        if (!$this->accepted) {
            return;
        }

        switch ($this->getRequest()->query->get('action', 'dashboard')) {
            case 'delete':
                if (!empty($params['id'])) {
                    try {
                        $this->repository->deleteRecord($params['id']);
                    } catch (\InvalidArgumentException $e) {
                    }
                } elseif ($params) {
                    try {
                        $this->repository->deleteBy($params);
                    } catch (\InvalidArgumentException $e) {
                    }
                }

                $this->module = new RedirectModule($this, $this->getRequest());
                $this->module->setData(['redirect' => $this->getPublicUri(null, true)]);
                $this->module->prepare();

                return;
            case 'duplicate':
                throw new \LogicException('Can\'t duplicate yet');
            case 'edit':
            default: // dashboard / detail
                if ($query = $this->getRequest()->query->get('q')) {
                    $this->repository->filterData(['_exposed' => $query]); // fixme
                } else {
                    $this->repository->filterData($params);
                }
        }

        $this->prepareData();

        $this->module->setData($this->data);
        $this->module->prepare();
    }

    public function run(): ?array
    {
        if (!$this->accepted) {
            return null;
        }

        // fixme: use for form as well
        $uniqueConstraint = false;
        if ($this->module instanceof DashboardModule && ($constraints = $this->config->getConstraint('unique'))) {
            foreach ($constraints as $fieldId) {
                $field = $this->getField($fieldId);
                $values = $this->getUniqueValues($field);
                $column = $this->getConstraintColumn($field);
                $uniqueConstraint = empty(array_diff($values, array_column($this->data, $column)));
            }
        }

        $data = [
            'appId'              => $this->appId,
            'category'           => $this->config->getCategory(),
            'categoryId'         => $this->config->getCategory()->getCategoryId(),
            // todo: remove
            'translation_domain' => Property::schemaName($this->appId),
            'meta'               => $this->config->meta,
            'public_uri'         => $this->getPublicUri(),
            'frontend'           => compact('uniqueConstraint'),
        ];
        $moduleData = $this->module->getData();
        if ($moduleData && ctype_alpha(key($moduleData))) {
            $data += $moduleData;
        } else {
            $data['data'] = $moduleData;
        }

        return $data;
    }

    protected function prepareData(): void
    {
        $this->data = [];

        // globally modify visibility
        foreach ($this->getFields() as $field) {
            $field->setModuleVisibility($this->module, $this->isAuthenticated); // fixme: adapt in data assignment
        }

        switch ($this->module->getName()) {
            case 'dashboard':
                while ($row = $this->repository->next()) {
                    $this->addDataRow($row);
                }
                break;
            case 'form':
                try {
                    $row = $this->repository->getActiveRecord();
                    $this->addDataRow($row, true);
                } catch (\LogicException $e) {
                    $this->addDataRow([], true);
                }
                $this->data = $this->data[0];
                break;
            case 'detail':
                $row = $this->repository->getActiveRecord();
                $this->addDataRow($row);
                $this->data = $this->data[0];
                break;
        }
    }

    private function addDataRow(array $sqlData, bool $includeField = false): void
    {
        $row = [
            'pk' => [
                'visible' => false,
                'value'   => $sqlData['pk'] ?? 0,
                'raw'     => $sqlData['pk'] ?? 0,
                'link'    => null,
                'field'   => null,
            ],
        ];

        foreach ($this->getFields() as $field) {
            if (!$field->isVisible($this->module) && !$field->isSlug()) { // fixme: append data into modules directly
                continue;
            }

            $fieldId = $field->getId();
            try {
                $field->setValue($this, $this->repository->getColumn($fieldId, $field->getSourceIdentifier()), $this->repository->getApplicationReference($fieldId));
            } catch (\LogicException $e) {
                // no value available (i.e. no records)
            }

            $row[$fieldId] = $field->getOutput($this->module);
            if ($includeField) {
                $row[$field->getId()]['field'] = $field;
            }
        }

        $this->data[] = $row;
    }

    protected function getUniqueValues(Field $field): ?array
    {
        if ($field->getSourceIdentifier()) {
            $result = array_keys($this->repository->getForeignData($field->getSourceIdentifier()));
        } else {
            $result = $field->getChoiceOptions();
        }

        return $result;
    }

    /**
     * @param \App\System\Application\Field $field
     *
     * @return string
     */
    protected function getConstraintColumn(Field $field): string
    {
        $result = $field->getId();
        if ($field->getSourceIdentifier()) {
            $result = $this->configStore->getForeignColumn($this->appId, $field->getSourceIdentifier());
        }

        return $result;
    }

    /**
     * Convert date into 'ago'-like string
     *
     * @param string|\DateTime $datetime
     * @param string           $round
     * @param string|null      $suffix
     * @param string           $transformTo
     *
     * @return string
     * @throws \DateMalformedStringException
     * @todo move to Helpers
     */
    public function timeElapsedString(string|\DateTime $datetime, string $round = 'floor', ?string $suffix = null, string $transformTo = 'auto'): string
    {
        $now = new \DateTime();
        $ago = !$datetime instanceof \DateTime ? new \DateTime($datetime) : $datetime;
        $diff = $now->diff($ago);

        if (false === $diff) {
            return $ago->format('Y M d');
        }

        if (!in_array($round, [
            'floor',
            'ceil',
            'round',
        ])) {
            throw new \LogicException('"' . $round . '" is not a valid Math function');
        }

        $diffWeeks = call_user_func($round, $diff->d / 7);
        $diff->d -= $diffWeeks * 7;

        $dateSlices = [
            'year'   => 'y',
            'month'  => 'm',
            'week'   => 'w',
            'day'    => 'd',
            'hour'   => 'h',
            'minute' => 'm',
        ];
        foreach ($dateSlices as &$v) {
            $v = $diff->$v;
        }

        if ($transformTo != 'auto') {
            $period = $transformTo;
            $result = $dateSlices[$transformTo];

            return $this->translate('value.time.' . $period . ($result > 1 ? '_plural' : '') . '.nn', ['nn' => $result]);
        }

        $result = [];
        foreach (array_filter($dateSlices) as $p => $r) {
            if ($r) {
                $result[] = $this->translate('value.time.' . $p . ($r > 1 ? '_plural' : '') . '.nn', ['nn' => $r]);
            }
        }

        if (!$result) {
            return $this->translate('value.time.just_now');
        }

        return implode(',', $result) . ($suffix ? ' ' . $suffix : null);
    }

    /**
     * @param       $fieldId
     * @param array $context
     *
     * @return mixed|null
     * @throws \LogicException
     */
    public function resolveExtension($fieldId, array $context): mixed
    {
        $className = str_replace(' ', '', ucwords(preg_replace('/[\W]+/', ' ', $this->appId)));
        $methodName = 'transform' . str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z]/', ' ', $fieldId)));

        if (!is_callable("$className::$methodName")) {
            throw new \LogicException("Could not run user function $className::$methodName");
        }
        $result = forward_static_call_array("$className::$methodName", [$context]);

        return $result;
    }

    private function getRequest(): ?\Symfony\Component\HttpFoundation\Request
    {
        return $this->requestStack->getMainRequest();
    }
}