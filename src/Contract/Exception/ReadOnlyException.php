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

namespace Narrowspark\ExceptionInspector\Contract\Exception;

use RuntimeException;
use Throwable;

final class ReadOnlyException extends RuntimeException
{
    public function __construct(string $functionName, string $className, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct(
            \Safe\sprintf('Calling [%s] method on read-only object [%s] is not allowed.', $functionName, $className),
            $code,
            $previous
        );
    }
}
