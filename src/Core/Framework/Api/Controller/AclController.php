<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Controller;

use Shopware\Core\Framework\Api\Acl\Event\AclGetAdditionalPrivilegesEvent;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use function array_merge;

/**
 * @Route(defaults={"_routeScope"={"api"}})
 *
 * @package system-settings
 */
class AclController extends AbstractController
{
    private DefinitionInstanceRegistry $definitionInstanceRegistry;

    private EventDispatcherInterface $eventDispatcher;

    private RouterInterface $router;

    /**
     * @internal
     */
    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        EventDispatcherInterface $eventDispatcher,
        RouterInterface $router
    ) {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->eventDispatcher = $eventDispatcher;
        $this->router = $router;
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/api/_action/acl/privileges", name="api.acl.privileges.get", methods={"GET"}, defaults={"auth_required"=true, "_acl"={"api_acl_privileges_get"}})
     */
    public function getPrivileges(): JsonResponse
    {
        $privileges = $this->getFromRoutes();

        $privileges = array_unique(array_merge($privileges, $this->getFromDefinitions()));

        return new JsonResponse($privileges);
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/api/_action/acl/additional_privileges", name="api.acl.privileges.additional.get", methods={"GET"}, defaults={"auth_required"=true, "_acl"={"api_acl_privileges_additional_get"}})
     */
    public function getAdditionalPrivileges(Context $context): JsonResponse
    {
        $privileges = $this->getFromRoutes();

        $definitionPrivileges = $this->getFromDefinitions();
        $privileges = array_diff(array_unique($privileges), $definitionPrivileges);

        $event = new AclGetAdditionalPrivilegesEvent($context, $privileges);
        $this->eventDispatcher->dispatch($event);

        $privileges = $event->getPrivileges();

        return new JsonResponse($privileges);
    }

    /**
     * @return list<string>
     */
    private function getFromDefinitions(): array
    {
        $privileges = [];
        $definitions = $this->definitionInstanceRegistry->getDefinitions();

        foreach ($definitions as $key => $_definition) {
            $privileges[] = $key . ':' . AclRoleDefinition::PRIVILEGE_CREATE;
            $privileges[] = $key . ':' . AclRoleDefinition::PRIVILEGE_DELETE;
            $privileges[] = $key . ':' . AclRoleDefinition::PRIVILEGE_READ;
            $privileges[] = $key . ':' . AclRoleDefinition::PRIVILEGE_UPDATE;
        }

        return $privileges;
    }

    /**
     * @return list<string>
     */
    private function getFromRoutes(): array
    {
        $permissions = [];

        foreach ($this->router->getRouteCollection()->all() as $route) {
            /** @var array<string>|null $acl */
            if ($acl = $route->getDefault(PlatformRequest::ATTRIBUTE_ACL)) {
                $permissions[] = $acl;
            }
        }

        return array_merge(...$permissions);
    }
}
