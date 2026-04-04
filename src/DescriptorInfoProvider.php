<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection;

/**
 * @api
 */
interface DescriptorInfoProvider
{
    /**
     * @param non-empty-string $name
     */
    public function findByName(string $name): ?DescriptorInfoProvider\File;

    /**
     * @param non-empty-string $symbol
     */
    public function findBySymbol(string $symbol): ?DescriptorInfoProvider\File;
}
