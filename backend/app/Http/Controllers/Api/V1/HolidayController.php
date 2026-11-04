<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Holiday;
use App\Support\Permissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tanggal libur untuk tarif akhir pekan/libur. Baca: setting.view. Ubah: setting.update. */
class HolidayController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permissions::SETTING_VIEW), 403);
        $year = $request->integer('year', (int) now(config('app.display_timezone'))->format('Y'));

        return response()->json(['data' => Holiday::query()->whereYear('date', $year)->orderBy('date')->get()
            ->map(fn (Holiday $h) => ['id' => $h->id, 'date' => $h->date->toDateString(), 'name' => $h->name])]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->can(Permissions::SETTING_UPDATE), 403);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')],
            'name' => ['required', 'string', 'min:3', 'max:100'],
        ], ['date.unique' => 'Tanggal ini sudah terdaftar sebagai hari libur.'], ['date' => 'tanggal', 'name' => 'nama hari libur']);

        $holiday = Holiday::create($data);
        activity('setting')->performedOn($holiday)->causedBy($request->user())->event('created')
            ->withProperties(['attributes' => $data])->log("Tambah hari libur {$data['date']}");

        return response()->json(['data' => ['id' => $holiday->id, 'date' => $data['date'], 'name' => $data['name']], 'message' => 'Hari libur ditambahkan.'], 201);
    }

    public function destroy(Request $request, Holiday $holiday): JsonResponse
    {
        abort_unless($request->user()->can(Permissions::SETTING_UPDATE), 403);
        activity('setting')->performedOn($holiday)->causedBy($request->user())->event('deleted')
            ->withProperties(['old' => ['date' => $holiday->date->toDateString(), 'name' => $holiday->name]])->log("Hapus hari libur {$holiday->date->toDateString()}");
        $holiday->delete();

        return response()->json(['message' => 'Hari libur dihapus.']);
    }
}
