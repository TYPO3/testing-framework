<?php

declare(strict_types=1);

namespace TYPO3\TestingFramework\Core\Functional\Framework\Fakes;

use PHPUnit\Framework\Assert as PHPUnit;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\SingletonInterface;

class FakeMailer extends Mailer implements SingletonInterface
{
    /**
     * All the mails that have been sent.
     */
    private array $mails = [];

    public function __construct(TransportInterface $transport = null, EventDispatcherInterface $eventDispatcher = null)
    {
        $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'null';

        parent::__construct($transport, $eventDispatcher);
    }

    /**
     * Assert if an email was sent based on a truth-test callback.
     */
    public function assertSent(string $mail, callable|int $callback = null): void
    {
        if (is_numeric($callback)) {
            $this->assertSentTimes($mail, $callback);
            return;
        }

        $message = "The expected [{$mail}] mail was not sent.";

        PHPUnit::assertTrue(
            count($this->sent($mail, $callback)) > 0,
            $message
        );
    }

    /**
     * Assert if an email was sent a number of times.
     */
    protected function assertSentTimes(string $mail, int $times = 1): void
    {
        $count = count($this->sent($mail));

        PHPUnit::assertSame(
            $times,
            $count,
            "The expected [{$mail}] mail was sent {$count} times instead of {$times} times."
        );
    }

    /**
     * Determine if an email was not sent based on a truth-test callback.
     */
    public function assertNotSent(string $mail, callable $callback = null): void
    {
        $count = count($this->sent($mail, $callback));

        PHPUnit::assertSame(
            0,
            $count,
            "The unexpected [{$mail}] mail was sent."
        );
    }

    /**
     * Assert that no emails were sent.
     */
    public function assertNothingSent(): void
    {
        PHPUnit::assertEmpty($this->mails, 'Mails were sent unexpectedly.');
    }

    /**
     * Get all the emails matching a truth-test callback.
     */
    public function sent(string $mail, callable $callback = null): array
    {
        if (!$this->hasSent($mail)) {
            return [];
        }

        $callback = $callback ?: static fn() => true;

        return array_filter($this->mailsOf($mail), static fn($mail) => $callback($mail));
    }

    /**
     * Determine if the given email has been sent.
     */
    public function hasSent(string $mail): bool
    {
        return count($this->mailsOf($mail)) > 0;
    }

    /**
     * Get all the mailed emails for a given type.
     */
    protected function mailsOf(string $type): array
    {
        return array_filter($this->mails, static fn(RawMessage $mail) => $mail instanceof $type);
    }

    public function send(RawMessage $message, Envelope $envelope = null): void
    {
        parent::send($message, $envelope);

        $this->mails[] = $message;
    }
}
