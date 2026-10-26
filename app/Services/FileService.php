<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * Private attachment handling.
 *
 * Documented assumptions (pending review):
 *  - hard limit 5 MB per file (5242880 bytes)
 *  - allowlist: pdf, png, jpg, jpeg, gif, webp, doc, docx, xls, xlsx, zip
 *  - files live on the private "local" disk under transactions/{number}/
 */
class FileService
{
    public const MAX_BYTES = 5242880;

    public const ALLOWED_MIMES = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'zip'];

    /**
     * Store an uploaded file and create its row. Caller supplies the DB
     * transaction context (this method must run inside one).
     *
     * @return array{0: string, 1: \App\Models\TransactionFile} stored path + model
     */
    public function store(Transaction $transaction, UploadedFile $file, ?int $uploaderId): array
    {
        $directory = 'transactions/'.$transaction->transaction_number;
        $path = $file->store($directory, 'local');

        if ($path === false) {
            throw new \RuntimeException('تعذر حفظ الملف المرفق');
        }

        $row = $transaction->files()->create([
            'original_name' => basename($file->getClientOriginalName()),
            'stored_path' => $path,
            'mime' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by' => $uploaderId,
        ]);

        return [$path, $row];
    }

    public function deleteFromDisk(string $storedPath): void
    {
        Storage::disk('local')->delete($storedPath);
    }
}
