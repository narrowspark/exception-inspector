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

namespace Narrowspark\ExceptionInspector;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Narrowspark\ExceptionInspector\Contract\Exception\ReadOnlyException;
use Narrowspark\ExceptionInspector\Contract\Exception\UnexpectedValueException;

/**
 * @implements \ArrayAccess<int, \Narrowspark\ExceptionInspector\Frame>
 * @implements \IteratorAggregate<int, \Narrowspark\ExceptionInspector\Frame>
 */
final class FrameCollection implements ArrayAccess, Countable, IteratorAggregate
{
    /** @var \Narrowspark\ExceptionInspector\Frame[] */
    private $frames;

    /**
     * @psalm-param list<array{file: string, line: int, class: string, args: array{array-key: mixed}, function: ?string}> $frames
     *
     * @param int[][]|mixed[][][]|string[][] $frames
     */
    public function __construct(array $frames)
    {
        /** @psalm-var array{file: string, line: int, class: string, args: array{array-key: mixed}, function: string} frame */
        $this->frames = array_map(static function (array $frame): Frame {
            return new Frame($frame);
        }, $frames);
    }

    /**
     * Filters frames using a callable, returns the same FrameCollection.
     *
     * @psalm-return \Narrowspark\ExceptionInspector\FrameCollection&static
     * @phpstan-return \Narrowspark\ExceptionInspector\FrameCollection<\Narrowspark\ExceptionInspector\Frame>
     */
    public function filter(callable $callable): self
    {
        $this->frames = array_values(array_filter($this->frames, $callable));

        return $this;
    }

    /**
     * Map the collection of frames.
     *
     * @psalm-return \Narrowspark\ExceptionInspector\FrameCollection&static
     * @phpstan-return \Narrowspark\ExceptionInspector\FrameCollection<\Narrowspark\ExceptionInspector\Frame>
     */
    public function map(callable $callable): self
    {
        // Contain the map within a higher-order callable
        // that enforces type-correctness for the $callable
        $this->frames = array_map(static function (Frame $frame) use ($callable): Frame {
            $frame = \call_user_func($callable, $frame);

            if (! $frame instanceof Frame) {
                throw new UnexpectedValueException(
                    'Callable to ' . self::class . '::map must return a Frame object'
                );
            }

            return $frame;
        }, $this->frames);

        return $this;
    }

    /**
     * Returns an array with all frames, does not affect
     * the internal array.
     *
     * @psalm-return array<array-key, \Narrowspark\ExceptionInspector\Frame>
     *
     * @return \Narrowspark\ExceptionInspector\Frame[]
     */
    public function getArray(): array
    {
        return $this->frames;
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-return \ArrayIterator<int, \Narrowspark\ExceptionInspector\Frame>
     *
     * @return ArrayIterator<int, \Narrowspark\ExceptionInspector\Frame>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->getArray());
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        return isset($this->frames[$offset]);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet($offset): Frame
    {
        return $this->frames[$offset];
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value): void
    {
        throw new ReadOnlyException(__FUNCTION__, self::class);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        throw new ReadOnlyException(__FUNCTION__, self::class);
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        /** @noRector \Rector\Php71\Rector\FuncCall\CountOnNullRector */
        return \count($this->frames);
    }

    /**
     * Count the frames that belongs to the application.
     */
    public function countIsApplication(): int
    {
        return \count(array_filter($this->frames, static function (Frame $frame): bool {
            return $frame->isApplication();
        }));
    }

    /**
     * @param \Narrowspark\ExceptionInspector\Frame[] $frames Array of Frame instances, usually from $e->getPrevious()
     */
    public function prependFrames(array $frames): void
    {
        $this->frames = array_merge($frames, $this->frames);
    }

    /**
     * Gets the innermost part of stack trace that is not the same as that of outer exception.
     *
     * @phpstan-param \Narrowspark\ExceptionInspector\FrameCollection<\Narrowspark\ExceptionInspector\Frame> $parentFrames
     *
     * @param self $parentFrames Outer exception frames to compare tail against
     *
     * @psalm-return array<array-key, \Narrowspark\ExceptionInspector\Frame>
     *
     * @return \Narrowspark\ExceptionInspector\Frame[]
     */
    public function topDiff(FrameCollection $parentFrames): array
    {
        $diff = $this->frames;

        $array = $parentFrames->getArray();

        $p = \count($array) - 1;

        for ($i = \count($diff) - 1; $i >= 0 && $p >= 0; $i--) {
            $tailFrame = $diff[$i];

            if ($tailFrame->equals($array[$p])) {
                unset($diff[$i]);
            }

            $p--;
        }

        return $diff;
    }
}
