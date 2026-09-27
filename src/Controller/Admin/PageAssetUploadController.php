<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Controller\Admin;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function is_string;

final class PageAssetUploadController extends AbstractController
{
    public const CSRF_TOKEN_ID = 'page_builder_asset_upload';

    public function __construct(
        private readonly AssetUploadHandler $assetUploadHandler,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/admin/page-builder/assets/upload', name: 'admin_page_builder_asset_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        if (!$this->assetUploadHandler->isEnabled()) {
            return new JsonResponse(['error' => 'upload_disabled'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->validateCsrf($request)) {
            return new JsonResponse(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $files = AssetUploadHandler::normalizeFiles($request->files->get('files'));
        if ($files === []) {
            // GrapesJS sometimes uses "file" singular depending on config.
            $files = AssetUploadHandler::normalizeFiles($request->files->get('file'));
        }

        try {
            $assets = $this->assetUploadHandler->handle($files);
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (RuntimeException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // GrapesJS Asset Manager accepts a plain array or { data: [...] }.
        return new JsonResponse(['data' => $assets]);
    }

    private function validateCsrf(Request $request): bool
    {
        $token = $request->headers->get('X-CSRF-TOKEN')
            ?? $request->request->get('_token')
            ?? $request->query->get('_token');

        return is_string($token)
            && $this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $token));
    }
}
