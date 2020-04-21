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

use Narrowspark\ExceptionInspector\Contract\Exception\OutOfRangeException;
use Narrowspark\ExceptionInspector\Contract\Exception\ReadOnlyException;
use Narrowspark\ExceptionInspector\Frame;
use Narrowspark\ExceptionInspector\FrameCollection;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * @internal
 *
 * @covers \Narrowspark\ExceptionInspector\Contract\Exception\ReadOnlyException
 * @covers \Narrowspark\ExceptionInspector\FrameCollection
 *
 * @medium
 */
final class FrameCollectionTest extends TestCase
{
    /**
     * Stupid little counter for tagging frames
     * with a unique but predictable id.
     *
     * @var int
     */
    private $frameIdCounter = 0;

    public function testArrayAccessExists(): void
    {
        $collection = $this->getFrameCollectionInstance();

        self::assertArrayHasKey(0, $collection);
    }

    public function testArrayAccessGet(): void
    {
        $collection = $this->getFrameCollectionInstance();

        self::assertInstanceOf(Frame::class, $collection[0]);
    }

    public function testArrayAccessSet(): void
    {
        $this->expectException(ReadOnlyException::class);
        $this->expectExceptionMessage('Calling [offsetSet] method on read-only object [Narrowspark\ExceptionInspector\FrameCollection] is not allowed.');

        $collection = $this->getFrameCollectionInstance();
        $collection[0] = 'foo';
    }

    public function testArrayAccessUnset(): void
    {
        $this->expectException(ReadOnlyException::class);
        $this->expectExceptionMessage('Calling [offsetUnset] method on read-only object [Narrowspark\ExceptionInspector\FrameCollection] is not allowed.');

        $collection = $this->getFrameCollectionInstance();

        unset($collection[0]);
    }

    public function testArrayAccessGetWithInvalidOffset(): void
    {
        $this->expectException(OutOfRangeException::class);
        $this->expectExceptionMessage('Frame[100] was not found.');

        $collection = $this->getFrameCollectionInstance();

        /** @noRector \Rector\DeadCode\Rector\Stmt\RemoveDeadStmtRector */
        $collection[100];
    }

    public function testFilterFrames(): void
    {
        $frames = $this->getFrameCollectionInstance();

        // Filter out all frames with a line number under 6
        $frames->filter(static function (Frame $frame): bool {
            return $frame->getLine() <= 5;
        });

        self::assertCount(5, $frames);
    }

    public function testMapFrames(): void
    {
        $frames = $this->getFrameCollectionInstance();

        // Filter out all frames with a line number under 6
        $frames->map(static function (Frame $frame): Frame {
            $frame->addComment('This is cool', 'test');

            return $frame;
        });

        self::assertCount(10, $frames);
    }

    public function testMapFramesEnforceType(): void
    {
        $this->expectException(UnexpectedValueException::class);

        $frames = $this->getFrameCollectionInstance();

        // Filter out all frames with a line number under 6
        $frames->map(static function (): string {
            return 'bajango';
        });
    }

    public function testGetArray(): void
    {
        $frames = $this->getFrameCollectionInstance();
        $frames = $frames->getArray();

        self::assertCount(10, $frames);

        self::assertContainsOnlyInstancesOf(Frame::class, $frames);
    }

    public function testGetArrayImmutable(): void
    {
        $frames = $this->getFrameCollectionInstance();
        $arr = $frames->getArray();
        $arr[0] = 'foobar';
        $newCopy = $frames->getArray();

        self::assertNotSame($arr[0], $newCopy);
    }

    public function testCollectionIsIterable(): void
    {
        $frames = $this->getFrameCollectionInstance();

        self::assertContainsOnlyInstancesOf(Frame::class, $frames);
    }

    public function testTopDiff(): void
    {
        $commonFrameTail = $this->getFrameDataList(3);

        $diffFrame = ['line' => $this->frameIdCounter] + $this->getFrameData();

        $frameCollection1 = new FrameCollection(array_merge([
            $diffFrame,
        ], $commonFrameTail));

        $frameCollection2 = new FrameCollection(array_merge([
            $this->getFrameData(),
        ], $commonFrameTail));

        $diff = $frameCollection1->topDiff($frameCollection2);

        self::assertCount(1, $diff);
    }

    public function testPrependFrames(): void
    {
        $frames = $this->getFrameCollectionInstance();

        $oldFrames = $frames->getArray();

        $frame = new Frame([
            'file' => 'foo',
            'line' => 1,
            'function' => 'test-1',
            'class' => 'MyClass',
            'args' => [true, 'hello'],
        ]);

        $frames->prependFrames([$frame]);

        $newFrames = $frames->getArray();

        self::assertCount(10, $oldFrames);
        self::assertCount(11, $newFrames);
        self::assertNotSame($oldFrames, $newFrames);
        self::assertSame($newFrames[0], $frame);
    }

    public function testCountIsApplication(): void
    {
        $frames = $this->getFrameCollectionInstance();

        self::assertSame(0, $frames->countIsApplication());

        $frame = new Frame([
            'file' => __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture/frame.lines-test.php',
            'line' => 1,
            'function' => 'test-1',
            'class' => 'MyClass',
            'args' => [true, 'hello'],
        ]);
        $frame->setApplication(true);

        $frames->prependFrames([$frame]);

        self::assertSame(1, $frames->countIsApplication());
    }

    public function testCount(): void
    {
        $frames = $this->getFrameCollectionInstance();

        self::assertSame(10, $frames->count());
        self::assertCount(10, $frames);
    }

    /**
     * @param int|string $total
     *
     * @psalm-return array<int, array{args: array{array-key: mixed}, class: string, file: string, function?: string, line: int}>
     *
     * @return mixed[]
     */
    private function getFrameDataList($total): array
    {
        $total = max((int) $total, 1);

        $self = $this;

        return array_map(static function () use ($self): array {
            return $self->getFrameData();
        }, range(1, $total));
    }

    /**
     * @psalm-return array{args: array{array-key: mixed}, class: string, file: string, function?: string, line: int}
     *
     * @return array<string, array|int|string>
     */
    private function getFrameData(): array
    {
        $id = ++$this->frameIdCounter;

        return [
            'file' => __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture/frame.lines-test.php',
            'line' => $id,
            'function' => \Safe\sprintf('test-%s', $id),
            'class' => 'MyClass',
            'args' => [true, 'hello'],
        ];
    }

    /**
     * @param null|array<int, mixed[]> $frames
     */
    private function getFrameCollectionInstance(?array $frames = null): FrameCollection
    {
        if ($frames === null) {
            $frames = $this->getFrameDataList(10);
        }

        return new FrameCollection($frames);
    }
}
