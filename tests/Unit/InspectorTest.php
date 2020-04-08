<?php

declare(strict_types=1);

/**
 * Copyright (c) 2020 Daniel Bannert
 *
 * For the full copyright and license information, please view
 * the LICENSE.md file that was distributed with this source code.
 *
 * @see https://github.com/narrowspark/exception-inspector
 */

namespace Narrowspark\ExceptionInspector\Tests\Unit;

use Error;
use Exception;
use Narrowspark\ExceptionInspector\FrameCollection;
use Narrowspark\ExceptionInspector\Inspector;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * @internal
 *
 * @covers \Narrowspark\ExceptionInspector\Inspector
 *
 * @small
 */
final class InspectorTest extends TestCase
{
    public function testCorrectNestedFrames(): void
    {
        // Create manually to have a different line number from the outer
        $inner = new Exception('inner');
        $outer = new Exception('outer', 0, $inner);

        $inspector = $this->getInspectorInstance($outer);
        $frames = $inspector->getFrames();

        self::assertSame($outer->getLine(), $frames[0]->getLine());
    }

    public function testDoesNotFailOnPHP7ErrorObject(): void
    {
        $inner = new Error('inner');
        $outer = new Exception('outer', 0, $inner);

        $inspector = $this->getInspectorInstance($outer);
        $frames = $inspector->getFrames();

        self::assertSame($outer->getLine(), $frames[0]->getLine());
    }

    public function testReturnsCorrectExceptionName(): void
    {
        $exception = new Exception();
        $inspector = $this->getInspectorInstance($exception);

        self::assertSame($inspector->getExceptionName(), Exception::class);
    }

    public function testExceptionIsStoredAndReturned(): void
    {
        $exception = new Exception();
        $inspector = $this->getInspectorInstance($exception);

        self::assertSame($exception, $inspector->getException());
    }

    public function testGetFramesReturnsCollection(): void
    {
        $exception = new Exception();
        $inspector = $this->getInspectorInstance($exception);

        self::assertInstanceOf(FrameCollection::class, $inspector->getFrames());
    }

    public function testPreviousException(): void
    {
        $previousException = new Exception("I'm here first!");
        $exception = new Exception('Oh boy', 0, $previousException);

        $inspector = $this->getInspectorInstance($exception);

        self::assertTrue($inspector->hasPreviousException());

        /** @var Inspector $previousExceptionInspector */
        $previousExceptionInspector = $inspector->getPreviousExceptionInspector();

        self::assertEquals($previousException, $previousExceptionInspector->getException());
    }

    public function testNegativeHasPreviousException(): void
    {
        $exception = new Exception('Oh boy');
        $inspector = $this->getInspectorInstance($exception);

        self::assertFalse($inspector->hasPreviousException());
    }

    public function testGetPreviousExceptionsReturnsListOfExceptions(): void
    {
        $exception1 = new Exception('My first exception');
        $exception2 = new Exception('My second exception', 0, $exception1);
        $exception3 = new Exception('And the third one', 0, $exception2);

        $inspector = $this->getInspectorInstance($exception3);

        $previousExceptions = $inspector->getPreviousExceptions();

        self::assertCount(2, $previousExceptions);
        self::assertEquals($exception2, $previousExceptions[0]);
        self::assertEquals($exception1, $previousExceptions[1]);
    }

    public function testGetPreviousExceptionsReturnsEmptyListIfThereAreNoPreviousExceptions(): void
    {
        $exception = new Exception('My exception');
        $inspector = $this->getInspectorInstance($exception);

        $previousExceptions = $inspector->getPreviousExceptions();

        self::assertCount(0, $previousExceptions);
    }

    public function testGetPreviousExceptionMessages(): void
    {
        $exception1 = new Exception('My first exception');
        $exception2 = new Exception('My second exception', 0, $exception1);
        $exception3 = new Exception('And the third one', 0, $exception2);

        $inspector = $this->getInspectorInstance($exception3);

        $previousExceptions = $inspector->getPreviousExceptionMessages();

        self::assertEquals($exception2->getMessage(), $previousExceptions[0]);
        self::assertEquals($exception1->getMessage(), $previousExceptions[1]);
    }

    public function testGetPreviousExceptionCodes(): void
    {
        $exception1 = new Exception('My first exception', 99);
        $exception2 = new Exception('My second exception', 20, $exception1);
        $exception3 = new Exception('And the third one', 10, $exception2);

        $inspector = $this->getInspectorInstance($exception3);

        $previousExceptions = $inspector->getPreviousExceptionCodes();

        self::assertEquals($exception2->getCode(), $previousExceptions[0]);
        self::assertEquals($exception1->getCode(), $previousExceptions[1]);
    }

    public function testExtractDocrefUrl(): void
    {
        $inspector = $this->getInspectorInstance(new Exception('test [<a href=\'www.example.com\'>test</a>].'));

        self::assertSame('www.example.com', $inspector->getExceptionDocrefUrl());

        $inspector = $this->getInspectorInstance(new Exception(''));

        self::assertNull($inspector->getExceptionDocrefUrl());
    }

    private function getInspectorInstance(Throwable $exception): Inspector
    {
        return new Inspector($exception);
    }
}
