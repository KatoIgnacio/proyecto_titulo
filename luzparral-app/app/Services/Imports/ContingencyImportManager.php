<?php

namespace App\Services\Imports;

use App\Contracts\ContingencyImportAdapter;
use InvalidArgumentException;

class ContingencyImportManager
{
    /** @var array<string, ContingencyImportAdapter> */
    private array $adapters;

    public function __construct(SyntheticCsvContingencyAdapter $syntheticCsv)
    {
        $this->adapters = [$syntheticCsv->key() => $syntheticCsv];
    }

    public function adapter(string $key): ContingencyImportAdapter
    {
        return $this->adapters[$key]
            ?? throw new InvalidArgumentException('La fuente de importación no está disponible.');
    }

    /** @return list<array{value: string, label: string}> */
    public function options(): array
    {
        return array_values(array_map(
            static fn (ContingencyImportAdapter $adapter): array => [
                'value' => $adapter->key(),
                'label' => $adapter->label(),
            ],
            $this->adapters,
        ));
    }
}
