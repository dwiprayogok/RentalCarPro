import { zodResolver } from '@hookform/resolvers/zod'
import { AlertTriangle, CalendarDays, Info } from 'lucide-react'
import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useCan } from '@/auth/useAuth'
import { RiskBadge, CarStatusBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Combobox } from '@/components/ui/Combobox'
import { Alert } from '@/components/ui/Feedback'
import { Field, FormSection, Input, Select, Switch, Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { MoneyInput } from '@/components/ui/MoneyInput'
import { useToast } from '@/components/ui/Toast'
import { AvailabilityCalendar } from '@/features/cars/AvailabilityCalendar'
import { errorMessage, errorStatus, fieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import { addHoursToWibInput, fromWibInput, humanDuration, nextHalfHourWib } from '@/lib/datetime'
import { applyServerErrors } from '@/lib/form'
import { dateOnly, number, phone, rupiah } from '@/lib/format'
import { P } from '@/lib/permissions'
import type { Car, Customer } from '@/lib/types'
import { useDebounce } from '@/lib/useDebounce'
import { uploadInspectionPhotos, useCreateRental, useQuote, type CreateRentalInput, type PaymentInput, type QuoteInput } from './api'
import { emptyHandover, type HandoverValue } from './handover'
import { HandoverFields } from './HandoverFields'
import { useCarOptions, useCustomerOptions, useDriverOptions, type DriverOption } from './options'
import { newRentalSchema, type NewRentalInput, type NewRentalValues } from './schema'
import type { Rental } from './types'

const FIELDS = ['customer_id', 'car_id', 'service_type', 'driver_id', 'start_at', 'planned_end_at', 'deposit_required', 'discount', 'discount_reason', 'blacklist_override_reason', 'notes'] as const

function defaults(): NewRentalInput {
  const start = nextHalfHourWib(1)
  return {
    customer_id: undefined as unknown as number,
    customer_blacklisted: false,
    car_id: undefined as unknown as number,
    service_type: 'lepas_kunci',
    driver_id: null,
    start_now: false,
    start_at: start,
    planned_end_at: addHoursToWibInput(start, 24),
    deposit_required: null,
    discount: null,
    discount_reason: '',
    blacklist_override_reason: '',
    notes: '',
    dp_amount: null,
    dp_method: 'tunai',
    dp_reference: '',
    deposit_paid: false,
    deposit_method: 'tunai',
    deposit_reference: '',
  }
}

function SummaryRow({ label, value, strong, muted }: { label: ReactNode; value: ReactNode; strong?: boolean; muted?: boolean }) {
  return (
    <div className={cn('flex items-baseline justify-between gap-4', strong ? 'text-base font-extrabold text-ink' : 'text-sm', muted && 'text-ink-muted')}>
      <span>{label}</span>
      <span className="text-right tabular-nums">{value}</span>
    </div>
  )
}

export function NewRentalModal({ open, onClose, onCreated, initialCar }: { open: boolean; onClose: () => void; onCreated: (r: Rental) => void; initialCar?: Car | null }) {
  const can = useCan()
  const toast = useToast()
  const create = useCreateRental()
  const [formError, setFormError] = useState<string | null>(null)
  const [uploading, setUploading] = useState(false)
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [car, setCar] = useState<Car | null>(null)
  const [driver, setDriver] = useState<DriverOption | null>(null)
  const [searches, setSearches] = useState({ customer: '', car: '', driver: '' })
  const [handover, setHandover] = useState<HandoverValue>(emptyHandover(null))
  const [handoverErrors, setHandoverErrors] = useState<Record<string, string>>({})
  const [showCalendar, setShowCalendar] = useState(false)

  const customers = useCustomerOptions(searches.customer)
  const cars = useCarOptions(searches.car)
  const drivers = useDriverOptions(searches.driver)

  const { register, control, handleSubmit, reset, setValue, setError, formState: { errors } } = useForm<NewRentalInput, unknown, NewRentalValues>({
    resolver: zodResolver(newRentalSchema),
    defaultValues: defaults(),
  })

  useEffect(() => {
    if (!open) return
    reset({ ...defaults(), car_id: initialCar?.id as number })
    setCustomer(null)
    setCar(initialCar ?? null)
    setDriver(null)
    setSearches({ customer: '', car: '', driver: '' })
    setHandover(emptyHandover(initialCar?.odometer ?? null))
    setHandoverErrors({})
    setFormError(null)
    setShowCalendar(false)
  }, [open, initialCar, reset])

  const w = useWatch({ control })
  const startNow = !!w.start_now
  const service = w.service_type ?? 'lepas_kunci'

  // Ringkasan biaya real-time dari server (sumber kebenaran harga & bentrok).
  const quoteInput = useMemo<QuoteInput | null>(() => {
    if (!w.car_id || !w.planned_end_at || (!startNow && !w.start_at)) return null
    return {
      customer_id: w.customer_id ?? null,
      car_id: w.car_id,
      service_type: service,
      driver_id: service === 'dengan_driver' ? w.driver_id ?? null : null,
      start_now: startNow,
      start_at: startNow ? null : fromWibInput(w.start_at!),
      planned_end_at: fromWibInput(w.planned_end_at),
      deposit_required: w.deposit_required ?? null,
      discount: w.discount ?? null,
    }
  }, [w.car_id, w.customer_id, w.driver_id, w.planned_end_at, w.start_at, startNow, service, w.deposit_required, w.discount])
  const debouncedQuote = useDebounce(quoteInput, 350)
  const quote = useQuote(debouncedQuote)
  const q = quote.data && debouncedQuote ? quote.data : null

  const dpAmount = w.dp_amount ?? 0
  const depositAmount = w.deposit_required ?? q?.deposit_required ?? 0
  const balance = Math.max(0, (q?.total_amount ?? 0) - dpAmount)
  const blockingError = q && Object.keys(q.errors).length > 0 && !(w.customer_blacklisted && Object.keys(q.errors).length === 1 && q.errors.customer_id && (w.blacklist_override_reason?.length ?? 0) >= 10 && can(P.rentalOverrideBlacklist))

  const busy = create.isPending || uploading

  const onSubmit = handleSubmit(async (v) => {
    setFormError(null)
    setHandoverErrors({})
    if (startNow && (handover.odometer === null || (car && handover.odometer < car.odometer))) {
      setHandoverErrors({ odometer: handover.odometer === null ? 'Isi odometer awal.' : `Tidak boleh di bawah odometer terakhir (${number(car!.odometer)} km).` })
      return
    }

    const payments: PaymentInput[] = []
    if ((v.dp_amount ?? 0) > 0) {
      const full = q && v.dp_amount === q.total_amount
      payments.push({ type: full ? 'pelunasan' : 'dp', method: v.dp_method, amount: v.dp_amount!, reference_number: v.dp_reference || null })
    }
    if (v.deposit_paid && depositAmount > 0) {
      payments.push({ type: 'deposit', method: v.deposit_method, amount: depositAmount, reference_number: v.deposit_reference || null })
    }

    const payload: CreateRentalInput = {
      customer_id: v.customer_id,
      car_id: v.car_id,
      service_type: v.service_type,
      driver_id: v.service_type === 'dengan_driver' ? v.driver_id : null,
      start_now: v.start_now,
      start_at: v.start_now ? null : fromWibInput(v.start_at),
      planned_end_at: fromWibInput(v.planned_end_at),
      deposit_required: v.deposit_required ?? undefined,
      discount: v.discount || undefined,
      discount_reason: v.discount_reason || null,
      blacklist_override_reason: v.customer_blacklisted ? v.blacklist_override_reason : null,
      notes: v.notes || null,
      handover: v.start_now ? {
        odometer: handover.odometer!,
        fuel_level: handover.fuel_level,
        checklist: handover.checklist.map((c) => ({ item: c.item, ok: c.ok, note: c.ok ? null : c.note || null })),
        notes: handover.notes || null,
      } : null,
      payments,
    }

    try {
      const res = await create.mutateAsync(payload)
      let rental = res.data
      if (v.start_now && handover.photos.length) {
        setUploading(true)
        try {
          rental = (await uploadInspectionPhotos(rental.id, 'serah_terima', handover.photos)).data
        } catch (e) {
          toast(`Sewa tersimpan, tetapi foto gagal diunggah: ${errorMessage(e)}`, 'error')
        } finally {
          setUploading(false)
        }
      }
      toast(res.message)
      onCreated(rental)
      onClose()
    } catch (error) {
      const fe = fieldErrors(error)
      const ho: Record<string, string> = {}
      for (const [k, m] of Object.entries(fe)) if (k.startsWith('handover.')) ho[k.slice(9)] = m
      setHandoverErrors(ho)
      const orphan = applyServerErrors(error, setError, FIELDS)
      const paymentErr = Object.entries(fe).find(([k]) => k.startsWith('payments'))?.[1]
      if (errorStatus(error) !== 422 || (orphan && !Object.keys(ho).length)) setFormError(paymentErr ?? orphan ?? errorMessage(error))
    }
  })

  const blacklisted = customer?.risk_status === 'blacklist'
  const canOverride = can(P.rentalOverrideBlacklist)

  return (
    <Modal
      open={open}
      onClose={onClose}
      dismissible={!busy}
      size="xl"
      title="Sewa Baru"
      description="Ringkasan biaya dihitung otomatis oleh server berdasarkan tarif, durasi, dan toleransi."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>Batal</Button>
          <Button type="submit" form="rental-form" loading={busy} disabled={!!blockingError || (blacklisted && !canOverride)}>
            {startNow ? 'Simpan & serahkan mobil' : 'Simpan booking'}
          </Button>
        </>
      }
    >
      <form id="rental-form" onSubmit={onSubmit} noValidate className="grid gap-6 lg:grid-cols-[1fr_340px]">
        <div className="space-y-6">
          {formError && <Alert>{formError}</Alert>}

          <FormSection title="Pelanggan & layanan">
            <Field label="Pelanggan" required className="sm:col-span-2" error={errors.customer_id?.message ?? q?.errors.customer_id}>
              {(a) => (
                <Combobox<Customer>
                  {...a}
                  value={customer}
                  onChange={(c) => {
                    setCustomer(c)
                    setValue('customer_id', c?.id as number, { shouldValidate: !!errors.customer_id })
                    setValue('customer_blacklisted', c?.risk_status === 'blacklist')
                  }}
                  items={customers.data ?? []}
                  loading={customers.isFetching && !customers.data}
                  search={searches.customer}
                  onSearch={(s) => setSearches((x) => ({ ...x, customer: s }))}
                  getKey={(c) => c.id}
                  getLabel={(c) => `${c.name} · ${phone(c.phone)}`}
                  renderItem={(c) => (
                    <div className="flex items-center justify-between gap-2">
                      <span className="min-w-0">
                        <span className="block truncate font-semibold text-ink">{c.name}</span>
                        <span className="block text-2xs text-ink-muted">{phone(c.phone)} · KTP {c.nik_masked.slice(-4)}{!c.self_drive_ready && ' · data lepas kunci belum lengkap'}</span>
                      </span>
                      <RiskBadge status={c.risk_status} label={c.risk_status_label} />
                    </div>
                  )}
                  placeholder="Cari nama, nomor HP, atau KTP…"
                  invalid={!!errors.customer_id || !!q?.errors.customer_id}
                />
              )}
            </Field>

            {customer?.risk_status === 'perhatian' && (
              <div className="sm:col-span-2"><Alert tone="yellow"><strong>Perhatian:</strong> {customer.risk_note}</Alert></div>
            )}
            {blacklisted && (
              <div className="space-y-3 sm:col-span-2">
                <Alert>
                  <strong>Pelanggan Blacklist.</strong> {customer?.risk_note}{' '}
                  {canOverride ? 'Sebagai pemilik, Anda dapat memberi pengecualian dengan alasan yang tercatat.' : 'Hanya pemilik yang dapat memberi pengecualian.'}
                </Alert>
                {canOverride && (
                  <Field label="Alasan pengecualian blacklist" required error={errors.blacklist_override_reason?.message}>
                    {(a) => <Textarea {...a} {...register('blacklist_override_reason')} rows={2} invalid={!!errors.blacklist_override_reason} />}
                  </Field>
                )}
              </div>
            )}

            <div className="sm:col-span-2">
              <p className="mb-1.5 text-xs font-semibold text-ink-soft">Layanan</p>
              <Controller control={control} name="service_type" render={({ field }) => (
                <div role="radiogroup" aria-label="Layanan" className="grid grid-cols-2 gap-2">
                  {([['lepas_kunci', 'Lepas Kunci', 'Pelanggan menyetir sendiri'], ['dengan_driver', 'Dengan Driver', 'Tarif total termasuk driver']] as const).map(([value, label, desc]) => (
                    <button
                      key={value}
                      type="button"
                      role="radio"
                      aria-checked={field.value === value}
                      onClick={() => field.onChange(value)}
                      className={cn('rounded-xl border px-4 py-3 text-left', field.value === value ? 'border-brand-500 bg-brand-50 ring-1 ring-brand-500' : 'border-line-strong hover:border-brand-300')}
                    >
                      <span className="block text-sm font-bold text-ink">{label}</span>
                      <span className="block text-2xs text-ink-muted">{desc}</span>
                    </button>
                  ))}
                </div>
              )} />
            </div>
          </FormSection>

          <FormSection title="Mobil & driver">
            <Field label="Mobil" required className={service === 'dengan_driver' ? '' : 'sm:col-span-2'} error={errors.car_id?.message ?? q?.errors.car_id}>
              {(a) => (
                <Combobox<Car>
                  {...a}
                  value={car}
                  onChange={(c) => {
                    setCar(c)
                    setValue('car_id', c?.id as number, { shouldValidate: !!errors.car_id })
                    setHandover((h) => ({ ...h, odometer: c?.odometer ?? null }))
                  }}
                  items={cars.data ?? []}
                  loading={cars.isFetching && !cars.data}
                  search={searches.car}
                  onSearch={(s) => setSearches((x) => ({ ...x, car: s }))}
                  getKey={(c) => c.id}
                  getLabel={(c) => `${c.name} · ${c.plate_number}`}
                  isItemDisabled={(c) => c.status === 'nonaktif'}
                  renderItem={(c) => (
                    <div className="flex items-center justify-between gap-2">
                      <span className="min-w-0">
                        <span className="block truncate font-semibold text-ink">{c.name} <span className="font-normal text-ink-muted">· {c.plate_number}</span></span>
                        <span className="block text-2xs text-ink-muted">{rupiah(c.rate_self_drive)} lepas kunci · {rupiah(c.rate_with_driver)} dengan driver</span>
                      </span>
                      <CarStatusBadge status={c.status} label={c.status_label} />
                    </div>
                  )}
                  placeholder="Cari merk atau plat…"
                  invalid={!!errors.car_id || !!q?.errors.car_id}
                />
              )}
            </Field>
            {service === 'dengan_driver' && (
              <Field label="Driver" required error={errors.driver_id?.message ?? q?.errors.driver_id}>
                {(a) => (
                  <Combobox<DriverOption>
                    {...a}
                    value={driver}
                    onChange={(d) => { setDriver(d); setValue('driver_id', d?.id ?? null, { shouldValidate: !!errors.driver_id }) }}
                    items={drivers.data ?? []}
                    loading={drivers.isFetching && !drivers.data}
                    search={searches.driver}
                    onSearch={(s) => setSearches((x) => ({ ...x, driver: s }))}
                    getKey={(d) => d.id}
                    getLabel={(d) => d.name}
                    isItemDisabled={(d) => d.license_expired}
                    renderItem={(d) => (
                      <span>
                        <span className="block font-semibold text-ink">{d.name}</span>
                        <span className="block text-2xs text-ink-muted">{phone(d.phone)}{d.license_expired && ' · SIM kedaluwarsa'}</span>
                      </span>
                    )}
                    placeholder="Pilih driver…"
                    emptyText="Belum ada driver aktif."
                    invalid={!!errors.driver_id || !!q?.errors.driver_id}
                  />
                )}
              </Field>
            )}
            {car && (
              <div className="sm:col-span-2">
                <button type="button" onClick={() => setShowCalendar((s) => !s)} className="inline-flex items-center gap-1.5 text-xs font-semibold text-brand-600 hover:underline">
                  <CalendarDays className="size-4" /> {showCalendar ? 'Sembunyikan kalender' : 'Lihat kalender ketersediaan'}
                </button>
                {showCalendar && <div className="mt-3 rounded-xl border border-line p-3"><AvailabilityCalendar carId={car.id} compact /></div>}
              </div>
            )}
          </FormSection>

          <FormSection title="Jadwal" description="Semua waktu dalam WIB. Durasi dibulatkan per 24 jam setelah masa toleransi.">
            <div className="sm:col-span-2">
              <Controller control={control} name="start_now" render={({ field }) => (
                <Switch checked={field.value} onChange={field.onChange} label="Mulai sekarang (serah terima langsung)" description="Sewa langsung Aktif dan checklist serah terima diisi sekarang." />
              )} />
            </div>
            {!startNow && (
              <Field label="Mulai" required error={errors.start_at?.message}>
                {(a) => <Input {...a} {...register('start_at')} type="datetime-local" step={900} invalid={!!errors.start_at} />}
              </Field>
            )}
            <Field label="Kembali rencana" required className={startNow ? 'sm:col-span-2' : ''} error={errors.planned_end_at?.message} hint={q?.pricing ? `Durasi ${humanDuration(q.pricing.duration_minutes)}` : undefined}>
              {(a) => <Input {...a} {...register('planned_end_at')} type="datetime-local" step={900} invalid={!!errors.planned_end_at} />}
            </Field>
          </FormSection>

          <FormSection title="Deposit, diskon & pembayaran awal">
            <Field label="Deposit / jaminan" hint="Dana titipan, dikembalikan saat Kembali." error={errors.deposit_required?.message}>
              {(a) => (
                <Controller control={control} name="deposit_required" render={({ field }) => (
                  <MoneyInput {...a} value={field.value ?? q?.deposit_required ?? null} onChange={field.onChange} placeholder="0" />
                )} />
              )}
            </Field>
            <div className="flex items-end pb-2">
              <Controller control={control} name="deposit_paid" render={({ field }) => (
                <Switch checked={field.value} onChange={field.onChange} label="Deposit diterima sekarang" disabled={depositAmount <= 0} />
              )} />
            </div>
            {w.deposit_paid && depositAmount > 0 && (
              <>
                <Field label="Metode deposit">
                  {(a) => <Select {...a} {...register('deposit_method')}><option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></Select>}
                </Field>
                <Field label="No. referensi deposit" error={errors.deposit_reference?.message}>
                  {(a) => <Input {...a} {...register('deposit_reference')} invalid={!!errors.deposit_reference} />}
                </Field>
              </>
            )}

            <Field label="DP / pembayaran awal" hint={q ? `Maks. ${rupiah(q.total_amount)}` : undefined} error={errors.dp_amount?.message}>
              {(a) => (
                <Controller control={control} name="dp_amount" render={({ field }) => (
                  <MoneyInput {...a} value={field.value} onChange={(v) => field.onChange(v !== null && q ? Math.min(v, q.total_amount) : v)} placeholder="0" />
                )} />
              )}
            </Field>
            <div className="grid grid-cols-2 gap-3">
              <Field label="Metode">
                {(a) => <Select {...a} {...register('dp_method')}><option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></Select>}
              </Field>
              <Field label="No. referensi" error={errors.dp_reference?.message}>
                {(a) => <Input {...a} {...register('dp_reference')} invalid={!!errors.dp_reference} />}
              </Field>
            </div>

            {can(P.rentalDiscount) && (
              <>
                <Field label="Diskon" error={errors.discount?.message}>
                  {(a) => (
                    <Controller control={control} name="discount" render={({ field }) => (
                      <MoneyInput {...a} value={field.value} onChange={field.onChange} placeholder="0" />
                    )} />
                  )}
                </Field>
                <Field label="Alasan diskon" required={(w.discount ?? 0) > 0} error={errors.discount_reason?.message}>
                  {(a) => <Input {...a} {...register('discount_reason')} invalid={!!errors.discount_reason} />}
                </Field>
              </>
            )}
            <Field label="Catatan" className="sm:col-span-2">
              {(a) => <Textarea {...a} {...register('notes')} rows={2} />}
            </Field>
          </FormSection>

          {startNow && (
            <FormSection title="Checklist serah terima" description="Kondisi mobil saat diserahkan ke pelanggan.">
              <div className="sm:col-span-2">
                <HandoverFields value={handover} onChange={setHandover} minOdometer={car?.odometer} errors={handoverErrors} />
              </div>
            </FormSection>
          )}
        </div>

        {/* Ringkasan biaya real-time */}
        <aside className="lg:sticky lg:top-0 lg:self-start">
          <div className="rounded-2xl border border-line bg-canvas/70 p-4">
            <p className="eyebrow">Ringkasan biaya</p>
            {!q ? (
              <p className="mt-3 flex gap-2 text-sm text-ink-muted"><Info className="mt-0.5 size-4 shrink-0" /> Pilih mobil dan jadwal untuk melihat perhitungan.</p>
            ) : (
              <div className={cn('mt-3 space-y-2', quote.isFetching && 'opacity-70')} aria-live="polite">
                {q.pricing && (
                  <>
                    <SummaryRow label={`${q.pricing.billed_days} hari × ${rupiah(q.daily_rate)}`} value={rupiah(q.pricing.billed_days * q.daily_rate)} muted={q.pricing.lines.some((l) => l.special)} />
                    {q.pricing.lines.some((l) => l.special) && (
                      <ul className="space-y-0.5 rounded-lg bg-white px-3 py-2 text-2xs text-ink-muted">
                        {q.pricing.lines.map((l) => <li key={l.date} className="flex justify-between"><span>{dateOnly(l.date)}{l.special && ' · akhir pekan/libur'}</span><span>{rupiah(l.rate)}</span></li>)}
                      </ul>
                    )}
                    <SummaryRow label="Sewa" value={rupiah(q.rental_amount)} />
                  </>
                )}
                {q.discount > 0 && <SummaryRow label="Diskon" value={`− ${rupiah(q.discount)}`} />}
                <div className="border-t border-dashed border-line-strong pt-2"><SummaryRow strong label="Total tagihan" value={rupiah(q.total_amount)} /></div>
                <SummaryRow label="Dibayar sekarang" value={rupiah(dpAmount)} muted />
                <SummaryRow label="Sisa tagihan" value={<span className={balance > 0 ? 'font-bold text-amber-700' : 'font-bold text-brand-700'}>{rupiah(balance)}</span>} />
                <div className="border-t border-line pt-2">
                  <SummaryRow label="Deposit (terpisah)" value={rupiah(depositAmount)} muted />
                  {w.deposit_paid && depositAmount > 0 && <p className="text-right text-2xs text-brand-700">diterima sekarang</p>}
                </div>
                {q.pricing && <p className="pt-1 text-2xs text-ink-muted">Toleransi {q.pricing.grace_minutes / 60} jam. Jeda antar sewa {q.buffer_minutes / 60} jam.</p>}
              </div>
            )}
          </div>

          {q && Object.keys(q.errors).length > 0 && (
            <div className="mt-3 space-y-2">
              {Object.entries(q.errors).map(([k, m]) => <Alert key={k}><span className="flex gap-2"><AlertTriangle className="mt-0.5 size-4 shrink-0" />{m}</span></Alert>)}
            </div>
          )}
          {q && q.warnings.length > 0 && <div className="mt-3 space-y-2">{q.warnings.map((m) => <Alert key={m} tone="yellow">{m}</Alert>)}</div>}
          {quote.isError && <div className="mt-3"><Alert>{errorMessage(quote.error)}</Alert></div>}
        </aside>
      </form>
    </Modal>
  )
}
