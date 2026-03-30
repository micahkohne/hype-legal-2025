<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Enums\Transporter;

/**
 * @internal
 */
enum Method : string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
}
