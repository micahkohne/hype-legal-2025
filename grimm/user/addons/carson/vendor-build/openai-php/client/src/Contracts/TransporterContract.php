<?php

declare (strict_types=1);
namespace BoldMinded\Carson\Dependency\OpenAI\Contracts;

use BoldMinded\Carson\Dependency\OpenAI\Exceptions\ErrorException;
use BoldMinded\Carson\Dependency\OpenAI\Exceptions\TransporterException;
use BoldMinded\Carson\Dependency\OpenAI\Exceptions\UnserializableResponse;
use BoldMinded\Carson\Dependency\OpenAI\ValueObjects\Transporter\Payload;
use BoldMinded\Carson\Dependency\OpenAI\ValueObjects\Transporter\Response;
use BoldMinded\Carson\Dependency\Psr\Http\Message\ResponseInterface;
/**
 * @internal
 */
interface TransporterContract
{
    /**
     * Sends a request to a server.
     *
     * @return Response<array<array-key, mixed>|string>
     *
     * @throws ErrorException|UnserializableResponse|TransporterException
     */
    public function requestObject(Payload $payload) : Response;
    /**
     * Sends a content request to a server.
     *
     * @throws ErrorException|TransporterException
     */
    public function requestContent(Payload $payload) : string;
    /**
     * Sends a stream request to a server.
     **
     * @throws ErrorException
     */
    public function requestStream(Payload $payload) : ResponseInterface;
}
