<?php

namespace App\Data;

class ContingencyImportPreview
{
    /**
     * @param  list<array<string, mixed>>  $acceptedRows
     * @param  list<array{source_row_number: int, field_name: ?string, error_code: string, message: string, synthetic_reference: string}>  $errors
     */
    public function __construct(
        public readonly string $checksum,
        public readonly string $encoding,
        public readonly string $delimiter,
        public readonly int $totalRows,
        public readonly array $acceptedRows,
        public readonly array $errors,
        public readonly bool $fatal = false,
    ) {}

    public function acceptedCount(): int
    {
        return count($this->acceptedRows);
    }

    public function rejectedCount(): int
    {
        return max(0, $this->totalRows - $this->acceptedCount());
    }

    public function canImport(): bool
    {
        return ! $this->fatal && $this->totalRows > 0;
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        return [
            'checksum' => $this->checksum,
            'encoding' => $this->encoding,
            'delimiter' => match ($this->delimiter) {
                "\t" => 'tabulación',
                ';' => 'punto y coma',
                default => 'coma',
            },
            'total_rows' => $this->totalRows,
            'accepted_rows' => $this->acceptedCount(),
            'rejected_rows' => $this->rejectedCount(),
            'can_import' => $this->canImport(),
            'accepted_references' => array_values(array_slice(array_map(
                static fn (array $row): string => (string) $row['code'],
                $this->acceptedRows,
            ), 0, 20)),
            'errors' => array_values(array_slice($this->errors, 0, 100)),
            'errors_truncated' => max(0, count($this->errors) - 100),
        ];
    }
}
