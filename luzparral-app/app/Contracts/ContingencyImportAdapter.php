<?php

namespace App\Contracts;

use App\Data\ContingencyImportPreview;
use Illuminate\Http\UploadedFile;

interface ContingencyImportAdapter
{
    public function key(): string;

    public function label(): string;

    public function preview(UploadedFile $file): ContingencyImportPreview;
}
