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

use BoldMinded\Carson\Dependency\Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
/**
 * Thrown by responses' toArray() method when their content cannot be JSON-decoded.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
final class JsonException extends \JsonException implements DecodingExceptionInterface
{
}
