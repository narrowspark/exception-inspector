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

use Narrowspark\ExceptionInspector\Contract\Exception\InvalidArgumentException;
use Narrowspark\ExceptionInspector\Frame;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Narrowspark\ExceptionInspector\Frame
 *
 * @internal
 *
 * @medium
 */
final class FrameTest extends TestCase
{
    public function testGetFile(): void
    {
        $data = $this->getFrameData();
        $frame = $this->getFrameInstance($data);

        self::assertEquals($frame->getFile(), $data['file']);
    }

    public function testGetLine(): void
    {
        $data = $this->getFrameData();
        $frame = $this->getFrameInstance($data);

        self::assertEquals($frame->getLine(), $data['line']);
    }

    public function testGetClass(): void
    {
        $data = $this->getFrameData();
        $frame = $this->getFrameInstance($data);

        self::assertEquals($frame->getClass(), $data['class']);
    }

    public function testGetFunction(): void
    {
        $data = $this->getFrameData();
        $frame = $this->getFrameInstance($data);

        self::assertEquals($frame->getFunction(), $data['function']);
    }

    public function testGetArgs(): void
    {
        $data = $this->getFrameData();
        $frame = $this->getFrameInstance($data);

        self::assertEquals($frame->getArgs(), $data['args']);
    }

    public function testGetFileContents(): void
    {
        $data = $this->getFrameData();
        $frame = $this->getFrameInstance($data);

        $content = $frame->getFileContents();

        self::assertNotNull($content);
        self::assertStringEqualsFile($data['file'], $content);
    }

    /**
     * @dataProvider provideGetFileContentsWhenFrameIsNotRelatedToSpecificFileCases
     */
    public function testGetFileContentsWhenFrameIsNotRelatedToSpecificFile(string $fakeFilename): void
    {
        $data = array_merge($this->getFrameData(), ['file' => $fakeFilename]);
        $frame = $this->getFrameInstance($data);

        self::assertNull($frame->getFileContents());
        self::assertNull($frame->getFileLines());
    }

    /**
     * @psalm-return iterable<array-key, array<array-key, string>>
     *
     * @return string[][]
     */
    public static function provideGetFileContentsWhenFrameIsNotRelatedToSpecificFileCases(): iterable
    {
        yield ['[internal]'];

        yield ['Unknown'];
    }

    public function testGetFileLines(): void
    {
        $data = $this->getFrameData();
        $frame = $this->getFrameInstance($data);

        $content = $frame->getFileContents();

        self::assertNotNull($content);

        $lines = explode("\n", $content);

        self::assertEquals($frame->getFileLines(), $lines);
    }

    public function testGetFileLinesRange(): void
    {
        $data = $this->getFrameData();
        $frame = $this->getFrameInstance($data);

        /** @var array<int, string> $lines */
        $lines = $frame->getFileLines(0, 3);

        self::assertEquals($lines[0], '<?php');
        self::assertEquals($lines[1], '// Line 2');
        self::assertEquals($lines[2], '// Line 3');
    }

    public function testGetFileLinesRangeThrowException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You provided a invalid value [-1] for $length, $length cannot be lower or equal to 0.');

        $frame = $this->getFrameInstance();

        $frame->getFileLines(-1, -1);
    }

    public function testGetComments(): void
    {
        $frame = $this->getFrameInstance();
        $testComments = [
            'Dang, yo!',
            'Errthangs broken!',
            'Dayumm!',
        ];

        $frame->addComment($testComments[0]);
        $frame->addComment($testComments[1]);
        $frame->addComment($testComments[2]);

        $comments = $frame->getComments();

        self::assertCount(3, $comments);

        self::assertEquals($comments[0]['comment'], $testComments[0]);
        self::assertEquals($comments[1]['comment'], $testComments[1]);
        self::assertEquals($comments[2]['comment'], $testComments[2]);
    }

    public function testGetFilteredComments(): void
    {
        $frame = $this->getFrameInstance();
        $testComments = [
            ['Dang, yo!', 'test'],
            ['Errthangs broken!', 'test'],
            'Dayumm!',
        ];

        $frame->addComment($testComments[0][0], $testComments[0][1]);
        $frame->addComment($testComments[1][0], $testComments[1][1]);
        $frame->addComment($testComments[2][0], $testComments[2][1]);

        $comments = $frame->getComments('test');

        self::assertCount(2, $comments);
        self::assertEquals($comments[0]['comment'], $testComments[0][0]);
        self::assertEquals($comments[1]['comment'], $testComments[1][0]);
    }

    public function testEquals(): void
    {
        $frame1 = $this->getFrameInstance(['line' => 1, 'file' => 'test-file.php']);
        $frame2 = $this->getFrameInstance(['line' => 1, 'file' => 'test-file.php']);

        self::assertTrue($frame1->equals($frame2));

        $frame1 = $this->getFrameInstance(['line' => 1, 'file' => 'test-file.php']);
        $frame2 = $this->getFrameInstance(['line' => 1, 'file' => 'Unknown']);

        self::assertFalse($frame1->equals($frame2));
    }

    public function testSetAndGetApplication(): void
    {
        $frame = $this->getFrameInstance();

        self::assertFalse($frame->isApplication());

        $frame->setApplication(true);

        self::assertTrue($frame->isApplication());
    }

    public function testGetRawFrame(): void
    {
        $frame = $this->getFrameInstance();

        self::assertEquals($this->getFrameData(), $frame->getRawFrame());
    }

    /**
     * @psalm-return array{file: string, line: int, function: string, class: string, args: array{0: true, 1: string}}
     *
     * @return array<string, array|int|string>
     */
    private function getFrameData(): array
    {
        return [
            'file' => __DIR__ . \DIRECTORY_SEPARATOR . 'Fixture/frame.lines-test.php',
            'line' => 0,
            'function' => 'test',
            'class' => 'MyClass',
            'args' => [true, 'hello'],
        ];
    }

    /**
     * @psalm-param null|array<string, array<array-key, mixed>|int|string> $data
     */
    private function getFrameInstance(?array $data = null): Frame
    {
        if ($data === null) {
            $data = $this->getFrameData();
        }

        return new Frame($data);
    }
}
