<?php

namespace App\Controller;

use App\System\Configuration\ConfigStore;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SystemController
 *
 * @package App\Controller
 */
class SystemController extends AbstractController
{
    public function getConfig(Request $request, ConfigStore $configStore, string $element): Response
    {
        $default = $request->query->get('default');
        $config  = $configStore->readSystemConfig('config', 'config');

        return new Response($config[$element] ?? $default);
    }

    public function getSystemTitle(ConfigStore $configStore): Response
    {
        return new Response($configStore->readSystemConfig('config', 'config')['title']);
    }
}