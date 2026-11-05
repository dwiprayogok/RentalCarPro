import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, FormSection, Input, Switch, Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, errorStatus } from '@/lib/api'
import { cn } from '@/lib/cn'
import { applyServerErrors } from '@/lib/form'
import { rupiah } from '@/lib/format'
import { useSaveOwner, type CarOwner } from './api'

const schema = z.object({
  name: z.string().trim().min(2, 'Nama wajib diisi.').max(100),
  phone: z.string().trim().min(9, 'Nomor HP wajib diisi.').max(20),
  email: z.union([z.literal(''), z.email('Format email tidak valid.')]),
  address: z.string().trim().max(255),
  bank_name: z.string().trim().max(60),
  bank_account_number: z.string().trim().max(40).regex(/^[\d\s.-]*$/, 'Nomor rekening hanya berisi angka.'),
  bank_account_name: z.string().trim().max(100),
  share_basis: z.enum(['kotor', 'bersih']),
  share_percent: z.number({ error: 'Isi persentase.' }).gt(0, 'Harus lebih dari 0%.').max(100, 'Maksimal 100%.'),
  notes: z.string().trim().max(1000),
  is_active: z.boolean(),
}).refine((v) => !v.bank_name === !v.bank_account_number, { path: ['bank_account_number'], message: 'Isi nama bank dan nomor rekening sekaligus.' })
type Values = z.infer<typeof schema>
const FIELDS = ['name', 'phone', 'email', 'address', 'bank_name', 'bank_account_number', 'bank_account_name', 'share_basis', 'share_percent', 'notes', 'is_active'] as const

const BASIS = [
  { value: 'kotor' as const, title: 'Dari pendapatan kotor', text: 'Pemilik dapat % dari uang sewa yang diterima. Biaya servis & pengeluaran ditanggung rental.' },
  { value: 'bersih' as const, title: 'Dari laba bersih', text: 'Pendapatan dikurangi biaya servis & pengeluaran mobil itu, baru dibagi. Biaya ikut ditanggung pemilik.' },
]

export function OwnerFormModal({ open, owner, onClose, onSaved }: { open: boolean; owner: CarOwner | null; onClose: () => void; onSaved?: (o: CarOwner) => void }) {
  const toast = useToast()
  const save = useSaveOwner()
  const [formError, setFormError] = useState<string | null>(null)
  const { register, control, handleSubmit, reset, setError, formState: { errors } } = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!open) return
    setFormError(null)
    reset({
      name: owner?.name ?? '', phone: owner?.phone ?? '', email: owner?.email ?? '', address: owner?.address ?? '',
      bank_name: owner?.bank_name ?? '', bank_account_number: owner?.bank_account_number ?? '', bank_account_name: owner?.bank_account_name ?? '',
      share_basis: owner?.share_basis ?? 'kotor', share_percent: owner?.share_percent ?? 70, notes: owner?.notes ?? '', is_active: owner?.is_active ?? true,
    })
  }, [open, owner, reset])

  const basis = useWatch({ control, name: 'share_basis' })
  const pct = useWatch({ control, name: 'share_percent' })
  const example = 10_000_000
  const exampleCost = 2_000_000
  const exampleBase = basis === 'kotor' ? example : example - exampleCost
  const exampleOwner = Number.isFinite(pct) ? Math.round((exampleBase * (pct || 0)) / 100) : 0

  const onSubmit = handleSubmit((v) => {
    setFormError(null)
    const blank = (s: string) => (s.trim() === '' ? null : s.trim())
    save.mutate({ id: owner?.id, payload: {
      name: v.name, phone: v.phone, email: blank(v.email), address: blank(v.address), bank_name: blank(v.bank_name),
      bank_account_number: blank(v.bank_account_number), bank_account_name: blank(v.bank_account_name),
      share_basis: v.share_basis, share_percent: v.share_percent, notes: blank(v.notes), is_active: v.is_active,
    } }, {
      onSuccess: (r) => { toast(r.message); onSaved?.(r.data); onClose() },
      onError: (e) => {
        const orphan = applyServerErrors(e, setError, FIELDS)
        if (errorStatus(e) !== 422 || orphan) setFormError(orphan ?? errorMessage(e))
      },
    })
  })

  return (
    <Modal open={open} onClose={onClose} dismissible={!save.isPending} size="lg" title={owner ? 'Ubah pemilik titipan' : 'Tambah pemilik titipan'}
      description="Pemilik mobil yang dititipkan untuk disewakan. Atur ketentuan bagi hasil per pemilik."
      footer={<><Button variant="secondary" onClick={onClose} disabled={save.isPending}>Batal</Button><Button type="submit" form="owner-form" loading={save.isPending}>Simpan</Button></>}>
      <form id="owner-form" onSubmit={onSubmit} noValidate className="space-y-6">
        {formError && <Alert>{formError}</Alert>}
        <FormSection title="Data pemilik">
          <Field label="Nama lengkap" required error={errors.name?.message}>{(a) => <Input {...a} {...register('name')} invalid={!!errors.name} />}</Field>
          <Field label="Nomor HP / WhatsApp" required hint="Slip bagi hasil dikirim ke nomor ini." error={errors.phone?.message}>
            {(a) => <Input {...a} type="tel" inputMode="tel" placeholder="08xx-xxxx-xxxx" {...register('phone')} invalid={!!errors.phone} />}
          </Field>
          <Field label="Email" error={errors.email?.message}>{(a) => <Input {...a} type="email" {...register('email')} invalid={!!errors.email} />}</Field>
          <Field label="Alamat" error={errors.address?.message}>{(a) => <Input {...a} {...register('address')} invalid={!!errors.address} />}</Field>
        </FormSection>

        <FormSection title="Rekening tujuan transfer">
          <Field label="Nama bank" error={errors.bank_name?.message}>{(a) => <Input {...a} placeholder="BCA, Mandiri, BRI…" {...register('bank_name')} invalid={!!errors.bank_name} />}</Field>
          <Field label="Nomor rekening" error={errors.bank_account_number?.message}>{(a) => <Input {...a} inputMode="numeric" {...register('bank_account_number')} invalid={!!errors.bank_account_number} />}</Field>
          <Field label="Atas nama" className="sm:col-span-2" error={errors.bank_account_name?.message}>{(a) => <Input {...a} {...register('bank_account_name')} invalid={!!errors.bank_account_name} />}</Field>
        </FormSection>

        <FormSection title="Ketentuan bagi hasil" description="Berlaku untuk setoran berikutnya. Setoran yang sudah dibuat tidak berubah.">
          <div className="grid gap-2 sm:col-span-2 sm:grid-cols-2" role="radiogroup" aria-label="Dasar bagi hasil">
            {BASIS.map((b) => (
              <label key={b.value} className={cn('cursor-pointer rounded-xl border p-3 transition', basis === b.value ? 'border-brand-500 bg-brand-50/60 ring-1 ring-brand-500' : 'border-line hover:bg-canvas')}>
                <input type="radio" value={b.value} {...register('share_basis')} className="sr-only" />
                <span className="block text-sm font-bold text-ink">{b.title}</span>
                <span className="mt-0.5 block text-xs text-ink-soft">{b.text}</span>
              </label>
            ))}
          </div>
          <Field label="Bagian pemilik" required error={errors.share_percent?.message}>
            {(a) => (
              <Controller control={control} name="share_percent" render={({ field }) => (
                <div className="relative">
                  <Input {...a} type="number" inputMode="decimal" step="0.5" min={0} max={100} className="pr-9 tabular-nums"
                    value={Number.isFinite(field.value) ? field.value : ''} onChange={(e) => field.onChange(e.target.value === '' ? Number.NaN : Number(e.target.value))} onBlur={field.onBlur} invalid={!!errors.share_percent} />
                  <span className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm font-semibold text-ink-muted">%</span>
                </div>
              )} />
            )}
          </Field>
          <div className="rounded-xl bg-canvas px-3 py-2 text-xs text-ink-soft">
            <p className="font-semibold text-ink">Contoh perhitungan</p>
            <p>Pendapatan {rupiah(example)}{basis === 'bersih' && `, biaya ${rupiah(exampleCost)}`} → pemilik <b className="text-ink">{rupiah(exampleOwner)}</b>, rental {rupiah(exampleBase - exampleOwner)}{basis === 'kotor' && ` (biaya ${rupiah(exampleCost)} ditanggung rental)`}.</p>
          </div>
        </FormSection>

        <FormSection title="Lainnya">
          <Field label="Catatan" className="sm:col-span-2" error={errors.notes?.message}>{(a) => <Textarea {...a} rows={2} {...register('notes')} />}</Field>
          {owner && (
            <div className="sm:col-span-2">
              <Controller control={control} name="is_active" render={({ field }) => (
                <Switch checked={field.value} onChange={field.onChange} label="Pemilik aktif" description="Nonaktifkan bila kerja sama berakhir; riwayat setoran tetap tersimpan." />
              )} />
            </div>
          )}
        </FormSection>
      </form>
    </Modal>
  )
}
