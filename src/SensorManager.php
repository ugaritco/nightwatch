<?php

namespace Ugarit\Nightwatch;

use Heritage\Cache\Events\CacheEvent;
use Heritage\Console\Events\ScheduledTaskFailed;
use Heritage\Console\Events\ScheduledTaskFinished;
use Heritage\Console\Events\ScheduledTaskSkipped;
use Heritage\Contracts\Config\Repository as Config;
use Heritage\Contracts\Container\Container;
use Heritage\Contracts\Debug\ExceptionHandler;
use Heritage\Database\Events\QueryExecuted;
use Heritage\Http\Request;
use Heritage\Mail\Events\MessageSending;
use Heritage\Mail\Events\MessageSent;
use Heritage\Notifications\Events\NotificationSending;
use Heritage\Notifications\Events\NotificationSent;
use Heritage\Queue\Events\JobFailed;
use Heritage\Queue\Events\JobProcessed;
use Heritage\Queue\Events\JobQueued;
use Heritage\Queue\Events\JobQueueing;
use Heritage\Queue\Events\JobReleasedAfterException;
use Ugarit\Nightwatch\Records\CacheEvent as CacheEventRecord;
use Ugarit\Nightwatch\Records\Command;
use Ugarit\Nightwatch\Records\Exception;
use Ugarit\Nightwatch\Records\Mail;
use Ugarit\Nightwatch\Records\Notification;
use Ugarit\Nightwatch\Records\OutgoingRequest;
use Ugarit\Nightwatch\Records\Query;
use Ugarit\Nightwatch\Records\QueuedJob;
use Ugarit\Nightwatch\Records\Request as RequestRecord;
use Ugarit\Nightwatch\Sensors\CacheEventSensor;
use Ugarit\Nightwatch\Sensors\CommandSensor;
use Ugarit\Nightwatch\Sensors\ExceptionSensor;
use Ugarit\Nightwatch\Sensors\JobAttemptSensor;
use Ugarit\Nightwatch\Sensors\LogSensor;
use Ugarit\Nightwatch\Sensors\MailSensor;
use Ugarit\Nightwatch\Sensors\NotificationSensor;
use Ugarit\Nightwatch\Sensors\OutgoingRequestSensor;
use Ugarit\Nightwatch\Sensors\QuerySensor;
use Ugarit\Nightwatch\Sensors\QueuedJobSensor;
use Ugarit\Nightwatch\Sensors\RequestSensor;
use Ugarit\Nightwatch\Sensors\ScheduledTaskSensor;
use Ugarit\Nightwatch\Sensors\StageSensor;
use Ugarit\Nightwatch\Sensors\UserSensor;
use Ugarit\Nightwatch\State\CommandState;
use Ugarit\Nightwatch\State\RequestState;
use Ugarit\Nightwatch\Types\Str;
use Monolog\LogRecord;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

use function hash;

/**
 * @internal
 */
final class SensorManager
{
    /**
     * @var (callable(CacheEvent): ?array{0: CacheEventRecord, 1: callable(): array<mixed>})|null
     */
    public $cacheEventSensor;

    /**
     * @var (callable(Throwable, null|bool): ?array{0: Exception, 1: callable(): array<mixed>})|null
     */
    public $exceptionSensor;

    /**
     * @var (callable(LogRecord): array<mixed>)|null
     */
    public $logSensor;

    /**
     * @var (callable(float, float, RequestInterface, ResponseInterface): array{0: OutgoingRequest, 1: callable(): array<mixed>})|null
     */
    public $outgoingRequestSensor;

    /**
     * @var (callable(QueryExecuted, list<array{ file?: string, line?: int }>): array{0: Query, 1: callable(): array<mixed>})|null
     */
    public $querySensor;

    /**
     * @var (callable(JobQueueing|JobQueued): ?array{0: QueuedJob, 1: callable(): array<mixed>})|null
     */
    public $queuedJobSensor;

    /**
     * @var (callable(JobProcessed|JobReleasedAfterException|JobFailed): ?array<mixed>)|null
     */
    public $jobAttemptSensor;

    /**
     * @var (callable(NotificationSending|NotificationSent): ?array{0: Notification, 1: callable(): array<mixed>})|null
     */
    public $notificationSensor;

    /**
     * @var (callable(MessageSending|MessageSent): ?array{0: Mail, 1: callable(): array<mixed>})|null
     */
    public $mailSensor;

    /**
     * @var (callable(): ?array<mixed>)|null
     */
    public $userSensor;

    /**
     * @var (callable(ExecutionStage): void)|null
     */
    public $stageSensor;

    /**
     * @var (callable(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed): ?array<mixed>)|null
     */
    public $scheduledTaskSensor;

    /**
     * @var (callable(Request, Response): array{0: RequestRecord, 1: callable(): array<mixed>})|null
     */
    public $requestSensor;

    /**
     * @var (callable(InputInterface, int): array{0: Command, 1: callable(): array<mixed>})|null
     */
    public $commandSensor;

    /**
     * @param  list<string>  $redactPayloadFields
     * @param  list<string>  $redactHeaders
     */
    public function __construct(
        private RequestState|CommandState $executionState,
        private Clock $clock,
        public Location $location,
        private bool $captureExceptionSourceCode,
        private bool $captureRequestPayload,
        private array $redactPayloadFields,
        private array $redactHeaders,
        private Container $container,
    ) {
        //
    }

    public function stage(ExecutionStage $executionStage): void
    {
        $sensor = $this->stageSensor ??= new StageSensor(
            executionState: $this->executionState,
            clock: $this->clock,
        );

        $sensor($executionStage);
    }

    /**
     * @return array{0: RequestRecord, 1: callable(): array<mixed>}
     */
    public function request(Request $request, Response $response): array
    {
        $sensor = $this->requestSensor ??= new RequestSensor(
            requestState: $this->executionState, // @phpstan-ignore argument.type
            capturePayload: $this->captureRequestPayload,
            redactPayloadFields: $this->redactPayloadFields,
            redactHeaders: $this->redactHeaders,
        );

        return $sensor($request, $response);
    }

    /**
     * @return array{0: Command, 1: callable(): array<mixed>}
     */
    public function command(InputInterface $input, int $status): array
    {
        $sensor = $this->commandSensor ??= new CommandSensor(
            commandState: $this->executionState, // @phpstan-ignore argument.type
        );

        return $sensor($input, $status);
    }

    /**
     * @param  list<array{ file?: string, line?: int }>  $trace
     * @return array{0: Query, 1: callable(): array<mixed>}
     */
    public function query(QueryExecuted $event, array $trace): array
    {
        $sensor = $this->querySensor ??= new QuerySensor(
            executionState: $this->executionState,
            clock: $this->clock,
            location: $this->location,
        );

        return $sensor($event, $trace);
    }

    /**
     * @return array{0: CacheEventRecord, 1: callable(): array<mixed>}
     */
    public function cacheEvent(CacheEvent $event): ?array
    {
        $sensor = $this->cacheEventSensor ??= new CacheEventSensor(
            executionState: $this->executionState,
            clock: $this->clock,
        );

        return $sensor($event);
    }

    /**
     * @return array{0: Mail, 1: callable(): array<mixed>}
     */
    public function mail(MessageSending|MessageSent $event): ?array
    {
        $sensor = $this->mailSensor ??= new MailSensor(
            executionState: $this->executionState,
            clock: $this->clock,
        );

        return $sensor($event);
    }

    /**
     * @return ?array{0: Notification, 1: callable(): array<mixed>}
     */
    public function notification(NotificationSending|NotificationSent $event): ?array
    {
        $sensor = $this->notificationSensor ??= new NotificationSensor(
            executionState: $this->executionState,
            clock: $this->clock,
        );

        return $sensor($event);
    }

    /**
     * @return array{0: OutgoingRequest, 1: callable(): array<mixed>}
     */
    public function outgoingRequest(float $startMicrotime, float $endMicrotime, RequestInterface $request, ResponseInterface $response): array
    {
        $sensor = $this->outgoingRequestSensor ??= new OutgoingRequestSensor(
            executionState: $this->executionState,
        );

        return $sensor($startMicrotime, $endMicrotime, $request, $response);
    }

    /**
     * @return ?array{0: Exception, 1: callable(): array<mixed>}
     */
    public function exception(Throwable $e, ?bool $handled): ?array
    {
        $sensor = $this->exceptionSensor ??= new ExceptionSensor(
            executionState: $this->executionState,
            clock: $this->clock,
            location: $this->location,
            captureSourceCode: $this->captureExceptionSourceCode,
            exceptionHandler: $this->container->make(ExceptionHandler::class),
        );

        return $sensor($e, $handled);
    }

    /**
     * @return array<mixed>
     */
    public function fatalError(Throwable $e): array
    {
        $file = $this->location->normalizeFile($e->getFile());

        return [
            'v' => 3,
            't' => 'exception',
            'timestamp' => $this->clock->microtime(),
            'deploy' => $this->executionState->deploy,
            'server' => $this->executionState->server,
            '_group' => hash('xxh128', $e::class.','.$e->getCode().','.$file.','.$e->getLine()),
            'trace_id' => $this->executionState->trace,
            'execution_source' => $this->executionState->source,
            'execution_id' => '',
            'execution_preview' => $this->executionState->executionPreview,
            'execution_stage' => $this->executionState->stage,
            'user' => $this->executionState->user->resolvedUserId(),
            'class' => $e::class,
            'file' => Str::tinyText($file),
            'line' => $e->getLine(),
            'message' => Str::text($e->getMessage()),
            'code' => (string) $e->getCode(),
            'trace' => '',
            'handled' => false,
            'php_version' => $this->executionState->phpVersion,
            'ugarit_version' => $this->executionState->ugaritVersion,
        ];
    }

    /**
     * @return array<mixed>
     */
    public function log(LogRecord $record): array
    {
        $sensor = $this->logSensor ??= new LogSensor(
            executionState: $this->executionState,
        );

        return $sensor($record);
    }

    /**
     * @return ?array{0: QueuedJob, 1: callable(): array<mixed>}
     */
    public function queuedJob(JobQueueing|JobQueued $event): ?array
    {
        $sensor = $this->queuedJobSensor ??= new QueuedJobSensor(
            executionState: $this->executionState,
            clock: $this->clock,
            connectionConfig: $this->container->make(Config::class)->get('queue.connections') ?? [], // @phpstan-ignore argument.type
        );

        return $sensor($event);
    }

    /**
     * @return ?array<mixed>
     */
    public function jobAttempt(JobProcessed|JobReleasedAfterException|JobFailed $event): ?array
    {
        $sensor = $this->jobAttemptSensor ??= new JobAttemptSensor(
            commandState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
            connectionConfig: $this->container->make(Config::class)->get('queue.connections') ?? [], // @phpstan-ignore argument.type
        );

        return $sensor($event);
    }

    /**
     * @return ?array<mixed>
     */
    public function scheduledTask(ScheduledTaskFinished|ScheduledTaskSkipped|ScheduledTaskFailed $event): ?array
    {
        $sensor = $this->scheduledTaskSensor ??= new ScheduledTaskSensor(
            commandState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
        );

        return $sensor($event);
    }

    /**
     * @return ?array<mixed>
     */
    public function user(): ?array
    {
        $sensor = $this->userSensor ??= new UserSensor(
            requestState: $this->executionState, // @phpstan-ignore argument.type
            clock: $this->clock,
        );

        return $sensor();
    }

    public function flush(): void
    {
        $this->cacheEventSensor = null;
        $this->exceptionSensor = null;
        $this->logSensor = null;
        $this->outgoingRequestSensor = null;
        $this->querySensor = null;
        $this->queuedJobSensor = null;
        $this->jobAttemptSensor = null;
        $this->notificationSensor = null;
        $this->mailSensor = null;
        $this->userSensor = null;
        $this->stageSensor = null;
        $this->scheduledTaskSensor = null;
        $this->requestSensor = null;
        $this->commandSensor = null;
    }
}
