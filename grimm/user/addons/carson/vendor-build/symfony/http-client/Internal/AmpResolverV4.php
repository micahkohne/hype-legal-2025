<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace BoldMinded\Carson\Dependency\Symfony\Component\HttpClient\Internal;

use BoldMinded\Carson\Dependency\Amp\Dns;
use BoldMinded\Carson\Dependency\Amp\Dns\Record;
use BoldMinded\Carson\Dependency\Amp\Promise;
use BoldMinded\Carson\Dependency\Amp\Success;
/**
 * Handles local overrides for the DNS resolver.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
class AmpResolverV4 implements Dns\Resolver
{
    public function __construct(private array &$dnsMap)
    {
    }
    public function resolve(string $name, ?int $typeRestriction = null) : Promise
    {
        $recordType = Record::A;
        $ip = $this->dnsMap[$name] ?? null;
        if (null !== $ip && \str_contains($ip, ':')) {
            $recordType = Record::AAAA;
        }
        if (null === $ip || $recordType !== ($typeRestriction ?? $recordType)) {
            return Dns\resolver()->resolve($name, $typeRestriction);
        }
        return new Success([new Record($ip, $recordType, null)]);
    }
    public function query(string $name, int $type) : Promise
    {
        $recordType = Record::A;
        $ip = $this->dnsMap[$name] ?? null;
        if (null !== $ip && \str_contains($ip, ':')) {
            $recordType = Record::AAAA;
        }
        if (null === $ip || $recordType !== $type) {
            return Dns\resolver()->query($name, $type);
        }
        return new Success([new Record($ip, $recordType, null)]);
    }
}
