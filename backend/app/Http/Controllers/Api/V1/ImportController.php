<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Import\ImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Import data massal (armada, pelanggan, driver) dari Excel/CSV. */
class ImportController extends Controller
{
    public function __construct(private readonly ImportService $imports) {}

    public function template(Request $request, string $type): StreamedResponse
    {
        $def = $this->imports->definition($type);
        $this->imports->authorize($request->user(), $def);
        $book = $this->imports->template($def);

        return response()->streamDownload(fn () => $this->imports->writeTemplate($book), "template-import-{$type}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function preview(Request $request, string $type): JsonResponse
    {
        $def = $this->imports->definition($type);
        $this->imports->authorize($request->user(), $def);
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt'],
        ], [
            'file.mimes' => 'File harus berformat Excel (.xlsx/.xls) atau CSV.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ], ['file' => 'file']);

        return response()->json(['data' => $this->imports->preview($def, $request->file('file'), $request->user())]);
    }

    public function commit(Request $request, string $type): JsonResponse
    {
        $def = $this->imports->definition($type);
        $this->imports->authorize($request->user(), $def);
        $f = $request->validate(['token' => ['required', 'uuid']]);
        $r = $this->imports->commit($def, $f['token'], $request->user());

        return response()->json([
            'data' => $r,
            'message' => "{$r['created']} {$def->label()} berhasil diimpor".(count($r['skipped']) ? ', '.count($r['skipped']).' dilewati.' : '.'),
        ]);
    }
}
