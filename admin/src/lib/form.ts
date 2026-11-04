import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'
import { fieldErrors } from './api'

/**
 * Pasang error validasi dari server (422) ke field form. Mengembalikan pesan untuk
 * error yang tidak punya field di form (ditampilkan sebagai alert umum).
 */
export function applyServerErrors<T extends FieldValues>(
  error: unknown,
  setError: UseFormSetError<T>,
  knownFields: readonly string[],
): string | null {
  const errors = fieldErrors(error)
  const orphan: string[] = []
  for (const [field, message] of Object.entries(errors)) {
    if (knownFields.includes(field)) {
      setError(field as Path<T>, { type: 'server', message }, { shouldFocus: true })
    } else {
      orphan.push(message)
    }
  }
  return orphan.length ? orphan.join(' ') : null
}
