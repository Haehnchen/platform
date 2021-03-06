<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Route;

use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class ApiRouteLoader extends Loader
{
    private $definitionRegistry;

    private $isLoaded = false;

    public function __construct(DefinitionRegistry $definitionRegistry)
    {
        $this->definitionRegistry = $definitionRegistry;
    }

    public function load($resource, $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new \RuntimeException('Do not add the "api" loader twice');
        }

        $routes = new RouteCollection();
        $class = ApiController::class;

        // uuid followed by any number of '/{entity-name}/{uuid}' pairs followed by an optional slash
        $detailSuffix = '[0-9a-f]{32}(\/[a-zA-Z-]+\/[0-9a-f]{32})*\/?$';

        // '/{uuid}/{entity-name}' pairs followed by an optional slash
        $listSuffix = '(\/[0-9a-f]{32}\/[a-zA-Z-]+)*\/?$';

        $elements = $this->definitionRegistry->getElements();
        usort($elements, function ($a, $b) {
            /* @var string|EntityDefinition $a */
            /* @var string|EntityDefinition $b */
            return $a::getEntityName() <=> $b::getEntityName();
        });

        /** @var string|EntityDefinition $definition */
        foreach ($elements as $definition) {
            if (is_subclass_of($definition, EntityTranslationDefinition::class)) {
                continue;
            }
            $entityName = $definition::getEntityName();
            $resourceName = str_replace('_', '-', $definition::getEntityName());

            // detail routes
            $route = new Route('/api/v{version}/' . $resourceName . '/{path}');
            $route->setMethods(['GET']);
            $route->setDefault('_controller', $class . '::detail');
            $route->setDefault('entityName', $resourceName);
            $route->addRequirements(['path' => $detailSuffix, 'version' => '\d+']);
            $routes->add('api.' . $entityName . '.detail', $route);

            $route = new Route('/api/v{version}/' . $resourceName . '/{path}');
            $route->setMethods(['PATCH']);
            $route->setDefault('_controller', $class . '::update');
            $route->setDefault('entityName', $resourceName);
            $route->addRequirements(['path' => $detailSuffix, 'version' => '\d+']);
            $routes->add('api.' . $entityName . '.update', $route);

            $route = new Route('/api/v{version}/' . $resourceName . '/{path}');
            $route->setMethods(['DELETE']);
            $route->setDefault('_controller', $class . '::delete');
            $route->setDefault('entityName', $resourceName);
            $route->addRequirements(['path' => $detailSuffix, 'version' => '\d+']);
            $routes->add('api.' . $entityName . '.delete', $route);

            // list routes
            $route = new Route('/api/v{version}/' . $resourceName . '{path}');
            $route->setMethods(['GET']);
            $route->setDefault('_controller', $class . '::list');
            $route->setDefault('entityName', $resourceName);
            $route->addRequirements(['path' => $listSuffix, 'version' => '\d+']);
            $routes->add('api.' . $entityName . '.list', $route);

            $route = new Route('/api/v{version}/search/' . $resourceName . '{path}');
            $route->setMethods(['POST']);
            $route->setDefault('_controller', $class . '::search');
            $route->setDefault('entityName', $resourceName);
            $route->addRequirements(['path' => $listSuffix, 'version' => '\d+']);
            $routes->add('api.' . $entityName . '.search', $route);

            $route = new Route('/api/v{version}/' . $resourceName . '{path}');
            $route->setMethods(['POST']);
            $route->setDefault('_controller', $class . '::create');
            $route->setDefault('entityName', $resourceName);
            $route->addRequirements(['path' => $listSuffix, 'version' => '\d+']);
            $routes->add('api.' . $entityName . '.create', $route);
        }

        $this->isLoaded = true;

        return $routes;
    }

    public function supports($resource, $type = null): bool
    {
        return $type === 'api';
    }
}
