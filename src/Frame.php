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

use Narrowspark\ExceptionInspector\Contract\Exception\InvalidArgumentException;
use Safe\Exceptions\FilesystemException;

/**
 * @noRector \Rector\SOLID\Rector\ClassMethod\ChangeReadOnlyVariableWithDefaultValueToConstantRector
 */
final class Frame
{
    /**
     * @psalm-var array{file: ?string, line: int, class: string, args: array{array-key: mixed}, function: string}
     *
     * @var array<string, null|array<int, mixed>|int|string>
     */
    private $frame;

    /** @var null|string */
    private $fileContentsCache;

    /**
     * @psalm-var list<array{comment: string, context: string}>
     *
     * @var array<int, array<string, string>>
     */
    private $comments = [];

    /** @var bool */
    private $application = false;

    /**
     * @psalm-param array{file: ?string, line: int, class: string, args: array{array-key: mixed}, function: string} $frame
     *
     * @param int[]|mixed[][]|string[] $frame
     */
    public function __construct(array $frame)
    {
        $this->frame = $frame;
    }

    /**
     * Returns all comments for this frame. Optionally allows
     * a filter to only retrieve comments from a specific
     * context.
     *
     * @param string $filter
     *
     * @psalm-return array<int, array{comment: string, context: string}>
     *
     * @return string[][]
     */
    public function getComments(?string $filter = null): array
    {
        $comments = $this->comments;

        if ($filter !== null) {
            $comments = array_filter($comments, static function (array $c) use ($filter): bool {
                return $c['context'] === $filter;
            });
        }

        return $comments;
    }

    /**
     * Returns whether this frame belongs to the application or not.
     */
    public function isApplication(): bool
    {
        return $this->application;
    }

    /**
     * Mark as an frame belonging to the application.
     */
    public function setApplication(bool $application): void
    {
        $this->application = $application;
    }

    /**
     * Adds a comment to this frame, that can be received and
     * used by other handlers. For example, the PrettyPage handler
     * can attach these comments under the code for each frame.
     *
     * An interesting use for this would be, for example, code analysis
     * & annotations.
     *
     * @param string $context Optional string identifying the origin of the comment
     */
    public function addComment(string $comment, string $context = 'global'): void
    {
        $this->comments[] = [
            'comment' => $comment,
            'context' => $context,
        ];
    }

    public function getFile(): ?string
    {
        if (! isset($this->frame['file']) || $this->frame['file'] === '') {
            return null;
        }

        $file = $this->frame['file'];

        // Check if this frame occurred within an eval().
        // @todo: This can be made more reliable by checking if we've entered
        // eval() in a previous trace, but will need some more work on the upper
        // trace collector(s).
        if (\Safe\preg_match('/^(.*)\((\d+)\) : (?:eval\(\)\'d|assert) code$/', $file, $matches) !== 0) {
            $file = $this->frame['file'] = $matches[1];
            $this->frame['line'] = (int) $matches[2];
        }

        return $file;
    }

    public function getLine(): int
    {
        return $this->frame['line'];
    }

    public function getClass(): ?string
    {
        if ($this->frame['class'] === '') {
            return null;
        }

        return $this->frame['class'];
    }

    public function getFunction(): ?string
    {
        if ($this->frame['function'] === '') {
            return null;
        }

        return $this->frame['function'];
    }

    /**
     * @return mixed[]
     */
    public function getArgs(): array
    {
        return $this->frame['args'];
    }

    /**
     * Returns the full contents of the file for this frame,
     * if it's known.
     */
    public function getFileContents(): ?string
    {
        if ($this->fileContentsCache === null && null !== $filePath = $this->getFile()) {
            // Leave the stage early when 'Unknown' or '[internal]' is passed
            // this would otherwise raise an exception when
            // open_basedir is enabled.
            if ($filePath === 'Unknown' || $filePath === '[internal]') {
                return null;
            }

            try {
                $this->fileContentsCache = \Safe\file_get_contents($filePath);
            } catch (FilesystemException $exception) {
                // @ignoreException
                // Internal file paths of PHP extensions cannot be opened
            }
        }

        return $this->fileContentsCache;
    }

    /**
     * Returns the array containing the raw frame data from which
     * this Frame object was built.
     *
     * @psalm-return array{file: string, line: int, class: string, args: array{array-key: mixed}, function: string}
     *
     * @return int[][]|mixed[][]|string[][]
     */
    public function getRawFrame(): array
    {
        return $this->frame;
    }

    /**
     * Returns the contents of the file for this frame as an
     * array of lines, and optionally as a clamped range of lines.
     *
     * NOTE: lines are 0-indexed
     *
     * @example
     *     Get all lines for this file
     *     $frame->getFileLines(); // => array( 0 => '<?php', 1 => '...', ...)
     * @example
     *     Get one line for this file, starting at line 10 (zero-indexed, remember!)
     *     $frame->getFileLines(9, 1); // array( 9 => '...' )
     *
     * @throws \Narrowspark\ExceptionInspector\Contract\Exception\InvalidArgumentException if $length is less than or equal to 0
     *
     * @return null|string[]
     */
    public function getFileLines(int $start = 0, ?int $length = null): ?array
    {
        if (null !== $contents = $this->getFileContents()) {
            $lines = explode("\n", $contents);

            // Get a subset of lines from $start to $end
            if ($length !== null) {
                if ($start < 0) {
                    $start = 0;
                }

                if ($length <= 0) {
                    throw new InvalidArgumentException(
                        "You provided a invalid value [{$length}] for \$length, \$length cannot be lower or equal to 0."
                    );
                }

                $lines = \array_slice($lines, $start, $length, true);
            }

            return $lines;
        }

        return null;
    }

    /**
     * Compares Frame against one another.
     */
    public function equals(Frame $frame): bool
    {
        $file = $this->getFile();
        $line = $this->getLine();

        if ($file === null || $file === 'Unknown') {
            return false;
        }

        return $frame->getFile() === $file && $frame->getLine() === $line;
    }
}
