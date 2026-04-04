<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection\DescriptorInfoProvider;

/**
 * @api
 */
final readonly class File
{
    /**
     * @param non-empty-string $name
     * @param list<non-empty-string> $imports
     */
    public function __construct(
        public string $name,
        public string $bytes,
        public array $imports = [],
    ) {}
}
