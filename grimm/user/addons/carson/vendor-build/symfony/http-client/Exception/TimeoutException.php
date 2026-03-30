<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace BoldMinded\Carson\Dependency\Symfony\Component\HttpClient\Exception;

use BoldMinded\Carson\Dependency\Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class TimeoutException extends TransportException implements TimeoutExceptionInterface
{
}
