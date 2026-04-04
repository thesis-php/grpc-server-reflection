<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection\DescriptorInfoProvider;

use Thesis\Grpc\Server\Reflection\DescriptorInfoProvider;
use Thesis\Protobuf\Registry;

/**
 * @api
 */
final readonly class FromPoolDescriptorInfoProvider implements DescriptorInfoProvider
{
    private Registry\Pool $registry;

    public function __construct(
        ?Registry\Pool $pool = null,
    ) {
        $this->registry = $pool ?? Registry\Pool::get();
    }

    #[\Override]
    public function findByName(string $name): ?DescriptorInfoProvider\File
    {
        return $this->createDescriptorFile($name, $this->registry->fileByName(...));
    }

    #[\Override]
    public function findBySymbol(string $symbol): ?DescriptorInfoProvider\File
    {
        return $this->createDescriptorFile($symbol, $this->registry->fileBySymbol(...));
    }

    /**
     * @param non-empty-string $name
     * @param \Closure(non-empty-string): ?Registry\File $find
     */
    private function createDescriptorFile(
        string $name,
        \Closure $find,
    ): ?DescriptorInfoProvider\File {
        $file = $find($name);

        if ($file !== null) {
            $descriptor = $this->registry->descriptorByFilename($file->name);

            if ($descriptor !== null) {
                return new DescriptorInfoProvider\File(
                    $file->name,
                    $descriptor->bytes,
                    $file->dependencies,
                );
            }
        }

        return null;
    }
}
