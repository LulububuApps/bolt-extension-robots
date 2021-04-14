<?php

namespace Bolt\Extension\Bolt\Robots;

use Bolt\Extension\SimpleExtension;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Robots extension loader class.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class RobotsExtension extends SimpleExtension
{
    /**
     * Route for /robots.txt
     *
     * @param Application $app
     *
     * @return Response
     */
    public function robotsTxt(Application $app)
    {
        $config = $this->getConfig();

        if ($config['enabled'] === false) {
            return null;
        }

        $maintenanceMode = $app['config']->get('general/maintenance_mode');
        $cacheKey        = 'bolt.robots.txt';
        $configRules     = $config['rules'];
        $rules           = false;

        if ($maintenanceMode) {
            $cacheKey    = 'bolt.maintenance.robots.txt';
            $configRules = $config['maintenance_rules'];
        }

        if ($config['cache']) {
            $rules = $app['cache']->fetch($cacheKey);
        }
        if ($rules === false) {
            $rules = $this->compileRobotsTxt($configRules, $config['sitemap']);

            $app['cache']->save($cacheKey, $rules, 3600);
        }

        return $rules;
    }

    /**
     * @param array $rules
     * @param boolean|string $sitemap
     *
     * @return Response
     */
    protected function compileRobotsTxt(array $rules, $sitemap)
    {
        $render = '';
        foreach ($rules as $key => $value) {
            $render    .= "User-agent: $key\n";
            $disallows = new ParameterBag((array)$value);
            foreach ($disallows->all() as $disallow) {
                $render .= sprintf("Disallow: %s\n", (string)$disallow);
            }
            $render .= "\n";
        }

        if ($sitemap !== null) {
            $render .= sprintf("Sitemap: %s\n", (string)$sitemap);
        }

        $response = new Response($render);
        $response->headers->set('Content-Type', 'text/plain');

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        $collection
            ->get('robots.txt', [$this, 'robotsTxt'])
            ->method(Request::METHOD_GET)
            ->bind('robots.txt')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultConfig()
    {
        return [
            'enabled'           => true,
            'cache'             => true,
            'rules'             => [],
            'maintenance_rules' => [],
            'sitemap'           => null,
        ];
    }
}
