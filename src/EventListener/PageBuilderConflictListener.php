<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\EventListener;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Nowo\TransactionContract\TransientStorageFailure;
use Nowo\TransactionDoctrine\ConflictCode;
use Nowo\TransactionDoctrine\Symfony\Conflict\ConflictResponsePolicy;
use Nowo\TransactionDoctrine\TransientFailureClassifier;
use Nowo\TransactionDoctrine\WriteUnit\CommitLedger;
use Nowo\TransactionDoctrine\WriteUnit\CommitMark;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

use function is_string;
use function str_contains;
use function str_starts_with;

/**
 * 1.17 (REQ-RUNTIME-003 rule 6): a lock conflict on a route of the bundle answers a conflict instead of a 500.
 *
 * - Lock conflict (a {@see TransientStorageFailure} of the bundle's runner, or a deadlock, lock wait timeout or
 *   serialization failure anywhere down the chain, or a lost savepoint of unknown outcome): the status of the
 *   {@see ConflictResponsePolicy} (**409** by default, 503 when the host configures it) with a {@see ConflictCode} in
 *   the `X-Conflict-Code` header:
 *   - `CONFLICT_001` with `Retry-After` when nothing of the request was committed;
 *   - `CONFLICT_002` without `Retry-After` when part of it may be: anything committed since the request started
 *     (transaction-doctrine's request mark, {@see ConflictCode::forRequest()}, when its bundle is registered; and the
 *     bundle's own mark on its connection's {@see CommitLedger}), a committed `ConcurrencyConflict`, or a lost
 *     savepoint with no deadlock behind it.
 * - Two editors adding the same missing translation of a page (or locale row of its document) at once: 409 with the
 *   domain code {@see self::CONCURRENT_EDIT} in `X-Error-Code`, never a `CONFLICT_*` code (it is not a lock
 *   conflict). The page-key and template-key races are answered by the services ("already exists"). A lost connection,
 *   or any other storage error, is not mapped (a real error).
 *
 * JSON requests (the editor's API calls: a JSON body, or `Accept: application/json`) get the bundle's JSON error shape
 * with the code added: `{"error": "conflict", "code": "CONFLICT_001", "message": "..."}`. The admin pages are
 * rendered by the application's error page: the answer is an HttpException with a fixed, client-safe message and the
 * storage error as previous (for the logs; no SQL or value reaches the client).
 *
 * Only the bundle's controllers: the host's routes keep the host's error handling. Stateless apart from the shared
 * ledger, a monotonic counter read by difference only (FrankenPHP worker mode, kernel reset off).
 */
final readonly class PageBuilderConflictListener
{
    /**
     * kernel.exception priority: above 0, before the framework's error listener logs the exception (`logKernelException`,
     * priority 0, CRITICAL for a 500), so an expected conflict answer is not logged as a critical error. Host routes
     * are never touched.
     */
    public const int PRIORITY = 64;

    /** kernel.request priority: before the router (32) and the firewall (8), so no controller wrote yet. */
    public const int REQUEST_PRIORITY = 2048;

    /** Request attribute holding the bundle's own commit mark (its connection's ledger). */
    public const string MARK_ATTRIBUTE = '_nowo_page_builder_kit_commit_mark';

    /** Response header carrying a domain error code (never a conflict code). */
    public const string ERROR_CODE_HEADER = 'X-Error-Code';

    /** Domain code of two editors adding the same page translation (or document locale) at once. */
    public const string CONCURRENT_EDIT = 'PAGE_BUILDER_CONCURRENT_EDIT';

    /** `error` value of the JSON answer of a lock conflict. */
    public const string JSON_ERROR = 'conflict';

    /** `error` value of the JSON answer of the concurrent-edit domain conflict. */
    public const string JSON_CONCURRENT_EDIT_ERROR = 'concurrent_edit';

    /** Message of a CONFLICT_001 answer (nothing committed). */
    public const string RETRY_SAFE_MESSAGE = 'Another change to this page is being saved. Retry in a moment.';

    /** Message of a CONFLICT_002 answer (part of the request may be committed). */
    public const string COMMITTED_MESSAGE = 'Part of the change may already be saved. Reload the page before trying again.';

    /** Message of the concurrent-edit answer. */
    public const string CONCURRENT_EDIT_MESSAGE = 'Another editor saved this page at the same time. Reload it and apply your change again.';

    /** Controller namespace of the bundle. */
    private const string CONTROLLER_PREFIX = 'Nowo\\PageBuilderKitBundle\\Controller\\';

    private ConflictResponsePolicy $policy;

    private TransientFailureClassifier $classifier;

    /**
     * @param ConflictResponsePolicy|null $policy the conflict status and default Retry-After (null = 409, 1 s)
     * @param CommitLedger|null $ledger real commits of the bundle's connection (null = no own commit tracking)
     */
    public function __construct(
        ?ConflictResponsePolicy $policy = null,
        private ?CommitLedger $ledger = null,
        private ?RequestStack $requestStack = null,
    ) {
        $this->policy     = $policy ?? new ConflictResponsePolicy();
        $this->classifier = new TransientFailureClassifier();
    }

    /**
     * Takes the bundle's commit mark on the main request.
     */
    public function onRequest(RequestEvent $event): void
    {
        if ($this->ledger instanceof CommitLedger && $event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::MARK_ATTRIBUTE, new CommitMark($this->ledger));
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->isBundleRequest($request)) {
            return;
        }

        $failure = $event->getThrowable();
        if ($this->isLockConflict($failure)) {
            $code    = $this->code($request, $failure);
            $message = $code->retrySafe() ? self::RETRY_SAFE_MESSAGE : self::COMMITTED_MESSAGE;
            $hint    = $this->hint($failure);
            if ($this->wantsJson($request)) {
                $event->setResponse(new JsonResponse(
                    ['error' => self::JSON_ERROR, 'code' => $code->value, 'message' => $message],
                    $this->policy->status,
                    $this->policy->headersFor($code, $hint),
                ));

                return;
            }

            $event->setThrowable($this->policy->httpExceptionFor($code, $message, $failure, $hint));

            return;
        }

        if ($this->isUniqueViolation($failure)) {
            if ($this->wantsJson($request)) {
                $event->setResponse(new JsonResponse(
                    ['error' => self::JSON_CONCURRENT_EDIT_ERROR, 'code' => self::CONCURRENT_EDIT, 'message' => self::CONCURRENT_EDIT_MESSAGE],
                    Response::HTTP_CONFLICT,
                    [self::ERROR_CODE_HEADER => self::CONCURRENT_EDIT],
                ));

                return;
            }

            $event->setThrowable(new ConflictHttpException(self::CONCURRENT_EDIT_MESSAGE, $failure, 0, [self::ERROR_CODE_HEADER => self::CONCURRENT_EDIT]));
        }
    }

    private function isLockConflict(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                return true;
            }
        }

        return $this->classifier->isTransient($failure) || $this->classifier->isCommitOutcomeUnknown($failure);
    }

    /**
     * CONFLICT_002 when transaction-doctrine's request mark, or the bundle's own one, saw a commit; else the failure decides.
     */
    private function code(Request $request, Throwable $failure): ConflictCode
    {
        $code = ConflictCode::forRequest($this->requestStack?->getMainRequest() ?? $request, $failure);
        if ($code->retrySafe() && $this->ownMark($request)?->committedSince() === true) {
            return ConflictCode::Committed;
        }

        return $code;
    }

    private function ownMark(Request $request): ?CommitMark
    {
        $mark = $request->attributes->get(self::MARK_ATTRIBUTE) ?? $this->requestStack?->getMainRequest()?->attributes->get(self::MARK_ATTRIBUTE);

        return $mark instanceof CommitMark ? $mark : null;
    }

    /** The first Retry-After hint of the chain (a transient failure of the runner); null for none or zero. */
    private function hint(Throwable $failure): ?int
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof TransientStorageFailure) {
                $hint = $current->retryAfterSeconds();

                return $hint !== null && $hint > 0 ? $hint : null;
            }
        }

        return null;
    }

    /**
     * A unique violation that reached the kernel: the page translation and document locale keys (the page-key and
     * template-key races are answered by the services before).
     */
    private function isUniqueViolation(Throwable $failure): bool
    {
        for ($current = $failure; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof UniqueConstraintViolationException) {
                return true;
            }
        }

        return false;
    }

    private function wantsJson(Request $request): bool
    {
        return $request->getPreferredFormat() === 'json'
            || $request->getContentTypeFormat() === 'json'
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');
    }

    private function isBundleRequest(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');

        return is_string($controller) && str_starts_with($controller, self::CONTROLLER_PREFIX);
    }
}
