<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Pencatatan pengeluaran. Bukti (struk/nota) disimpan di disk privat dan hanya bisa dibuka
 * lewat signed URL berumur pendek.
 */
class ExpenseService
{
    public const DISK = 'private';

    /** @param array<string, mixed> $data */
    public function create(array $data, User $by, ?UploadedFile $proof = null): Expense
    {
        $stored = $proof ? $this->store($proof) : null;

        try {
            return DB::transaction(fn () => Expense::create(
                collect($data)->except(['proof', 'remove_proof'])->all() + ['created_by' => $by->id, 'proof_path' => $stored],
            ));
        } catch (\Throwable $e) {
            $stored && Storage::disk(self::DISK)->delete($stored);
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Expense $expense, array $data, ?UploadedFile $proof = null): Expense
    {
        $old = $expense->proof_path;
        $stored = $proof ? $this->store($proof) : null;
        $removeOld = $stored || ! empty($data['remove_proof']);

        try {
            DB::transaction(function () use ($expense, $data, $stored, $removeOld) {
                $fill = collect($data)->except(['proof', 'remove_proof'])->all();
                if ($removeOld) {
                    $fill['proof_path'] = $stored;
                }
                $expense->update($fill);
            });
        } catch (\Throwable $e) {
            $stored && Storage::disk(self::DISK)->delete($stored);
            throw $e;
        }

        if ($removeOld && $old) {
            Storage::disk(self::DISK)->delete($old);
        }

        return $expense;
    }

    /** Soft delete: bukti tetap disimpan agar jejak audit utuh. */
    public function delete(Expense $expense): void
    {
        $expense->delete();
    }

    public function proofUrl(Expense $expense): ?string
    {
        return $expense->proof_path
            ? URL::temporarySignedRoute('v1.expenses.proof', now()->addMinutes(10), ['expense' => $expense->id])
            : null;
    }

    private function store(UploadedFile $file): string
    {
        return $file->storeAs('expenses/'.now()->format('Y/m'), Str::uuid().'.'.$file->extension(), self::DISK);
    }
}
