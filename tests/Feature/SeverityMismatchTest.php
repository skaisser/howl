<?php

use Skaisser\Howl\Events\GenericInfoEvent;
use Skaisser\Howl\Facades\Howl;

// ---------------------------------------------------------------------------
// Severity-mismatch guard — 4 cases (a / b / c / d)
//
// Policy (implemented in PendingNotification, Phase 3 wire):
//   When a terminal verb (e.g. ->error()) is called with a HowlEvent whose
//   severity() differs from the verb AND no explicit ->severity() builder
//   override has been set, a \LogicException is thrown with the message:
//
//     "Howl: terminal verb ->error() conflicts with event severity 'info'.
//      Use ->send($event) to defer to the event, or set ->severity('error')
//      explicitly to override."
//
//  GenericInfoEvent defaults to severity 'info', making it the ideal fixture
//  for exercising the mismatch path when combined with ->error().
// ---------------------------------------------------------------------------

// (a) Throw when terminal verb conflicts with event severity
it('throws LogicException when terminal verb conflicts with event severity', function () {
    Howl::fake();

    expect(fn () => Howl::on()->error(new GenericInfoEvent('Test', 'Info msg')))
        ->toThrow(
            LogicException::class,
            "Howl: terminal verb ->error() conflicts with event severity 'info'."
        );
});

// (b) No throw when an explicit ->severity() override matches the terminal verb
it('does not throw when explicit ->severity() override matches the terminal verb', function () {
    Howl::fake();

    // ->severity('error') signals intent to override — no guard triggered.
    expect(fn () => Howl::on()->severity('error')->error(new GenericInfoEvent('Test', 'Info msg')))
        ->not->toThrow(LogicException::class);
});

// (c) No throw when using ->send($event) — non-terminal, defers to event severity
it('does not throw when dispatching via ->send($event) (defers to event severity)', function () {
    Howl::fake();

    expect(fn () => Howl::on()->send(new GenericInfoEvent('Test', 'Info msg')))
        ->not->toThrow(LogicException::class);
});

// (d) No throw when event severity already matches the terminal verb
it('does not throw when event severity matches the terminal verb', function () {
    Howl::fake();

    // GenericInfoEvent with default severity 'info' → ->info() verb matches → no conflict.
    expect(fn () => Howl::on()->info(new GenericInfoEvent('Test', 'Info msg')))
        ->not->toThrow(LogicException::class);
});
