<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Service\ContentFieldsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function in_array;
use function is_array;
use function is_string;

final class PageContentFieldApiController extends AbstractController
{
    public function __construct(
        private readonly BuilderPageRepository $pageRepository,
        private readonly ContentFieldsService $contentFieldsService,
        private readonly BuilderLocales $builderLocales,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route(
        '/pages/{pageKey}/fields/{fieldKey}',
        name: 'admin_page_builder_field_save',
        requirements: ['pageKey' => '[a-z0-9_-]+', 'fieldKey' => '[a-z][a-z0-9_]*'],
        methods: ['POST'],
    )]
    public function save(string $pageKey, string $fieldKey, Request $request): JsonResponse
    {
        if (!$this->csrfTokenManager->isTokenValid(
            new CsrfToken('page_builder_content', (string) $request->headers->get('X-CSRF-TOKEN', '')),
        )) {
            return new JsonResponse(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $locale  = is_string($payload['locale'] ?? null) ? $payload['locale'] : $this->builderLocales->getDefault();
        if (!in_array($locale, $this->builderLocales->getAll(), true)) {
            return new JsonResponse(['error' => 'invalid_locale'], Response::HTTP_BAD_REQUEST);
        }

        $options = [];
        if (is_array($payload['options'] ?? null)) {
            foreach ($payload['options'] as $option) {
                if (is_string($option) && $option !== '') {
                    $options[] = $option;
                }
            }
        }

        $labels = [];
        if (is_array($payload['labels'] ?? null)) {
            foreach ($payload['labels'] as $labelLocale => $text) {
                if (is_string($labelLocale) && is_string($text) && $text !== '') {
                    $labels[$labelLocale] = $text;
                }
            }
        }

        $definition = [
            'type'     => is_string($payload['type'] ?? null) ? $payload['type'] : 'string',
            'label'    => is_string($payload['label'] ?? null) ? $payload['label'] : $fieldKey,
            'labels'   => $labels,
            'required' => (bool) ($payload['required'] ?? false),
            'options'  => $options,
            'default'  => null,
        ];

        try {
            $this->contentFieldsService->saveFieldValue(
                $page,
                $fieldKey,
                $locale,
                $payload['value'] ?? '',
                $definition,
            );
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'ok'       => true,
            'pageKey'  => $pageKey,
            'fieldKey' => $fieldKey,
            'locale'   => $locale,
            'value'    => $payload['value'] ?? '',
        ]);
    }
}
