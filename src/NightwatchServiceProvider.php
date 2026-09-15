<?php

namespace Ugarit\Nightwatch;

use Heritage\Auth\AuthManager;
use Heritage\Auth\Events\Logout;
use Heritage\Cache\Events\CacheHit;
use Heritage\Cache\Events\CacheMissed;
use Heritage\Cache\Events\ForgettingKey;
use Heritage\Cache\Events\KeyForgetFailed;
use Heritage\Cache\Events\KeyForgotten;
use Heritage\Cache\Events\KeyWriteFailed;
use Heritage\Cache\Events\KeyWritten;
use Heritage\Cache\Events\RetrievingKey;
use Heritage\Cache\Events\RetrievingManyKeys;
use Heritage\Cache\Events\WritingKey;
use Heritage\Cache\Events\WritingManyKeys;
use Heritage\Console\Events\ScribeStarting;
use Heritage\Console\Events\CommandStarting;
use Heritage\Contracts\Config\Repository;
use Heritage\Contracts\Console\Kernel as ConsoleKernelContract;
use Heritage\Contracts\Debug\ExceptionHandler;
use Heritage\Contracts\Events\Dispatcher;
use Heritage\Contracts\Http\Kernel as HttpKernelContract;
use Heritage\Database\Events\QueryExecuted;
use Heritage\Foundation\Events\Terminating;
use Heritage\Foundation\Http\Events\RequestHandled;
use Heritage\Http\Client\Factory as Http;
use Heritage\Mail\Events\MessageSending;
use Heritage\Mail\Events\MessageSent;
use Heritage\Notifications\Events\NotificationSending;
use Heritage\Notifications\Events\NotificationSent;
use Heritage\Queue\Events\JobQueued;
use Heritage\Queue\Events\JobQueueing;
use Heritage\Queue\Queue;
use Heritage\Routing\Events\PreparingResponse;
use Heritage\Routing\Events\ResponsePrepared;
use Heritage\Routing\Events\RouteMatched;
use Heritage\Support\Env;
use Heritage\Support\Facades\Context;
use Heritage\Support\ServiceProvider;
use Ugarit\Nightwatch\Console\AgentCommand;
use Ugarit\Nightwatch\Console\DeployCommand;
use Ugarit\Nightwatch\Facades\Nightwatch;
use Ugarit\Nightwatch\Factories\Logger;
use Ugarit\Nightwatch\Hooks\ScribeStartingListener;
use Ugarit\Nightwatch\Hooks\CacheEventListener;
use Ugarit\Nightwatch\Hooks\CommandBootedHandler;
use Ugarit\Nightwatch\Hooks\CommandStartingListener;
use Ugarit\Nightwatch\Hooks\ContextDehydratingHandler;
use Ugarit\Nightwatch\Hooks\CreateQueuePayloadHandler;
use Ugarit\Nightwatch\Hooks\ExceptionHandlerResolvedHandler;
use Ugarit\Nightwatch\Hooks\GlobalMiddleware;
use Ugarit\Nightwatch\Hooks\HttpClientFactoryResolvedHandler;
use Ugarit\Nightwatch\Hooks\HttpKernelResolvedHandler;
use Ugarit\Nightwatch\Hooks\LivewireListener;
use Ugarit\Nightwatch\Hooks\LogoutListener;
use Ugarit\Nightwatch\Hooks\MailListener;
use Ugarit\Nightwatch\Hooks\NotificationListener;
use Ugarit\Nightwatch\Hooks\OctaneListener;
use Ugarit\Nightwatch\Hooks\PolyfillContextDehydration;
use Ugarit\Nightwatch\Hooks\PolyfillContextHydration;
use Ugarit\Nightwatch\Hooks\PreparingResponseListener;
use Ugarit\Nightwatch\Hooks\QueryExecutedListener;
use Ugarit\Nightwatch\Hooks\QueuedJobListener;
use Ugarit\Nightwatch\Hooks\RequestBootedHandler;
use Ugarit\Nightwatch\Hooks\RequestHandledListener;
use Ugarit\Nightwatch\Hooks\ResponsePreparedListener;
use Ugarit\Nightwatch\Hooks\RouteMatchedListener;
use Ugarit\Nightwatch\Hooks\RouteMiddleware;
use Ugarit\Nightwatch\Hooks\TerminatingListener;
use Ugarit\Nightwatch\Http\Middleware\Sample;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Ugarit\Nightwatch\Support\Uuid;
use Ugarit\Octane\Events\RequestReceived;
use Livewire\Livewire;
use Livewire\LivewireManager;
use Ramsey\Uuid\Uuid as BaseUuid;
use Throwable;

use function class_exists;
use function defined;
use function hash;
use function microtime;
use function substr;

/**
 * @internal
 */
final class NightwatchServiceProvider extends ServiceProvider
{
    /**
     * @var Core<RequestState|CommandState>
     */
    private Core $core;

    private float $timestamp;

    private bool $isRequest;

    private Repository $config;

    /**
     * @var array{
     *     enabled?: bool,
     *     sampling?: array{
     *        requests?: float,
     *        commands?: float,
     *        exceptions?: float,
     *        scheduled_tasks?: float,
     *     },
     *     filtering?: array{
     *         ignore_cache_events?: bool,
     *         ignore_mail?: bool,
     *         ignore_notifications?: bool,
     *         ignore_outgoing_requests?: bool,
     *         ignore_queries?: bool,
     *         log_level?: \Psr\Log\LogLevel::*,
     *     },
     *     token?: string,
     *     deployment?: string,
     *     server?: string,
     *     ingest?: array{ uri?: string, timeout?: float|int, connection_timeout?: float|int, event_buffer?: int },
     *     capture_exception_source_code?: bool,
     *     capture_request_payload?: bool,
     *     redact_payload_fields?: string[],
     *     redact_headers?: string[],
     *  }
     */
    private array $nightwatchConfig;

    private ?Throwable $registerException = null;

    private static ?string $initialTrace = null;

    public function register(): void
    {
        try {
            $this->captureTimestamp();
            Compatibility::boot($this->app);
            $this->captureExecutionType();
            $this->registerAndCaptureConfig();
            $this->registerBindings();

            if (! $this->core->enabled()) {
                return;
            }

            $this->registerHooks();
        } catch (Throwable $e) {
            $this->registerException = $e;
        }
    }

    public function boot(): void
    {
        try {
            if ($this->registerException) {
                $this->handleAndClearRegisterException();

                return;
            }

            if ($this->app->runningInConsole()) {
                $this->registerPublications();
                $this->registerCommands();
            }
        } catch (Throwable $e) {
            Nightwatch::unrecoverableExceptionOccurred($e);
        }
    }

    private function captureTimestamp(): void
    {
        $this->timestamp = match (true) {
            defined('UGARIT_START') => UGARIT_START,
            default => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        };
    }

    private function captureExecutionType(): void
    {
        $this->isRequest = ! $this->app->runningInConsole() || Env::get('NIGHTWATCH_FORCE_REQUEST');
    }

    private function registerAndCaptureConfig(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nightwatch.php', 'nightwatch');

        $this->config = $this->app->make(Repository::class);

        $this->nightwatchConfig = $this->config->get('nightwatch') ?? []; // @phpstan-ignore assign.propertyType
    }

    private function registerBindings(): void
    {
        $this->registerLogger();
        $this->registerMiddleware();
        $this->registerAgentCommand();
        $this->registerDeployCommand();
        $this->buildAndRegisterCore();
    }

    private function registerLogger(): void
    {
        if (! $this->config->has('logging.channels.nightwatch')) {
            $this->config->set('logging.channels.nightwatch', [
                'driver' => 'custom',
                'via' => Logger::class,
                'level' => $this->nightwatchConfig['filtering']['log_level'] ?? 'debug',
            ]);
        }

        $this->app->singleton(Logger::class, fn () => new Logger($this->core));
    }

    private function registerMiddleware(): void
    {
        $this->app->singleton(RouteMiddleware::class, fn () => new RouteMiddleware($this->core)); // @phpstan-ignore argument.type

        $this->app->scoped(GlobalMiddleware::class, fn () => new GlobalMiddleware($this->core)); // @phpstan-ignore argument.type

        $this->app->singleton(Sample::class, fn () => new Sample($this->core)); // @phpstan-ignore argument.type
    }

    private function registerAgentCommand(): void
    {
        $this->app->singleton(AgentCommand::class, fn () => new AgentCommand(
            token: $this->nightwatchConfig['token'] ?? null,
            server: $this->nightwatchConfig['server'] ?? null,
            ingestUri: $this->nightwatchConfig['ingest']['uri'] ?? null,
        ));
    }

    private function registerDeployCommand(): void
    {
        $this->app->singleton(DeployCommand::class, fn () => new DeployCommand(
            token: $this->nightwatchConfig['token'] ?? null,
        ));
    }

    private function buildAndRegisterCore(): void
    {
        $clock = new Clock;
        $uuid = new Uuid(static fn () => BaseUuid::uuid4()->toString());
        $trace = self::$initialTrace ??= $this->isRequest && Compatibility::$isUgaritCloud
            ? ($_SERVER['HTTP_CLOUD_REQUEST_ID'] ?? $uuid->make())
            : $uuid->make();
        $executionState = $this->executionState($trace);
        $tokenHash = substr(hash('xxh128', $this->nightwatchConfig['token'] ?? ''), 0, 7);

        $this->app->instance(Core::class, $this->core = new Core(
            ingest: new Ingest(
                transmitTo: $this->nightwatchConfig['ingest']['uri'] ?? '127.0.0.1:2407',
                connectionTimeout: $this->nightwatchConfig['ingest']['connection_timeout'] ?? 0.5,
                timeout: $this->nightwatchConfig['ingest']['timeout'] ?? 0.5,
                streamFactory: new SocketStreamFactory,
                buffer: new RecordsBuffer(
                    length: $this->nightwatchConfig['ingest']['event_buffer'] ?? 500,
                ),
                tokenHash: $tokenHash,
                events: $this->app->make(Dispatcher::class),
            ),
            sensor: new SensorManager(
                executionState: $executionState,
                clock: $clock = new Clock,
                location: new Location(
                    basePath: $this->app->basePath(),
                    publicPath: $this->app->publicPath(),
                ),
                captureExceptionSourceCode: (bool) ($this->nightwatchConfig['capture_exception_source_code'] ?? true),
                captureRequestPayload: (bool) ($this->nightwatchConfig['capture_request_payload'] ?? false),
                redactPayloadFields: $this->nightwatchConfig['redact_payload_fields'] ?? ['_token', 'password', 'password_confirmation'],
                redactHeaders: $this->nightwatchConfig['redact_headers'] ?? ['Authorization', 'Cookie', 'Proxy-Authorization', 'X-XSRF-TOKEN'],
                container: $this->app,
            ),
            executionState: $executionState,
            clock: $clock,
            uuid: $uuid,
            config: [
                'enabled' => $this->nightwatchConfig['enabled'] ?? true,
                'sampling' => [
                    'requests' => $this->nightwatchConfig['sampling']['requests'] ?? 1.0,
                    'commands' => $this->nightwatchConfig['sampling']['commands'] ?? 1.0,
                    'exceptions' => $this->nightwatchConfig['sampling']['exceptions'] ?? 1.0,
                    'scheduled_tasks' => $this->nightwatchConfig['sampling']['scheduled_tasks'] ?? 1.0,
                ],
                'filtering' => [
                    'ignore_cache_events' => (bool) ($this->nightwatchConfig['filtering']['ignore_cache_events'] ?? false),
                    'ignore_mail' => (bool) ($this->nightwatchConfig['filtering']['ignore_mail'] ?? false),
                    'ignore_notifications' => (bool) ($this->nightwatchConfig['filtering']['ignore_notifications'] ?? false),
                    'ignore_outgoing_requests' => (bool) ($this->nightwatchConfig['filtering']['ignore_outgoing_requests'] ?? false),
                    'ignore_queries' => (bool) ($this->nightwatchConfig['filtering']['ignore_queries'] ?? false),
                ],
            ],
        ));
    }

    private function handleAndClearRegisterException(): void
    {
        Nightwatch::unrecoverableExceptionOccurred($this->registerException); // @phpstan-ignore argument.type

        $this->registerException = null;
    }

    private function registerPublications(): void
    {
        $this->publishes([
            __DIR__.'/../config/nightwatch.php' => $this->app->configPath('nightwatch.php'),
        ], ['nightwatch', 'nightwatch-config']);
    }

    private function registerCommands(): void
    {
        $this->commands([
            Console\AgentCommand::class,
            Console\StatusCommand::class,
            Console\DeployCommand::class,
        ]);
    }

    private function registerHooks(): void
    {
        $core = $this->core;

        /** @var Dispatcher */
        $events = $this->app->make(Dispatcher::class);

        //
        // -------------------------------------------------------------------------
        // Sensor hooks
        // --------------------------------------------------------------------------
        //

        /**
         * @see \Ugarit\Nightwatch\Records\Query
         */
        $events->listen(QueryExecuted::class, (new QueryExecutedListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\Records\Exception
         */
        $this->callAfterResolving(ExceptionHandler::class, (new ExceptionHandlerResolvedHandler($core))(...));

        /**
         * @see \Ugarit\Nightwatch\Records\QueuedJob
         */
        $events->listen([JobQueueing::class, JobQueued::class], (new QueuedJobListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\Records\Notification
         */
        $events->listen([NotificationSending::class, NotificationSent::class], (new NotificationListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\Records\Mail
         */
        $events->listen([MessageSending::class, MessageSent::class], (new MailListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\Records\OutgoingRequest
         */
        $this->callAfterResolving(Http::class, (new HttpClientFactoryResolvedHandler($core))(...));

        /**
         * @see \Ugarit\Nightwatch\Records\CacheEvent
         */
        $events->listen([
            RetrievingKey::class,
            RetrievingManyKeys::class,
            CacheHit::class,
            CacheMissed::class,
            WritingKey::class,
            WritingManyKeys::class,
            KeyWritten::class,
            KeyWriteFailed::class,
            ForgettingKey::class,
            KeyForgotten::class,
            KeyForgetFailed::class,
        ], (new CacheEventListener($core))(...));

        $events->listen(RequestReceived::class, (new OctaneListener($core))(...)); // @phpstan-ignore class.notFound

        Queue::createPayloadUsing(new CreateQueuePayloadHandler($core));

        if (Compatibility::$contextExists) {
            Context::dehydrating(new ContextDehydratingHandler($core));
        } else {
            Queue::createPayloadUsing(new PolyfillContextDehydration($core));
            $events->listen((new PolyfillContextHydration($core))(...));
        }

        //
        // -------------------------------------------------------------------------
        // Execution stage hooks
        // --------------------------------------------------------------------------
        //

        if ($this->isRequest) {
            /** @var Core<RequestState> $core */
            $this->registerRequestHooks($events, $core);
        } else {
            /** @var Core<CommandState> $core */
            $this->registerConsoleHooks($events, $core);
        }

        /** @var Core<RequestState|CommandState> $core */

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::Terminating
         */
        $events->listen(Terminating::class, (new TerminatingListener($core))(...));
    }

    /**
     * @param  Core<RequestState>  $core
     */
    private function registerRequestHooks(Dispatcher $events, Core $core): void
    {
        // TODO resolve the kernel inline rather than in the listener.

        /**
         * @see \Ugarit\Nightwatch\State\RequestState::$user
         *
         * TODO handle this on the queue
         */
        $events->listen(Logout::class, (new LogoutListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::BeforeMiddleware
         */
        $this->app->booted((new RequestBootedHandler($core))(...));

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::Action
         * @see \Ugarit\Nightwatch\ExecutionStage::Terminating
         */
        $events->listen(RouteMatched::class, (new RouteMatchedListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::Render
         */
        $events->listen(PreparingResponse::class, (new PreparingResponseListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::AfterMiddleware
         */
        $events->listen(ResponsePrepared::class, (new ResponsePreparedListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::Sending
         */
        $events->listen(RequestHandled::class, (new RequestHandledListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::End
         * @see \Ugarit\Nightwatch\Records\Request
         * @see \Ugarit\Nightwatch\ExecutionStage::Terminating
         * @see \Ugarit\Nightwatch\Core::finishExecution()
         */
        $this->callAfterResolving(HttpKernelContract::class, (new HttpKernelResolvedHandler($core))(...));

        $this->registerLivewireHooks($core);
    }

    /**
     * @param  Core<CommandState>  $core
     */
    private function registerConsoleHooks(Dispatcher $events, Core $core): void
    {
        /** @var ConsoleKernelContract */
        $kernel = $this->app->make(ConsoleKernelContract::class);

        /**
         * @see \Ugarit\Nightwatch\State\CommandState::$scribe
         */
        $events->listen(ScribeStarting::class, (new ScribeStartingListener($core))(...));

        /**
         * @see \Ugarit\Nightwatch\ExecutionStage::Action
         */
        $this->app->booted((new CommandBootedHandler($core))(...));

        /**
         * @see \Ugarit\Nightwatch\State\CommandState::$name
         *
         * Commands...
         * @see \Ugarit\Nightwatch\ExecutionStage::Terminating
         * @see \Ugarit\Nightwatch\ExecutionStage::End
         * @see \Ugarit\Nightwatch\Records\Command
         * @see \Ugarit\Nightwatch\Core::finishExecution()
         *
         * Jobs...
         * @see \Ugarit\Nightwatch\State\CommandState::$source
         * @see \Ugarit\Nightwatch\State\CommandState::flush()
         * @see \Ugarit\Nightwatch\State\CommandState::$timestamp
         * @see \Ugarit\Nightwatch\State\CommandState::$id
         * @see \Ugarit\Nightwatch\Records\JobAttempt
         * @see \Ugarit\Nightwatch\Records\Exception
         *
         * Scheduled tasks...
         * @see \Ugarit\Nightwatch\Core::finishExecution()
         */
        $events->listen(CommandStarting::class, (new CommandStartingListener($events, $core, $kernel))(...));
    }

    /**
     * @param  Core<RequestState>  $core
     */
    private function registerLivewireHooks(Core $core): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        $this->app->booted(static function ($app) use ($core) {
            if (! $app->bound(LivewireManager::class)) {
                return;
            }

            $listener = new LivewireListener($core);

            // Livewire 2
            Livewire::listen('component.hydrate.subsequent', $listener->componentHydrateSubsequent(...));

            // Livewire 3
            Livewire::listen('hydrate', $listener->hydrate(...));
        });
    }

    private function executionState(string $trace): RequestState|CommandState
    {
        Compatibility::addTraceIdToContext($trace);

        if ($this->isRequest) {
            return new RequestState(
                timestamp: $this->timestamp,
                trace: $trace,
                id: $trace,
                currentExecutionStageStartedAtMicrotime: $this->timestamp,
                deploy: $this->nightwatchConfig['deployment'] ?? '',
                server: $this->nightwatchConfig['server'] ?? '',
                user: $this->userProvider(),
            );
        } else {
            return new CommandState(
                timestamp: $this->timestamp,
                trace: new LazyValue(function () {
                    return (string) Compatibility::getTraceIdFromContext(function () { // @phpstan-ignore cast.string
                        $trace = $this->core->uuid->make();

                        Compatibility::addTraceIdToContext($trace);

                        return $trace;
                    });
                }),
                id: $trace,
                currentExecutionStageStartedAtMicrotime: $this->timestamp,
                deploy: $this->nightwatchConfig['deployment'] ?? '',
                server: $this->nightwatchConfig['server'] ?? '',
                user: $this->userProvider(),
            );
        }
    }

    private function userProvider(): UserProvider
    {
        /** @var AuthManager */
        $auth = $this->app->make(AuthManager::class);

        return new UserProvider(
            fn (callable $callback) => $this->core->ignore(static fn () => $callback($auth)),
            fn () => $this->core->userDetailsResolver,
            fn () => $this->core->report(...),
        );
    }

    public static function flushState(): void
    {
        self::$initialTrace = null;
    }
}
