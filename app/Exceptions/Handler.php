<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkeys;
use PDOException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;
use Webauthn\Exception\AuthenticatorResponseVerificationException;

class Handler extends ExceptionHandler
{
    /**
     * The validation parser in Laravel formats custom rules using the class name
     * resulting in some weird rule names. This string will be parsed out and
     * replaced with 'p_' in the response code.
     */
    private const PANEL_RULE_STRING = 'App\_rules\_';

    /**
     * Message prefixes web-auth/webauthn-lib 5.3 uses when it rejects a ceremony over its
     * origin, in CheckAllowedOrigins and CheckOrigin. Matching on wording is unpleasant,
     * but the library throws one exception type for every verification failure and carries
     * no code to switch on. Recheck these whenever that dependency is bumped.
     */
    private const PASSKEY_ORIGIN_FAILURES = ['Invalid origin', 'Invalid scheme'];

    /**
     * A list of the exception types that should not be reported.
     */
    protected $dontReport = [
        AuthenticationException::class,
        AuthenticatorResponseVerificationException::class,
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        TokenMismatchException::class,
        ValidationException::class,
    ];

    /**
     * Maps exceptions to a specific response code. This handles special exception
     * types that don't have a defined response code.
     *
     * @var array<class-string, int>
     */
    protected static array $exceptionResponseCodes = [
        AuthenticationException::class => 401,
        AuthorizationException::class => 403,
        AuthenticatorResponseVerificationException::class => 422,
        ValidationException::class => 422,
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     */
    protected $dontFlash = [
        'token',
        'secret',
        'password',
        'password_confirmation',
    ];

    /**
     * Registers the exception handling callbacks for the application. This
     * will capture specific exception types that we do not want to include
     * the detailed stack traces for since they could reveal credentials to
     * whoever can read the logs.
     *
     * @noinspection PhpUnusedLocalVariableInspection
     */
    public function register(): void
    {
        if (config('app.exceptions.report_all', false)) {
            $this->dontReport = [];
        }

        $this->reportable(function (PDOException $ex) {
            $ex = $this->generateCleanedExceptionStack($ex);
        });

        $this->reportable(function (TransportException $ex) {
            $ex = $this->generateCleanedExceptionStack($ex);
        });

        $this->renderable(fn (AuthenticatorResponseVerificationException $ex, Request $request) => $this->invalidPasskey($ex, $request));
    }

    /**
     * Turn a failed WebAuthn ceremony into an actionable response.
     *
     * The library only reports that the browser's origin wasn't accepted, which on its
     * own is impossible to act on, so log the origin the ceremony ran on alongside the
     * ones being enforced and tell the user which address passkeys work at.
     *
     * Returns null for requests that aren't expecting JSON so they fall through to the
     * normal renderer, the way unauthenticated() does.
     */
    private function invalidPasskey(AuthenticatorResponseVerificationException $exception, Request $request): ?JsonResponse
    {
        $isOriginFailure = Str::startsWith($exception->getMessage(), self::PASSKEY_ORIGIN_FAILURES);

        // Every failure, not just the origin ones. This exception also covers signature
        // mismatches, failed user verification and clone detection, and it's in $dontReport
        // so nothing else logs it.
        logger()->warning($exception, [
            'ceremony_origin' => $this->passkeyCeremonyOrigin($request),
            'request_origin' => $request->headers->get('Origin'),
            'relying_party_id' => Passkeys::relyingPartyId(),
            // As enforced, so this includes whatever AllowPasskeyOrigin added this request.
            'enforced_origins' => config('passkeys.allowed_origins'),
        ]);

        if (!$request->expectsJson()) {
            return null;
        }

        $detail = $isOriginFailure
            ? trans('passkeys.invalid_origin', ['domain' => Passkeys::relyingPartyId()])
            : trans('passkeys.invalid');

        return new JsonResponse(
            $this->convertExceptionToArray($exception, ['detail' => $detail]),
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    /**
     * The origin the ceremony actually ran on. This is the value the library compared and
     * the only one worth debugging against, and it lives base64url encoded in the signed
     * client data rather than in a header the browser may not have sent.
     */
    private function passkeyCeremonyOrigin(Request $request): ?string
    {
        $clientData = $request->input('credential.response.clientDataJSON');

        if (!is_string($clientData)) {
            return null;
        }

        $decoded = base64_decode(strtr($clientData, '-_', '+/'), true);

        if ($decoded === false) {
            return null;
        }

        $clientDataJson = json_decode($decoded, true);
        $origin = is_array($clientDataJson) ? $clientDataJson['origin'] ?? null : null;

        return is_string($origin) ? $origin : null;
    }

    private function generateCleanedExceptionStack(Throwable $exception): string
    {
        $cleanedStack = '';
        foreach ($exception->getTrace() as $index => $item) {
            $cleanedStack .= sprintf(
                "#%d %s(%d): %s%s%s\n",
                $index,
                Arr::get($item, 'file'),
                Arr::get($item, 'line'),
                Arr::get($item, 'class'),
                Arr::get($item, 'type'),
                Arr::get($item, 'function')
            );
        }

        $message = sprintf(
            '%s: %s in %s:%d',
            class_basename($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );

        return $message . "\nStack trace:\n" . trim($cleanedStack);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  Request  $request
     *
     * @throws Throwable
     */
    public function render($request, Throwable $e): Response
    {
        $connections = $this->container->make(Connection::class);

        // If we are currently wrapped up inside a transaction, we will roll all the way
        // back to the beginning. This needs to happen, otherwise session data does not
        // get properly persisted.
        //
        // This is kind of a hack, and ideally things like this should be handled as
        // much as possible at the code level, but there are a lot of spots that do a
        // ton of actions and were written before this bug discovery was made.
        if ($connections->transactionLevel()) {
            $connections->rollBack(0);
        }

        return parent::render($request, $e);
    }

    /**
     * Transform a validation exception into a consistent format to be returned for
     * calls to the API.
     *
     * @param  Request  $request
     */
    public function invalidJson($request, ValidationException $exception): JsonResponse
    {
        $codes = Collection::make($exception->validator->failed())->mapWithKeys(function ($reasons, $field) {
            $cleaned = [];
            foreach ($reasons as $reason => $attrs) {
                $cleaned[] = Str::snake($reason);
            }

            return [str_replace('.', '_', $field) => $cleaned];
        })->toArray();

        $errors = Collection::make($exception->errors())->map(function ($errors, $field) use ($codes, $exception) {
            $response = [];
            foreach ($errors as $key => $error) {
                $meta = [
                    'source_field' => $field,
                    'rule' => str_replace(self::PANEL_RULE_STRING, 'p_', Arr::get(
                        $codes,
                        str_replace('.', '_', $field) . '.' . $key
                    )),
                ];

                $converted = $this->convertExceptionToArray($exception)['errors'][0];
                $converted['detail'] = $error;
                $converted['meta'] = array_merge($converted['meta'] ?? [], $meta);

                $response[] = $converted;
            }

            return $response;
        })->flatMap(function ($errors) {
            return $errors;
        })->toArray();

        return response()->json(['errors' => $errors], $exception->status);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array{errors: array{
     *     code: string,
     *     status: string,
     *     detail: string,
     *     source?: array{line: int, file: string},
     *     meta?: array{trace: string[], previous: string[]}
     * }}|array{errors: array{non-empty-array<string, mixed>}}
     */
    public static function exceptionToArray(Throwable $e, array $override = []): array
    {
        $match = self::$exceptionResponseCodes[get_class($e)] ?? null;

        $error = [
            'code' => class_basename($e),
            'status' => method_exists($e, 'getStatusCode')
                ? strval($e->getStatusCode())
                : strval($match ?? '500'),
            'detail' => $e instanceof HttpExceptionInterface || !is_null($match)
                ? $e->getMessage()
                : 'An unexpected error was encountered while processing this request, please try again.',
        ];

        if ($e instanceof ModelNotFoundException || $e->getPrevious() instanceof ModelNotFoundException) {
            // Show a nicer error message compared to the standard "No query results for model"
            // response that is normally returned. If we are in debug mode this will get overwritten
            // with a more specific error message to help narrow down things.
            $error['detail'] = 'The requested resource could not be found on the server.';
        }

        if (config('app.debug')) {
            $error = array_merge($error, [
                'detail' => $e->getMessage(),
                'source' => [
                    'line' => $e->getLine(),
                    'file' => str_replace(Application::getInstance()->basePath(), '', $e->getFile()),
                ],
                'meta' => [
                    'trace' => Collection::make($e->getTrace())
                        ->map(fn ($trace) => Arr::except($trace, ['args']))
                        ->all(),
                    'previous' => Collection::make(self::extractPrevious($e))
                        ->map(fn ($exception) => $exception->getTrace())
                        ->map(fn ($trace) => Arr::except($trace, ['args']))
                        ->all(),
                ],
            ]);
        }

        return ['errors' => [array_merge($error, $override)]];
    }

    /**
     * Return the exception as a JSONAPI representation for use on API requests.
     *
     * @param  array{detail?: mixed, source?: mixed, meta?: mixed}  $override
     * @return array{errors?: array<mixed>}
     */
    protected function convertExceptionToArray(Throwable $e, array $override = []): array
    {
        return self::exceptionToArray($e, $override);
    }

    /**
     * Return an array of exceptions that should not be reported.
     */
    public static function isReportable(Exception $exception): bool
    {
        return (new self(Container::getInstance()))->shouldReport($exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  Request  $request
     */
    protected function unauthenticated($request, AuthenticationException $exception): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return new JsonResponse($this->convertExceptionToArray($exception), JsonResponse::HTTP_UNAUTHORIZED);
        }

        return redirect()->guest(route('filament.app.auth.login'));
    }

    /**
     * Extracts all the previous exceptions that lead to the one passed into this
     * function being thrown.
     *
     * @return Throwable[]
     */
    public static function extractPrevious(Throwable $e): array
    {
        $previous = [];
        while ($value = $e->getPrevious()) {
            $previous[] = $value;
            $e = $value;
        }

        return $previous;
    }

    /**
     * Helper method to allow reaching into the handler to convert an exception
     * into the expected array response type.
     *
     * @return array<mixed>
     */
    public static function toArray(Throwable $e): array
    {
        return self::exceptionToArray($e);
    }
}
