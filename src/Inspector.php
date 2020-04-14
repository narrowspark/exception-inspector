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

use ErrorException;
use Safe\Exceptions\InfoException;
use Throwable;

final class Inspector
{
    /** @var string */
    public const VERSION = '1.0.0';

    /** @var string */
    private const PATTERN = "/\\[<a href='([^']+)'>(?:[^<]+)<\\/a>\\]/";

    /** @var Throwable */
    private $exception;

    /**
     * @phpstan-var null|\Narrowspark\ExceptionInspector\FrameCollection<\Narrowspark\ExceptionInspector\Frame>
     *
     * @var null|\Narrowspark\ExceptionInspector\FrameCollection
     */
    private $frames;

    /** @var null|self */
    private $previousExceptionInspector;

    /** @var Throwable[] */
    private $previousExceptions;

    /** @var null|string */
    private $exceptionUrl;

    /** @var null|string */
    private $exceptionMessage;

    public function __construct(Throwable $exception)
    {
        $this->exception = $exception;
    }

    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * Returns an iterator for the inspected exception's
     * frames.
     *
     * @noRector \Rector\DeadCode\Rector\ClassMethod\RemoveDeadRecursiveClassMethodRector
     *
     * @psalm-return \Narrowspark\ExceptionInspector\FrameCollection<\Narrowspark\ExceptionInspector\Frame>
     *
     * @return \Narrowspark\ExceptionInspector\FrameCollection
     */
    public function getFrames(): FrameCollection
    {
        if ($this->frames === null) {
            $frames = $this->getTrace($this->exception);

            // Fill empty line/file info for call_user_func_array usages (PHP Bug #44428)
            foreach ($frames as $k => $frame) {
                if ($frame['file'] !== '') {
                    // Default values when file and line are missing
                    $file = '[internal]';
                    $line = 0;

                    $nextFrame = $frames[$k + 1] ?? [];

                    if ($this->isValidNextFrame($nextFrame)) {
                        $file = $nextFrame['file'];
                        $line = $nextFrame['line'];
                    }

                    $frames[$k]['file'] = $file;
                    $frames[$k]['line'] = $line;
                }
            }

            // Find latest non-error handling frame index ($i) used to remove error handling frames
            $i = 0;

            foreach ($frames as $k => $frame) {
                if ($frame['file'] === $this->exception->getFile() && $frame['line'] === $this->exception->getLine()) {
                    $i = $k;
                }
            }

            // Remove error handling frames
            if ($i > 0) {
                array_splice($frames, 0, $i);
            }

            $firstFrame = $this->getFrameFromException($this->exception);

            array_unshift($frames, $firstFrame);

            $this->frames = new FrameCollection($frames);

            if (($previousInspector = $this->getPreviousExceptionInspector()) !== null) {
                // Keep outer frame on top of the inner one
                $outerFrames = $this->frames;
                $newFrames = clone $previousInspector->getFrames();

                // I assume it will always be set, but let's be safe
                if (isset($newFrames[0])) {
                    $newFrames[0]->addComment(
                        $previousInspector->getExceptionMessage(),
                        'Exception message:'
                    );
                }

                $newFrames->prependFrames($outerFrames->topDiff($newFrames));

                $this->frames = $newFrames;
            }
        }

        return $this->frames;
    }

    /**
     * Returns an Inspector for a previous Exception, if any.
     */
    public function getPreviousExceptionInspector(): ?Inspector
    {
        if ($this->previousExceptionInspector === null && null !== $previousException = $this->exception->getPrevious()) {
            $this->previousExceptionInspector = new Inspector($previousException);
        }

        return $this->previousExceptionInspector;
    }

    /**
     * Returns an array of all previous exceptions for this inspector's exception.
     *
     * @return Throwable[]
     */
    public function getPreviousExceptions(): array
    {
        if ($this->previousExceptions === null) {
            $this->previousExceptions = [];

            $prev = $this->exception->getPrevious();

            while ($prev !== null) {
                $this->previousExceptions[] = $prev;
                $prev = $prev->getPrevious();
            }
        }

        return $this->previousExceptions;
    }

    public function getExceptionMessage(): string
    {
        if ($this->exceptionMessage === null) {
            $this->exceptionMessage = $this->extractDocrefUrl($this->exception->getMessage())['message'];
        }

        return $this->exceptionMessage;
    }

    public function getExceptionName(): string
    {
        return \get_class($this->exception);
    }

    /**
     * @return string[]
     */
    public function getPreviousExceptionMessages(): array
    {
        return array_map(function (Throwable $prev): string {
            return $this->extractDocrefUrl($prev->getMessage())['message'];
        }, $this->getPreviousExceptions());
    }

    /**
     * Returns a url to the php-manual related to the underlying error - when available.
     */
    public function getExceptionDocrefUrl(): ?string
    {
        if ($this->exceptionUrl === null) {
            $this->exceptionUrl = $this->extractDocrefUrl($this->exception->getMessage())['url'];
        }

        return $this->exceptionUrl;
    }

    /**
     * Does the wrapped Exception has a previous Exception?
     */
    public function hasPreviousException(): bool
    {
        return $this->previousExceptionInspector !== null || $this->exception->getPrevious() !== null;
    }

    /**
     * @return int[]
     */
    public function getPreviousExceptionCodes(): array
    {
        return array_map(static function (Throwable $prev) {
            return $prev->getCode();
        }, $this->getPreviousExceptions());
    }

    /**
     * @noRector \Rector\TypeDeclaration\Rector\ClassMethod\AddArrayReturnDocTypeRector
     *
     * @psalm-return array{message: string, url: null|string}
     *
     * @return mixed[]|null[]|string[]
     */
    private function extractDocrefUrl(string $message): array
    {
        $docref = [
            'message' => $message,
            'url' => null,
        ];

        // php embbeds urls to the manual into the Exception message with the following ini-settings defined
        try {
            \Safe\ini_get('html_errors');
        } catch (InfoException $exception) {
            return $docref;
        }

        try {
            \Safe\ini_get('docref_root');
        } catch (InfoException $exception) {
            return $docref;
        }

        if (\Safe\preg_match(self::PATTERN, $message, $matches) !== 0) {
            // -> strip those automatically generated links from the exception message
            $message = \Safe\preg_replace(self::PATTERN, '', $message, 1);

            if (\is_string($message)) {
                $docref['message'] = $message;
            }

            $docref['url'] = $matches[1];
        }

        return $docref;
    }

    /**
     * Gets the backtrace from an exception.
     *
     * If xdebug is installed.
     *
     * @psalm-return list<array<string, mixed>>
     *
     * @return mixed[]
     */
    private function getTrace(Throwable $throwable): array
    {
        $traces = $throwable->getTrace();

        // Get trace from xdebug if enabled, failure exceptions only trace to the shutdown handler by default
        if (! $throwable instanceof ErrorException) {
            return $traces;
        }

        if (! self::isLevelFatal($throwable->getSeverity())) {
            return $traces;
        }

        if (! \extension_loaded('xdebug') || ! xdebug_is_enabled()) {
            return $traces;
        }

        // Use xdebug to get the full stack trace and remove the shutdown handler stack trace
        $stack = array_reverse(xdebug_get_function_stack());
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);

        return array_diff_key($stack, $trace);
    }

    /**
     * Given an exception, generates an array in the format
     * generated by Exception::getTrace().
     *
     * @noRector \Rector\TypeDeclaration\Rector\ClassMethod\AddArrayReturnDocTypeRector
     *
     * @psalm-return array{file: string, line: int, class: class-string<\Throwable>, args: array<array-key, string>}
     *
     * @return array<string, array<int, string>|int|string>
     */
    private function getFrameFromException(Throwable $exception): array
    {
        return [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => \get_class($exception),
            'args' => [
                $exception->getMessage(),
            ],
        ];
    }

    /**
     * Determine if the frame can be used to fill in previous frame's missing info
     * happens for call_user_func and call_user_func_array usages (PHP Bug #44428).
     *
     * @psalm-param array{file: ?string, line: ?int, class: string, args: array<array-key, mixed>, function: ?string} $frame
     *
     * @param array<string, array|int|string> $frame
     */
    private function isValidNextFrame(array $frame): bool
    {
        if (! isset($frame['file'], $frame['line'])) {
            return false;
        }

        return isset($frame['function']) && stripos($frame['function'], 'call_user_func') !== false;
    }

    /**
     * Determine if an error level is fatal (halts execution).
     */
    private static function isLevelFatal(int $level): bool
    {
        $errors = \E_ERROR;
        $errors |= \E_PARSE;
        $errors |= \E_CORE_ERROR;
        $errors |= \E_CORE_WARNING;
        $errors |= \E_COMPILE_ERROR;
        $errors |= \E_COMPILE_WARNING;

        return ($level & $errors) > 0;
    }
}
