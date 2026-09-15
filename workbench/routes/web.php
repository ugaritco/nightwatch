<?php

use App\Jobs\MyJob;
use App\Mail\MyMail;
use App\Notifications\MyNotification;
use Heritage\Support\Facades\Scribe;
use Heritage\Support\Facades\Cache;
use Heritage\Support\Facades\Context;
use Heritage\Support\Facades\DB;
use Heritage\Support\Facades\Http;
use Heritage\Support\Facades\Log;
use Heritage\Support\Facades\Mail;
use Heritage\Support\Facades\Notification;
use Heritage\Support\Facades\Route;
use Ugarit\Nightwatch\Http\Middleware\Sample;

Route::get('/', static function () {
    // This should not be captured.
    Scribe::call('inspire');

    DB::table('users')->get('name');

    MyJob::dispatch();

    Notification::route('mail', 'phillip@ugarit.com')->notify(new MyNotification);

    Mail::to('tim@ugarit.com')->send(new MyMail);

    Http::get('https://ugarit.com');

    Context::add('some', 'extra');
    Log::channel('nightwatch')->debug('Hello world!', [
        'some' => 'context',
    ]);

    report('Hello world!');

    Cache::get('user:55');
    Cache::put('user:55', 'Taylor', 60);
    Cache::putMany(['user:56' => 'Jess', 'user:57' => 'Tim'], 60);
    Cache::getMultiple(['user:56', 'user:57']);
    Cache::forget('user:55');

    return 'ok';
});

// --- TESTING --- //
Route::get('/sampled-or-throw', static fn () => [])->middleware([
    // the order of these middleware matters
    'throwing-middleware',
    Sample::never(),
]);

Route::get('/test-exception', App\Http\ExceptionTestController::class);
