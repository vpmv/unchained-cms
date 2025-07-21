<?php

namespace App\System\Application\Module;

use App\System\Application\Database\Column;
use App\System\Application\Database\Junction;
use App\System\Application\Database\JunctionList;
use App\System\Application\Field;
use App\System\Constructs\Translatable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;

class FormModule extends AbstractModule
{
    public function prepare(): void
    {
        foreach ($this->data as $fieldId => $data) {
            if (!$data['visible']) {
                continue;
            }

            /** @var \App\System\Application\Field $field */
            $field = $data['field'];

            $fieldOptions         = $field->getModuleConfig($this);
            $fieldOptions['data'] = $this->getRequestValue($field);

            switch ($field->getFormType()) {
                case 'checkbox':
                    $fieldOptions['attr']  += [
                        'data-bs-toggle' => 'toggle',
                        'data-on'     => $this->container->translate($field->getLabel('enabled')),
                        'data-off'    => $this->container->translate($field->getLabel('disabled')),
                    ];
                    $fieldOptions['label'] = false;
                    $fieldOptions['data']  = filter_var($fieldOptions['data'] ?? false, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'choice':
                    $rawData              = $field->getData('raw') ?? null;
                    $fieldOptions['data'] = $field->isMultipleChoice() ? [] : null;

                    if ($rawData instanceof Junction) {
                        $fieldOptions['data'] = $rawData->getPrimaryKey();
                    } elseif ($rawData instanceof JunctionList) {
                        $fieldOptions['data'] = array_values(array_map(function (Junction $junction) {
                            return $junction->getPrimaryKey();
                        }, $rawData->getJunctions()));
                    } elseif ($rawData instanceof Column) {
                        $fieldOptions['data'] = $rawData->getValue();
                    }
                    $fieldOptions['attr'] += ['class' => 'selectpicker', 'title' => 'Select...'];
                    if ($fieldOptions['required']) {
                        $fieldOptions['attr']['required'] = 'required';
                    }

                    // TODO
                    //$fieldOptions['attr'] += ['data-live-search' => $fieldOptions['suggestions'] ?? true];

                    $choices = $this->container->getFieldOptions($field, (!is_array($fieldOptions['data']) ? $fieldOptions['data'] : null));
                    unset ($fieldOptions['choices']);

                    foreach ($choices as $i => $translatable) {
                        if ($translatable instanceof Translatable) {
                            $translatable = $this->container->translate($translatable->getMessage());
                        }
                        $fieldOptions['choices'][$translatable] = $i;
                    }

                    // convert default value
                    if (!$field->getSourceIdentifier() && $fieldOptions['data'] === null && $field->getDefaultValue() !== null) {
                        $fieldOptions['data'] = array_keys($field->getDefaultValue());
                        if (!$fieldOptions['multiple'] && is_array($fieldOptions['data'])) {
                            $fieldOptions['data'] = $fieldOptions['data'][0];
                        }
                    }

                    //$fieldOptions['attr']['data-source'] = ;

                    break;
                case 'date':
                case 'datetime':
                    if (!$fieldOptions['data'] instanceof \DateTime) {
                        if ($field->getDefaultValue() && ($time = strtotime($field->getDefaultValue()))) {
                            $fieldOptions['data'] = new \DateTime($field->getDefaultValue());
                        } elseif ($field->getDefaultValue() !== null && (empty($fieldOptions['data']) || is_string($fieldOptions['data']))) {
                            $date = $fieldOptions['data'] ?? date(max($fieldOptions['years']) . '-m-d'); // today => max year -m-d
                            if ($time = strtotime($date)) { // test correctness of date
                                $fieldOptions['data'] = new \DateTime($date);
                            }
                        }
                    }
                    break;
                case 'time':
                    $time = $fieldOptions['data'] ?? 'now';
                    if (!$time instanceof \DateTime) {
                        $fieldOptions['data'] = new \DateTime($time);
                    }
                    break;
                case 'file':
                    if (!empty($fieldOptions['data']) && !$fieldOptions['data'] instanceof File) {
                        try {
                            $fieldOptions['data'] = new File($fieldOptions['data']);
                            $this->addFieldData($field, $field->getData('view'));
                        } catch (FileNotFoundException $e) {
                            unset($fieldOptions['data']);
                        }
                    }
                    break;
            }

            $this->addFieldData($field, $fieldOptions['data'] ?? null);
            $this->container->formBuilder->add($field->getId(), $field->getFormType(true), $fieldOptions);
        }

        //$this->formBuilder->add('Save', SubmitType::class, ['label' => 'Save']);

        $form = $this->container->formBuilder->getForm();
        $form->handleRequest($this->request);

        // todo: validation, constraints & error messages... :( fml
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->container->getRepository()->persist($form->getData());
                $this->output['redirect'] = $this->container->getPublicUri(null, true);

                return;
            } catch (UniqueConstraintViolationException $e) {
                $this->errors['duplicate'] = true;
            }
        }

        $this->output['form']    = $form->createView();
        $this->output['hasData'] = !empty($this->data['pk']['value']);
    }

    public function getName(): string
    {
        return 'form';
    }

    /**
     * @param \App\System\Application\Field $field
     *
     * @return mixed
     * @note InputBag doesn't accept non-scalar values as of 6.0
     */
    private function getRequestValue(Field $field): mixed
    {
        $default = $field->getData('value');
        if (is_array($default) || $default instanceof \DateTimeInterface) {
            if ('no-legal-value' === $this->request->request->get($field->getId(), 'no-legal-value')) {
                return $default;
            }
        }

        return $this->request->request->get($field->getId(), $default);
    }

    private function addFieldData(Field $field, $data): void
    {
        if (isset($this->output['data'][$field->getId()])) {
            return;
        }

        $this->output['data'][$field->getId()] = [
            'value'       => $data,
            'type'        => $field->getFormType(),
            'displayType' => $field->getDisplayType(),
        ];
    }
}