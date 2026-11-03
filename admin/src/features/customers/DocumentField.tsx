import { FileText, Upload, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { cn } from '@/lib/cn'

const MAX_BYTES = 5 * 1024 * 1024
const ACCEPT = 'image/jpeg,image/png,image/webp,application/pdf'

/** Pilih foto KTP/SIM. Berkas baru diunggah setelah data pelanggan disimpan. */
export function DocumentField({ label, file, onChange, existingUrl, existing, onRemoveExisting, disabled }: {
  label: string
  file: File | null
  onChange: (file: File | null) => void
  existingUrl?: string | null
  existing?: boolean
  onRemoveExisting?: () => void
  disabled?: boolean
}) {
  const input = useRef<HTMLInputElement>(null)
  const [error, setError] = useState<string | null>(null)
  const [preview, setPreview] = useState<string | null>(null)

  useEffect(() => {
    if (!file || !file.type.startsWith('image/')) { setPreview(null); return }
    const url = URL.createObjectURL(file)
    setPreview(url)
    return () => URL.revokeObjectURL(url)
  }, [file])

  const shown = preview ?? (file ? null : existingUrl)
  const hasSomething = !!file || existing

  return (
    <div>
      <p className="mb-1.5 text-xs font-semibold text-ink-soft">{label}</p>
      <div className={cn('flex items-center gap-3 rounded-xl border border-dashed p-3', error ? 'border-red-400' : 'border-line-strong')}>
        <div className="grid size-14 shrink-0 place-items-center overflow-hidden rounded-lg bg-canvas text-ink-muted">
          {shown ? <img src={shown} alt={`Pratinjau ${label}`} className="size-full object-cover" /> : <FileText className="size-5" />}
        </div>
        <div className="min-w-0 flex-1 text-xs">
          {file ? (
            <p className="truncate font-semibold text-ink">{file.name}</p>
          ) : existing ? (
            <p className="font-semibold text-ink">Sudah diunggah{existingUrl && <> · <a href={existingUrl} target="_blank" rel="noreferrer" className="text-brand-600 hover:underline">Lihat</a></>}</p>
          ) : (
            <p className="text-ink-muted">Belum ada berkas</p>
          )}
          <p className="text-2xs text-ink-muted">JPG/PNG/WebP/PDF, maks. 5 MB. Disimpan privat.</p>
          {error && <p className="text-2xs font-medium text-red-600" role="alert">{error}</p>}
        </div>
        {!disabled && (
          <div className="flex shrink-0 gap-1">
            <button type="button" onClick={() => input.current?.click()} className="rounded-lg p-2 text-brand-600 hover:bg-brand-50" aria-label={`Pilih ${label}`}>
              <Upload className="size-4" />
            </button>
            {hasSomething && (
              <button
                type="button"
                onClick={() => (file ? onChange(null) : onRemoveExisting?.())}
                className="rounded-lg p-2 text-ink-muted hover:bg-red-50 hover:text-red-600"
                aria-label={`Hapus ${label}`}
              >
                <X className="size-4" />
              </button>
            )}
          </div>
        )}
        <input
          ref={input}
          type="file"
          accept={ACCEPT}
          hidden
          onChange={(e) => {
            const f = e.target.files?.[0] ?? null
            e.target.value = ''
            if (!f) return
            if (f.size > MAX_BYTES) { setError('Ukuran berkas maksimal 5 MB.'); return }
            if (!ACCEPT.split(',').includes(f.type)) { setError('Format harus JPG, PNG, WebP, atau PDF.'); return }
            setError(null)
            onChange(f)
          }}
        />
      </div>
    </div>
  )
}
