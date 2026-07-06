<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Controller;

use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Returns the data needed to render the frontend admin bar as JSON.
 *
 * The route of this controller lives below the Sulu admin base path on
 * purpose (see "admin_bar.admin_base_path"): it is the only path prefix
 * covered by the admin firewall, so the Sulu admin session token is
 * available here while frontend pages stay fully cacheable.
 *
 * Kept compatible with PHP >= 7.2 and Sulu 2.x/3.x: page/webspace security
 * contexts and admin URL patterns are identical in both major versions, so
 * no Sulu version specific classes are referenced here.
 *
 * Besides pages, any custom entity with Sulu admin views is supported:
 * the edit/add links are resolved dynamically from the view registry by
 * resource key, so no per-entity configuration is required. Entries under
 * "admin_bar.entities" may add a stricter security context on top.
 */
class AdminBarController
{
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Same value as PageInterface::RESOURCE_KEY (Sulu 3) and
     * BasePageDocument resource key (Sulu 2).
     */
    private const RESOURCE_KEY_PAGES = 'pages';

    /**
     * Same value as PageAdmin::SECURITY_CONTEXT_PREFIX in Sulu 2 and 3.
     */
    private const SECURITY_CONTEXT_PREFIX = 'sulu.webspaces.';

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    /**
     * @var SecurityCheckerInterface
     */
    private $securityChecker;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var WebspaceManagerInterface
     */
    private $webspaceManager;

    /**
     * Custom entity configuration indexed by resource key:
     * ['formations' => ['resource_key' => ..., 'security_context' => ...], ...].
     *
     * @var array<string, array{resource_key: string, security_context: ?string}>
     */
    private $entitiesByResourceKey = [];

    /**
     * Sulu's view registry (sulu_admin.view_registry). Intentionally not
     * type-hinted: the service only exists in the admin context and the
     * class is duck-typed (getViews()) to stay compatible with Sulu 2 and 3.
     *
     * @var object|null
     */
    private $viewRegistry;

    /**
     * @param array<string, array{resource_key: string, security_context: ?string}> $entities
     * @param object|null $viewRegistry
     */
    public function __construct(
        TokenStorageInterface $tokenStorage,
        SecurityCheckerInterface $securityChecker,
        UrlGeneratorInterface $urlGenerator,
        WebspaceManagerInterface $webspaceManager,
        array $entities = [],
        $viewRegistry = null
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->securityChecker = $securityChecker;
        $this->urlGenerator = $urlGenerator;
        $this->webspaceManager = $webspaceManager;
        $this->viewRegistry = $viewRegistry;

        foreach ($entities as $entityConfig) {
            $this->entitiesByResourceKey[$entityConfig['resource_key']] = $entityConfig;
        }
    }

    public function infoAction(Request $request): JsonResponse
    {
        $token = $this->tokenStorage->getToken();
        $user = null !== $token ? $token->getUser() : null;

        if (!$user instanceof User) {
            return $this->createResponse(['authenticated' => false], 401);
        }

        $webspace = $this->resolveWebspace((string) $request->query->get('webspace', ''));

        if (!$webspace instanceof Webspace) {
            return $this->createResponse(['authenticated' => false], 401);
        }

        $webspaceKey = $webspace->getKey();
        $locale = $this->resolveLocale($webspace, (string) $request->query->get('locale', ''));

        $resourceKey = (string) $request->query->get('resourceKey', '');

        $uuid = (string) $request->query->get('uuid', '');
        $isPage = self::RESOURCE_KEY_PAGES === $resourceKey
            && 1 === \preg_match(self::UUID_PATTERN, $uuid);

        $id = (string) $request->query->get('id', '');
        $isEntity = '' !== $resourceKey
            && self::RESOURCE_KEY_PAGES !== $resourceKey
            && \ctype_digit($id);

        $adminUrl = $this->urlGenerator->generate('sulu_admin');

        $editUrl = null;
        $addUrl = null;

        if ($isEntity) {
            // Custom entity: resolve its admin form views dynamically from
            // the view registry. Views are registered per user, so a
            // missing view also means "not allowed". An optionally
            // configured security context is checked on top.
            $entityConfig = $this->entitiesByResourceKey[$resourceKey] ?? null;
            $entitySecurityContext = null !== $entityConfig ? $entityConfig['security_context'] : null;

            if (null === $entitySecurityContext
                || $this->securityChecker->hasPermission($entitySecurityContext, PermissionTypes::EDIT)
            ) {
                $editUrl = $this->resolveEntityViewUrl($adminUrl, $resourceKey, $locale, $id);
            }

            if (null === $entitySecurityContext
                || $this->securityChecker->hasPermission($entitySecurityContext, PermissionTypes::ADD)
            ) {
                $addUrl = $this->resolveEntityViewUrl($adminUrl, $resourceKey, $locale, null);
            }
        } else {
            $securityContext = self::SECURITY_CONTEXT_PREFIX . $webspaceKey;
            $canEdit = $this->securityChecker->hasPermission($securityContext, PermissionTypes::EDIT);
            $canAdd = $this->securityChecker->hasPermission($securityContext, PermissionTypes::ADD);

            if ($isPage && $canEdit) {
                $editUrl = \sprintf('%s#/webspaces/%s/pages/%s/%s', $adminUrl, $webspaceKey, $locale, $uuid);
            }

            if ($canAdd) {
                // Create the new page below the current one; outside of a page
                // context fall back to the page list of the current webspace.
                $addUrl = $isPage
                    ? \sprintf('%s#/webspaces/%s/pages/%s/add/%s', $adminUrl, $webspaceKey, $locale, $uuid)
                    : \sprintf('%s#/webspaces/%s/pages/%s', $adminUrl, $webspaceKey, $locale);
            }
        }

        $fullName = \trim($user->getFullName());
        if ('' === $fullName) {
            // getUserIdentifier() only exists since Symfony 5.3.
            $fullName = \method_exists($user, 'getUserIdentifier')
                ? $user->getUserIdentifier()
                : $user->getUsername();
        }

        return $this->createResponse([
            'authenticated' => true,
            'user' => [
                'name' => $fullName,
            ],
            'urls' => [
                'admin' => $adminUrl,
                'edit' => $editUrl,
                'add' => $addUrl,
                'logout' => $this->urlGenerator->generate('sulu_admin.logout'),
            ],
        ]);
    }

    /**
     * Builds the admin URL of the entity's edit view ($id given) or add
     * view ($id null) by looking the view up in Sulu's view registry.
     *
     * The registry only contains views the Admin classes registered for
     * the current user, so a missing view also means "no link". Returns
     * null when no matching view exists or its path cannot be resolved.
     */
    private function resolveEntityViewUrl(string $adminUrl, string $resourceKey, string $locale, ?string $id): ?string
    {
        if (null === $this->viewRegistry || !\method_exists($this->viewRegistry, 'getViews')) {
            return null;
        }

        $candidates = [];
        foreach ($this->viewRegistry->getViews() as $view) {
            if ($resourceKey !== $view->getOption('resourceKey')) {
                continue;
            }

            $path = $view->getPath();
            $isEditPath = false !== \strpos($path, ':id');
            $isAddPath = 1 === \preg_match('#/add(/|$)#', $path);

            if (null !== $id ? ($isEditPath && !$isAddPath) : ($isAddPath && !$isEditPath)) {
                $candidates[] = $path;
            }
        }

        // Prefer the shortest path: that is the parent (resource tab) view,
        // which the Sulu admin resolves to its first visible tab itself.
        \usort($candidates, static function (string $a, string $b) {
            return \strlen($a) - \strlen($b);
        });

        foreach ($candidates as $path) {
            $url = \strtr($path, [':id' => (string) $id, ':locale' => $locale]);

            // Skip views with placeholders this endpoint cannot fill.
            if (false === \strpos($url, ':')) {
                return $adminUrl . '#' . $url;
            }
        }

        return null;
    }

    private function resolveWebspace(string $webspaceKey): ?Webspace
    {
        $webspaceCollection = $this->webspaceManager->getWebspaceCollection();

        if ('' !== $webspaceKey) {
            $webspace = $webspaceCollection->getWebspace($webspaceKey);

            if ($webspace instanceof Webspace) {
                return $webspace;
            }
        }

        foreach ($webspaceCollection->getWebspaces() as $webspace) {
            return $webspace;
        }

        return null;
    }

    private function resolveLocale(Webspace $webspace, string $locale): string
    {
        foreach ($webspace->getAllLocalizations() as $localization) {
            if ($localization->getLocale() === $locale) {
                return $locale;
            }
        }

        return $webspace->getDefaultLocalization()->getLocale();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createResponse(array $data, int $status = 200): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');

        return $response;
    }
}
