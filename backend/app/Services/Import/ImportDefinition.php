<?php

namespace App\Services\Import;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/** Definisi satu jenis import (armada, pelanggan, driver). */
interface ImportDefinition
{
    public function key(): string;

    /** Label jamak untuk pesan, mis. "armada". */
    public function label(): string;

    /** @return list<Column> */
    public function columns(): array;

    /** @return class-string<FormRequest> */
    public function requestClass(): string;

    /** Permission tambah untuk jenis ini (selain data.import). */
    public function createPermission(): string;

    /** Kunci unik dalam satu file (untuk deteksi baris ganda), null bila tidak bisa ditentukan. */
    public function fileKey(array $input): ?string;

    /** Pesan bila data sudah ada di database (baris dilewati), null bila belum ada. */
    public function existing(array $input): ?string;

    /** Ringkasan baris untuk pratinjau (tanpa data sensitif). */
    public function summary(array $input): string;

    public function create(array $validated, User $by): void;
}
