<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Functional\Framework\Fakes;

use PHPUnit\Framework\Assert as PHPUnit;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use TYPO3\CMS\Core\SingletonInterface;

class FakeEventDispatcher implements EventDispatcherInterface, SingletonInterface
{
    private ListenerProviderInterface $listenerProvider;

    /**
     * All the events that have been intercepted keyed by type.
     */
    private array $events = [];

    public function __construct(ListenerProviderInterface $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }

    public function dispatch(object $event)
    {
        $name = get_class($event);
        $this->events[$name][] = $event;
        return $event;
    }

    /**
     * Assert if an event has a listener attached to it.
     */
    public function assertListening(string $expectedEvent, string $expectedListener): void
    {
        if (!method_exists($this->listenerProvider, 'getAllListenerDefinitions')) {
            throw new \RuntimeException(
                'Please inject a listenerProvider which defines the method "getAllListenerDefinitions".'
            );
        }
        $allListeners = $this->listenerProvider->getAllListenerDefinitions();
        $listenersForEvent = $allListeners[$expectedEvent] ?? [];

        foreach ($listenersForEvent as $actualListener) {
            if ($actualListener === $expectedListener) {
                PHPUnit::assertTrue(true);

                return;
            }
        }

        PHPUnit::fail(
            sprintf(
                'Event [%s] does not have the [%s] listener attached to it',
                $expectedEvent,
                print_r($expectedListener, true)
            )
        );
    }

    /**
     * Assert if an event was dispatched based on a truth-test callback.
     */
    public function assertDispatched(string $event, callable|int $callback = null): void
    {
        if (is_int($callback)) {
            $this->assertDispatchedTimes($event, $callback);
            return;
        }

        PHPUnit::assertTrue(
            count($this->dispatched($event, $callback)) > 0,
            "The expected [{$event}] event was not dispatched."
        );
    }

    /**
     * Assert if an event was dispatched a number of times.
     */
    public function assertDispatchedTimes(string $event, int $times = 1): void
    {
        $count = count($this->dispatched($event));

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$event}] event was dispatched {$count} times instead of {$times} times."
        );
    }

    /**
     * Determine if an event was dispatched based on a truth-test callback.
     */
    public function assertNotDispatched(string $event, callable $callback = null): void
    {
        PHPUnit::assertCount(
            0,
            $this->dispatched($event, $callback),
            "The unexpected [{$event}] event was dispatched."
        );
    }

    /**
     * Assert that no events were dispatched.
     */
    public function assertNothingDispatched(): void
    {
        $count = count($this->events);

        PHPUnit::assertSame(
            0,
            $count,
            "{$count} unexpected events were dispatched."
        );
    }

    /**
     * Get all the events matching a truth-test callback.
     */
    public function dispatched(string $event, callable $callback = null): array
    {
        if (!$this->hasDispatched($event)) {
            return [];
        }

        $callback = $callback ?: static fn() => true;

        return array_filter($this->events[$event], static fn($arguments) => $callback(...$arguments), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Determine if the given event has been dispatched.
     */
    public function hasDispatched(string $event): bool
    {
        return isset($this->events[$event]) && !empty($this->events[$event]);
    }
}
