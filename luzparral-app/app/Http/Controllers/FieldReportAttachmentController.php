<?php

namespace App\Http\Controllers;

use App\Models\FieldReportAttachment;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FieldReportAttachmentController extends Controller
{
    public function download(FieldReportAttachment $attachment): StreamedResponse
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }
}
