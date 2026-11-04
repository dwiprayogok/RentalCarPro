import { zodResolver } from '@hookform/resolvers/zod'
import { AlertTriangle, ArrowLeft, CheckCircle2, Plus, Trash2, Wrench } from 'lucide-react'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { Controller, useFieldArray, useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, FormSection, Input, Select } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { MoneyInput } from '@/components/ui/MoneyInput'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, fieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import { fromWibInput, toWibInput } from '@/lib/datetime'
import { dateTimeWib, number, rupiah } from '@/lib/format'
import { useDebounce } from '@/lib/useDebounce'
import { uploadInspectionPhotos, useCompleteReturn, useReturnPreview, type ReturnInput } from './api'
import { DEFAULT_CHECKLIST, type HandoverValue } from './handover'
import { HandoverFields } from './HandoverFields'
import type { FeeType, Rental, ReturnPreview } from './types'

const FEE_TYPES: { value: FeeType; label: string }[] = [
  { value: 'bbm', label: 'BBM kurang' },
  { value: 'kebersihan', label: 'Kebersihan' },
  { value: 'kelebihan_km', label: 'Kelebihan km' },
  { value: 'kerusakan', label: 'Kerusakan' },
  { value: 'lainnya', label: 'Lainnya' },
]
const METHODS = [{ value: 'tunai', label: 'Tunai' }, { value: 'transfer', label: 'Transfer' }, { value: 'qris', label: 'QRIS' }] as const

const schema = z.object({
  returned_at: z.string().min(1, 'Isi waktu kembali.'),
  fees: z.array(z.object({
    type: z.enum(['bbm', 'kebersihan', 'kelebihan_km', 'kerusakan', 'lainnya']),
    amount: z.number().int().min(0).nullable(),
    reason: z.string().trim().max(500),
  })).superRefine((fees, ctx) => fees.forEach((f, i) => {
    if ((f.amount ?? 0) > 0 && !f.reason) ctx.addIssue({ code: 'custom', path: [i, 'reason'], message: 'Alasan wajib diisi.' })
  })),
  deposit_deduction: z.number().int().min(0).nullable(),
  payment_amount: z.number().int().min(0).nullable(),
  payment_method: z.enum(['tunai', 'transfer', 'qris']),
  payment_reference: z.string().trim().max(100),
  refund_method: z.enum(['tunai', 'transfer', 'qris']),
  car_status: z.enum(['auto', 'tersedia', 'servis']),
})
type Values = z.infer<typeof schema>

function Row({ label, value, strong, tone }: { label: ReactNode; value: ReactNode; strong?: boolean; tone?: 'red' | 'green' | 'amber' | 'muted' }) {
  const color = { red: 'text-red-700', green: 'text-brand-700', amber: 'text-amber-700', muted: 'text-ink-muted' }
  return (
    <div className={cn('flex items-baseline justify-between gap-4', strong ? 'text-base font-extrabold' : 'text-sm')}>
      <span className={strong ? 'text-ink' : 'text-ink-muted'}>{label}</span>
      <span className={cn('text-right font-semibold tabular-nums', tone && color[tone])}>{value}</span>
    </div>
  )
}

function Summary({ p, rental }: { p: ReturnPreview; rental: Rental }) {
  return (
    <div className="space-y-1.5">
      <Row label={`Sewa (${rental.billed_days} hari × ${rupiah(rental.daily_rate)})`} value={rupiah(p.rental_amount)} />
      {p.discount > 0 && <Row label="Diskon" value={`− ${rupiah(p.discount)}`} />}
      <Row
        label={<>Denda keterlambatan<br /><span className="text-2xs">
          {p.late.late_minutes <= 0 ? 'Tepat waktu' : p.late.within_grace ? `Terlambat ${p.late.late_label} (dalam toleransi)` : `Terlambat ${p.late.late_label}${p.late.extra_days ? ` · ${p.late.extra_days} hari tambahan` : ''}${p.late.hourly_fee ? ` · ${p.late.charged_hours} jam${p.late.capped ? ' (maks. 1 hari)' : ''}` : ''}`}
        </span></>}
        value={rupiah(p.late.total)}
        tone={p.late.total > 0 ? 'red' : undefined}
      />
      <Row label="Biaya tambahan" value={rupiah(p.extra_fees_total)} />
      <div className="border-t border-dashed border-line-strong pt-2"><Row strong label="Total akhir" value={rupiah(p.total_amount)} /></div>
      <Row label="Sudah dibayar" value={rupiah(p.paid_amount)} />
      <Row label="Sisa sebelum deposit" value={rupiah(p.balance_before)} />
      <Row label="Dipotong dari deposit" value={`− ${rupiah(p.deposit_deduction)}`} />
      <Row label="Dibayar sekarang" value={`− ${rupiah(p.payment_amount)}`} />
      <div className="border-t border-line pt-2">
        <Row strong label={p.remaining > 0 ? 'Sisa (piutang)' : 'Sisa tagihan'} value={rupiah(p.remaining)} tone={p.remaining > 0 ? 'amber' : 'green'} />
      </div>
      <div className="mt-2 rounded-lg bg-white px-3 py-2">
        <Row label="Deposit ditahan" value={rupiah(p.deposit_held)} tone="muted" />
        <Row label="Deposit dikembalikan" value={rupiah(p.deposit_refund)} tone={p.deposit_refund > 0 ? 'green' : 'muted'} />
      </div>
    </div>
  )
}

export function ReturnModal({ rental, open, onClose, onDone }: { rental: Rental; open: boolean; onClose: () => void; onDone: (r: Rental) => void }) {
  const toast = useToast()
  const complete = useCompleteReturn(rental.id)
  const startOdo = rental.inspections?.serah_terima?.odometer ?? rental.car.odometer
  const [step, setStep] = useState<'form' | 'review'>('form')
  const [inspection, setInspection] = useState<HandoverValue>(() => ({
    odometer: null, fuel_level: rental.inspections?.serah_terima?.fuel_level ?? 100,
    checklist: (rental.inspections?.serah_terima?.checklist?.length ? rental.inspections.serah_terima.checklist.map((c) => c.item) : DEFAULT_CHECKLIST).map((item) => ({ item, ok: true, note: '' })),
    notes: '', photos: [],
  }))
  const [inspErrors, setInspErrors] = useState<Record<string, string>>({})
  const [serverError, setServerError] = useState<string | null>(null)
  const [busy, setBusy] = useState(false)
  // true hanya bila PENGGUNA mengubah potongan deposit; selama false, ikuti saran server (maksimum).
  const [depositTouched, setDepositTouched] = useState(false)

  const { register, control, handleSubmit, setValue, setError, formState: { errors } } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      returned_at: toWibInput(new Date()),
      fees: [],
      deposit_deduction: null,
      payment_amount: null,
      payment_method: 'tunai',
      payment_reference: '',
      refund_method: 'tunai',
      car_status: 'auto',
    },
  })
  const fees = useFieldArray({ control, name: 'fees' })
  const v = useWatch({ control })

  useEffect(() => { if (!open) setStep('form') }, [open])

  const input = useMemo<ReturnInput>(() => ({
    returned_at: v.returned_at ? fromWibInput(v.returned_at) : null,
    odometer: inspection.odometer,
    fuel_level: inspection.fuel_level,
    fees: (v.fees ?? []).filter((f) => (f?.amount ?? 0) > 0).map((f) => ({ type: f!.type!, amount: f!.amount!, reason: f!.reason ?? '' })),
    deposit_deduction: depositTouched ? v.deposit_deduction ?? 0 : null,
    payment: (v.payment_amount ?? 0) > 0 ? { amount: v.payment_amount!, method: v.payment_method!, reference_number: v.payment_reference || null } : null,
    refund_method: v.refund_method,
    car_status: v.car_status === 'auto' ? null : v.car_status,
  }), [v, inspection.odometer, inspection.fuel_level, depositTouched])
  const debounced = useDebounce(input, 350)
  const preview = useReturnPreview(rental.id, open ? debounced : null)
  const p = preview.data

  // Saran awal: potong deposit sebesar maksimum & lunasi sisanya, selama pengguna belum mengubah.
  useEffect(() => {
    if (p && !depositTouched) setValue('deposit_deduction', p.deposit_deduction)
  }, [p, depositTouched, setValue])

  const previewErrors = p ? Object.entries(p.errors) : []
  const carStatus = v.car_status === 'auto' ? p?.car_status_suggested : v.car_status

  const toReview = handleSubmit(() => {
    setServerError(null)
    if (inspection.odometer === null) { setInspErrors({ odometer: 'Isi odometer akhir.' }); return }
    if (inspection.odometer < startOdo) { setInspErrors({ odometer: `Tidak boleh di bawah odometer serah terima (${number(startOdo)} km).` }); return }
    setInspErrors({})
    if (previewErrors.length) return
    setStep('review')
  })

  const confirm = async () => {
    setBusy(true)
    setServerError(null)
    try {
      const res = await complete.mutateAsync({
        ...input,
        deposit_deduction: depositTouched ? v.deposit_deduction ?? 0 : p?.deposit_deduction ?? 0,
        checklist: inspection.checklist.map((c) => ({ item: c.item, ok: c.ok, note: c.ok ? null : c.note || null })),
        notes: inspection.notes || null,
      })
      let rentalDone = res.data
      if (inspection.photos.length) {
        try { rentalDone = (await uploadInspectionPhotos(rental.id, 'kembali', inspection.photos)).data } catch (e) { toast(`Data tersimpan, tetapi foto gagal diunggah: ${errorMessage(e)}`, 'error') }
      }
      toast(res.message)
      onDone(rentalDone)
      onClose()
    } catch (e) {
      const fe = fieldErrors(e)
      for (const [k, m] of Object.entries(fe)) {
        if (k === 'odometer') setInspErrors({ odometer: m })
        const fee = /^fees\.(\d+)\.reason$/.exec(k)
        if (fee) setError(`fees.${Number(fee[1])}.reason`, { message: m })
      }
      setServerError(errorMessage(e))
      setStep('form')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal
      open={open}
      onClose={onClose}
      dismissible={!busy}
      size="xl"
      title={step === 'form' ? `Kembali · ${rental.code}` : `Tinjau ringkasan · ${rental.code}`}
      description={`${rental.car.name} · ${rental.car.plate_number} · ${rental.customer.name}`}
      footer={step === 'form' ? (
        <>
          <Button variant="secondary" onClick={onClose}>Batal</Button>
          <Button onClick={toReview} disabled={!p || preview.isFetching}>Tinjau ringkasan</Button>
        </>
      ) : (
        <>
          <Button variant="secondary" onClick={() => setStep('form')} disabled={busy}><ArrowLeft className="size-4" /> Ubah data</Button>
          <Button onClick={confirm} loading={busy}><CheckCircle2 className="size-4" /> Konfirmasi selesai</Button>
        </>
      )}
    >
      {step === 'form' ? (
        <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
          <div className="space-y-6">
            {serverError && <Alert>{serverError}</Alert>}
            <FormSection title="Waktu & kondisi kembali">
              <Field label="Waktu kembali (WIB)" required hint={`Rencana: ${dateTimeWib(rental.planned_end_at)}`} error={errors.returned_at?.message ?? p?.errors.returned_at}>
                {(a) => <Input {...a} {...register('returned_at')} type="datetime-local" invalid={!!errors.returned_at || !!p?.errors.returned_at} />}
              </Field>
              <div className="flex items-end pb-2 text-xs text-ink-muted">
                {p?.distance_km !== null && p?.distance_km !== undefined && <>Jarak tempuh: <strong className="ml-1 text-ink">{number(p.distance_km)} km</strong></>}
              </div>
              <div className="sm:col-span-2">
                <HandoverFields value={inspection} onChange={setInspection} minOdometer={startOdo} errors={inspErrors} odometerLabel="Odometer akhir (km)" photoLabel="Foto kondisi saat kembali (privat)" />
              </div>
            </FormSection>

            <FormSection title="Biaya tambahan" description="Setiap biaya wajib disertai alasan. Biaya kerusakan otomatis memindahkan mobil ke Servis.">
              <div className="space-y-3 sm:col-span-2">
                {fees.fields.length === 0 && <p className="rounded-xl bg-canvas px-4 py-3 text-center text-xs text-ink-muted">Tidak ada biaya tambahan.</p>}
                {fees.fields.map((f, i) => (
                  <div key={f.id} className="grid gap-2 rounded-xl border border-line p-3 sm:grid-cols-[150px_150px_1fr_auto]">
                    <Select aria-label={`Jenis biaya ${i + 1}`} {...register(`fees.${i}.type`)}>{FEE_TYPES.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}</Select>
                    <Controller control={control} name={`fees.${i}.amount`} render={({ field }) => <MoneyInput aria-label={`Jumlah biaya ${i + 1}`} value={field.value} onChange={field.onChange} placeholder="0" />} />
                    <div>
                      <Input aria-label={`Alasan biaya ${i + 1}`} placeholder="Alasan, mis. spion kiri retak" {...register(`fees.${i}.reason`)} invalid={!!errors.fees?.[i]?.reason} />
                      {errors.fees?.[i]?.reason && <p className="mt-1 text-2xs font-medium text-red-600">{errors.fees[i]?.reason?.message}</p>}
                    </div>
                    <button type="button" onClick={() => fees.remove(i)} className="grid size-10 place-items-center rounded-lg text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label={`Hapus biaya ${i + 1}`}><Trash2 className="size-4" /></button>
                  </div>
                ))}
                <Button size="sm" variant="secondary" onClick={() => fees.append({ type: 'bbm', amount: null, reason: '' })}><Plus className="size-3.5" /> Tambah biaya</Button>
              </div>
            </FormSection>

            <FormSection title="Penyelesaian pembayaran & deposit">
              <Field label={<>Potong dari deposit{depositTouched && <button type="button" className="ml-2 text-2xs font-semibold text-brand-600 hover:underline" onClick={() => setDepositTouched(false)}>pakai saran</button>}</>} hint={p ? `Maks. ${rupiah(p.deposit_deduction_max)} · deposit ditahan ${rupiah(p.deposit_held)}` : undefined} error={errors.deposit_deduction?.message ?? p?.errors.deposit_deduction}>
                {(a) => <Controller control={control} name="deposit_deduction" render={({ field }) => <MoneyInput {...a} value={field.value} onChange={(x) => { setDepositTouched(true); field.onChange(x ?? 0) }} invalid={!!p?.errors.deposit_deduction} />} />}
              </Field>
              <Field label="Metode pengembalian deposit" hint={p ? `Dikembalikan ${rupiah(p.deposit_refund)}` : undefined}>
                {(a) => <Select {...a} {...register('refund_method')} disabled={!p || p.deposit_refund <= 0}>{METHODS.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}</Select>}
              </Field>
              <Field label="Pembayaran sekarang" hint={p ? `Sisa setelah deposit ${rupiah(p.balance_before - p.deposit_deduction)}` : undefined} error={p?.errors['payment.amount']}>
                {(a) => <Controller control={control} name="payment_amount" render={({ field }) => <MoneyInput {...a} value={field.value} onChange={field.onChange} placeholder="0" invalid={!!p?.errors['payment.amount']} />} />}
              </Field>
              <div className="grid grid-cols-2 gap-3">
                <Field label="Metode">{(a) => <Select {...a} {...register('payment_method')}>{METHODS.map((m) => <option key={m.value} value={m.value}>{m.label}</option>)}</Select>}</Field>
                <Field label="No. referensi">{(a) => <Input {...a} {...register('payment_reference')} />}</Field>
              </div>
              {p && p.balance_before - p.deposit_deduction > 0 && (
                <div className="sm:col-span-2">
                  <Button size="sm" variant="ghost" onClick={() => setValue('payment_amount', p.balance_before - p.deposit_deduction, { shouldDirty: true })}>Isi pelunasan penuh ({rupiah(p.balance_before - p.deposit_deduction)})</Button>
                </div>
              )}
              <Field label="Status mobil setelah kembali" hint={p ? `Saran sistem: ${p.car_status_suggested === 'servis' ? 'Servis (ada biaya kerusakan)' : 'Tersedia'}` : undefined}>
                {(a) => (
                  <Select {...a} {...register('car_status')}>
                    <option value="auto">Otomatis{p ? ` (${p.car_status_suggested === 'servis' ? 'Servis' : 'Tersedia'})` : ''}</option>
                    <option value="tersedia">Tersedia</option>
                    <option value="servis">Servis</option>
                  </Select>
                )}
              </Field>
            </FormSection>
          </div>

          <aside className="lg:sticky lg:top-0 lg:self-start">
            <div className="rounded-2xl border border-line bg-canvas/70 p-4">
              <p className="eyebrow mb-3">Ringkasan akhir</p>
              {p ? <div className={cn(preview.isFetching && 'opacity-70')} aria-live="polite"><Summary p={p} rental={rental} /></div> : <p className="text-sm text-ink-muted">Menghitung…</p>}
            </div>
            {previewErrors.filter(([k]) => !k.startsWith('fees')).map(([k, m]) => <div key={k} className="mt-3"><Alert>{m}</Alert></div>)}
            {p?.customer_flag && <div className="mt-3"><Alert tone="yellow">Pelanggan akan otomatis ditandai <strong>Perhatian</strong> karena terlambat.</Alert></div>}
            {preview.isError && <div className="mt-3"><Alert>{errorMessage(preview.error)}</Alert></div>}
          </aside>
        </div>
      ) : p && (
        <div className="grid gap-6 md:grid-cols-[1fr_1fr]">
          <div className="rounded-2xl border border-line bg-canvas/70 p-5"><Summary p={p} rental={rental} /></div>
          <div className="space-y-3 text-sm">
            <Alert tone="yellow">
              <span className="flex gap-2"><AlertTriangle className="mt-0.5 size-4 shrink-0" />Periksa kembali. Setelah dikonfirmasi, sewa ditandai <strong>Selesai</strong> dan tidak bisa diubah dari layar ini.</span>
            </Alert>
            <ul className="space-y-2 rounded-xl border border-line p-4">
              <li className="flex justify-between"><span className="text-ink-muted">Waktu kembali</span><strong>{dateTimeWib(p.returned_at)}</strong></li>
              <li className="flex justify-between"><span className="text-ink-muted">Odometer</span><strong>{number(startOdo)} → {number(inspection.odometer ?? 0)} km</strong></li>
              <li className="flex justify-between"><span className="text-ink-muted">BBM</span><strong>{inspection.fuel_level}%</strong></li>
              <li className="flex justify-between"><span className="text-ink-muted">Kondisi bermasalah</span><strong>{inspection.checklist.filter((c) => !c.ok).length || 'Tidak ada'}</strong></li>
              <li className="flex items-center justify-between"><span className="text-ink-muted">Status mobil</span>{carStatus === 'servis' ? <Badge tone="yellow"><Wrench className="size-3" /> Servis</Badge> : <Badge tone="green">Tersedia</Badge>}</li>
              {p.fees.map((f, i) => <li key={i} className="flex justify-between gap-3"><span className="text-ink-muted">{FEE_TYPES.find((t) => t.value === f.type)?.label}: {f.reason}</span><strong>{rupiah(f.amount)}</strong></li>)}
              {p.customer_flag && <li className="text-xs text-amber-800">Pelanggan → Perhatian (otomatis)</li>}
            </ul>
          </div>
        </div>
      )}
    </Modal>
  )
}
