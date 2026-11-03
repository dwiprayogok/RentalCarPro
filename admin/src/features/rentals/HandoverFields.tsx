import { Camera, Check, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Field, Input, Textarea } from '@/components/ui/Field'
import { cn } from '@/lib/cn'
import { number } from '@/lib/format'
import { FUEL_LEVELS, type HandoverValue } from './handover'

/** Checklist serah terima: odometer, BBM, kondisi, catatan, foto (diunggah setelah disimpan, privat). */
export function HandoverFields({ value, onChange, minOdometer, errors = {} }: {
  value: HandoverValue
  onChange: (v: HandoverValue) => void
  minOdometer?: number
  errors?: Record<string, string | undefined>
}) {
  const set = <K extends keyof HandoverValue>(k: K, v: HandoverValue[K]) => onChange({ ...value, [k]: v })
  const fileInput = useRef<HTMLInputElement>(null)
  const [previews, setPreviews] = useState<string[]>([])

  useEffect(() => {
    const urls = value.photos.map((f) => URL.createObjectURL(f))
    setPreviews(urls)
    return () => urls.forEach(URL.revokeObjectURL)
  }, [value.photos])

  const odoError = errors.odometer ?? (minOdometer !== undefined && value.odometer !== null && value.odometer < minOdometer
    ? `Tidak boleh di bawah odometer terakhir (${number(minOdometer)} km).`
    : undefined)

  return (
    <div className="space-y-4">
      <div className="grid gap-4 sm:grid-cols-2">
        <Field label="Odometer awal (km)" required hint={minOdometer !== undefined ? `Terakhir tercatat ${number(minOdometer)} km.` : undefined} error={odoError}>
          {(a) => (
            <Input
              {...a}
              type="number"
              inputMode="numeric"
              min={minOdometer ?? 0}
              value={value.odometer ?? ''}
              invalid={!!odoError}
              onChange={(e) => set('odometer', e.target.value === '' ? null : Number(e.target.value))}
            />
          )}
        </Field>
        <div>
          <p className="mb-1.5 text-xs font-semibold text-ink-soft">Level BBM <span className="text-red-600">*</span></p>
          <div role="radiogroup" aria-label="Level BBM" className="grid grid-cols-5 gap-1 rounded-[10px] border border-line-strong p-1">
            {FUEL_LEVELS.map((f) => (
              <button
                key={f.value}
                type="button"
                role="radio"
                aria-checked={value.fuel_level === f.value}
                onClick={() => set('fuel_level', f.value)}
                className={cn('h-8 rounded-md text-xs font-semibold', value.fuel_level === f.value ? 'bg-brand-500 text-white' : 'text-ink-soft hover:bg-canvas')}
              >
                {f.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div>
        <p className="mb-1.5 text-xs font-semibold text-ink-soft">Checklist kondisi</p>
        <ul className="grid gap-2 sm:grid-cols-2">
          {value.checklist.map((c, i) => (
            <li key={c.item} className={cn('rounded-xl border px-3 py-2', c.ok ? 'border-line' : 'border-amber-300 bg-amber-50/60')}>
              <div className="flex items-center justify-between gap-2">
                <span className="text-sm text-ink">{c.item}</span>
                <div className="flex gap-1" role="radiogroup" aria-label={c.item}>
                  {[true, false].map((ok) => (
                    <button
                      key={String(ok)}
                      type="button"
                      role="radio"
                      aria-checked={c.ok === ok}
                      aria-label={ok ? `${c.item} baik` : `${c.item} ada catatan`}
                      onClick={() => set('checklist', value.checklist.map((x, j) => (j === i ? { ...x, ok } : x)))}
                      className={cn(
                        'grid size-7 place-items-center rounded-md border',
                        c.ok === ok ? (ok ? 'border-brand-500 bg-brand-500 text-white' : 'border-amber-500 bg-amber-500 text-white') : 'border-line-strong text-ink-muted',
                      )}
                    >
                      {ok ? <Check className="size-4" /> : <X className="size-4" />}
                    </button>
                  ))}
                </div>
              </div>
              {!c.ok && (
                <input
                  value={c.note ?? ''}
                  onChange={(e) => set('checklist', value.checklist.map((x, j) => (j === i ? { ...x, note: e.target.value } : x)))}
                  placeholder="Catatan, mis. baret pintu kiri"
                  aria-label={`Catatan ${c.item}`}
                  className="mt-2 h-8 w-full rounded-md border border-line-strong bg-white px-2 text-xs focus:border-brand-500 focus:outline-none"
                />
              )}
            </li>
          ))}
        </ul>
      </div>

      <Field label="Catatan serah terima">
        {(a) => <Textarea {...a} rows={2} value={value.notes} onChange={(e) => set('notes', e.target.value)} placeholder="Mis. kunci + STNK diserahkan" />}
      </Field>

      <div>
        <p className="mb-1.5 text-xs font-semibold text-ink-soft">Foto kondisi (privat)</p>
        <div className="flex flex-wrap gap-2">
          {previews.map((src, i) => (
            <div key={src} className="relative">
              <img src={src} alt={`Foto ${i + 1}`} className="size-16 rounded-lg object-cover ring-1 ring-line" />
              <button
                type="button"
                onClick={() => set('photos', value.photos.filter((_, j) => j !== i))}
                className="absolute -top-1.5 -right-1.5 grid size-5 place-items-center rounded-full bg-sidebar text-white"
                aria-label={`Hapus foto ${i + 1}`}
              >
                <X className="size-3" />
              </button>
            </div>
          ))}
          {value.photos.length < 12 && (
            <button type="button" onClick={() => fileInput.current?.click()} className="grid size-16 place-items-center rounded-lg border-2 border-dashed border-line-strong text-ink-muted hover:border-brand-300 hover:text-brand-600" aria-label="Tambah foto kondisi">
              <Camera className="size-5" />
            </button>
          )}
        </div>
        <input
          ref={fileInput}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          capture="environment"
          multiple
          hidden
          onChange={(e) => {
            const files = Array.from(e.target.files ?? []).filter((f) => f.size <= 5 * 1024 * 1024)
            set('photos', [...value.photos, ...files].slice(0, 12))
            e.target.value = ''
          }}
        />
        <p className="mt-1 text-2xs text-ink-muted">JPG/PNG/WebP maks. 5 MB, hingga 12 foto. Diunggah setelah data tersimpan.</p>
      </div>
    </div>
  )
}
