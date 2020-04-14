# Exception inspector

Inspector for exception stack trace.

## Installation

Run

```
$ composer require narrowspark/exception-inspector
```

## Usage

The `Inspector` class provides methods to inspect an exception instance, with particular focus on its frames or stack-trace.

```php
<?php

declare(strict_types=1);

use Narrowspark\ExceptionInspector\Frame;
use Narrowspark\ExceptionInspector\FrameCollection;
use Narrowspark\ExceptionInspector\Inspector;

$exception = new \Exception('This is a error');

$inspector = new Inspector($exception);

/**
 * Returns an iterator instance for all the frames in the stack
 * trace for the Exception being inspected.
 *
 * @var \Narrowspark\ExceptionInspector\FrameIterator $frames
 */
$frames = $inspector->getFrames();

// Returns the string name of the Exception being inspected
// A faster way of doing get_class($inspector->getException())
echo $inspector->getExceptionName();

// Returns the string message for the Exception being inspected
// A faster way of doing $inspector->getException()->getMessage()
echo $inspector->getExceptionMessage();
```

The `FrameCollection` class exposes a fluent interface to manipulate and examine a collection of `Frame` instances.

```php
// Returns the number of frames in the collection
echo $frames->count();

// @see [array_filter](https://www.php.net/manual/en/function.array-filter)
// Filter the Frames in the collection with a callable.
// The callable must accept a Frame object, and return
// true to keep it in the collection, or false not to.
$filteredFrames = $frames->filter(function(Frame $frame): bool {
    return true;
});

// @see: [array_map](https://www.php.net/manual/en/function.array-map.php)
// The callable must accept a Frame object, and return
// a Frame object, doesn't matter if it's the same or not
// - will throw an UnexpectedValueException if something
// else is returned.
$mapedFrames = $frames->map(function (Frame $frame): Frame {
    return $frame;
});
```

The `Frame` class models a single frame in an exception’s stack trace.
You can use it to retrieve info about frame context, file, line number.

You have available functionality to add comments to a frame.

```php
foreach ($frames as $frame) {
    // Returns the file path for the file where this frame occurred.
    $frame->getFile();

    // Returns the line number for this frame
    $frame->getLine();

    // Returns the class name for this frame, if it occurred
    // within a class/instance.
    $frame->getClass();

    // Returns the function name for this frame, if it occurred
    // within a function/method
    $frame->getFunction();

    // Returns an array of arguments for this frame. Empty if no
    // arguments were provided.
    $frame->getArgs();

    // Returns the full file contents for the file where this frame
    // occurred.
    $frame->getFileContents();

    // Returns an array of lines for a file.
    $frame->getFileLines();

    // Optionally scoped to a given range of line numbers.
    // i.e: Frame::getFileLines(0, 3) returns the first 3
    // lines after line 0 (1)
    $frame->getFileLines(0, 3);
}
```

## Versioning

This library follows semantic versioning, and additions to the code ruleset are performed in major releases.

## Changelog

Please have a look at [`CHANGELOG.md`](CHANGELOG.md).

## Contributing

Please have a look at [`CONTRIBUTING.md`](.github/CONTRIBUTING.md).

Thanks [whoops](https://github.com/filp/whoops) for the class interfaces.

## Code of Conduct

Please have a look at [`CODE_OF_CONDUCT.md`](.github/CODE_OF_CONDUCT.md).

## License

This package is licensed using the MIT License.

Please have a look at [`LICENSE.md`](LICENSE.md).
