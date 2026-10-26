<?php

namespace App\Http\Controllers;

use App\Models\TransactionFile;
use Illuminate\Support\Facades\Storage;

/**
 * تنزيل المرفقات الخاصة: يُصرَّف عبر authorize('download', المعاملة الأب)
 * بسياسة TransactionPolicy — لا يُنزَّل مرفق إلا لمن يُسمح له برؤية المعاملة.
 */
class FileController extends Controller
{
    /**
     * Private attachment download — authorized against the parent
     * transaction's visibility (TransactionPolicy::download).
     */
    public function download(int $id)
    {
        $file = TransactionFile::with('transaction')->findOrFail($id);

        $this->authorize('download', $file->transaction);

        abort_unless(Storage::disk('local')->exists($file->stored_path), 404);

        return Storage::disk('local')->download($file->stored_path, $file->original_name, [
            'Content-Type' => $file->mime ?: 'application/octet-stream',
        ]);
    }
}
