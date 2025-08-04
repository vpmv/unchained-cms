<?php


namespace App\System\Application\Module;

class DashboardModule extends AbstractModule
{
    public function prepare(): void
    {
        if ($this->container->isModuleEnabled('detail')) {
            foreach ($this->data as &$row) {
                $detailVal     = $row['_slug']['value'] ?? $row['pk']['value'];
                $row['detail'] = [
                    'visible'     => false,
                    'value'       => null,
                    'raw'         => null,
                    'field'       => null,
                    'transformed' => false,
                    'link' => $this->container->getRoute(null, ['slug' => $detailVal]),
                ];
            }
        }

        // TODO
        /*if ($pointers = $this->container->getPointers()) {
            foreach ($pointers as $pointer) {
                $row['pointers'][] = [
                        'hasData' => false,
                        'hasLink' => true,
                    ] + $pointer;
            }
        }*/
        $this->output = $this->data;
    }

    public function getName(): string
    {
        return 'dashboard';
    }
}