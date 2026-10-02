<?php

use Illuminate\Database\QueryException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;

it('logs a failed query without its bound values', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message;
    });

    report(new QueryException(
        'mysql',
        'ALTER USER ? IDENTIFIED BY ?',
        ['panel_user', 'hunter2-secret'],
        new PDOException('SQLSTATE[HY000]: General error: 1396 Operation ALTER USER failed'),
    ));

    expect($logged)->toHaveCount(1)
        ->and($logged[0])->toContain('ALTER USER ? IDENTIFIED BY ?')
        ->toContain('Stack trace')
        ->not->toContain('hunter2-secret');
});
