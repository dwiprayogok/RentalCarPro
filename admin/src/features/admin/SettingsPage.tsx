import { zodResolver } from '@hookform/resolvers/zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CalendarPlus, Save, Trash2 } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/Button'
import { Alert, Skeleton } from '@/components/ui/Feedback'
import { Field, FormSection, Input, Select, Switch, Textarea } from '@/components/ui/Field'
import { MoneyInput } from '@/components/ui/MoneyInput'
import { ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { api, errorMessage, fieldErrors } from '@/lib/api'
import { cn } from '@/lib/cn'
import { dateOnly, rupiah } from '@/lib/format'
import { isValidIndonesianPhone } from '@/lib/phone'

interface SettingRow { key: string; group: string; label: string; description: string | null; type: string; value: unknown }

const schema = z.object({
  grace_period_minutes: z.number({ error: 'Isi angka.' }).int().min(0, 'Minimal 0.').max(720, 'Maksimal 720 menit (12 jam).'),
  booking_buffer_minutes: z.number({ error: 'Isi angka.' }).int().min(0, 'Minimal 0.').max(1440, 'Maksimal 1440 menit (24 jam).'),
  late_fee_per_hour: z.number({ error: 'Isi nominal.' }).int().min(0).max(100_000_000),
  late_fee_cap_days: z.number().int().min(1).max(7),
  auto_flag_late_customer: z.boolean(),
  weekend_rate_enabled: z.boolean(),
  weekend_days: z.array(z.number().int().min(1).max(7)),
  reminder_days: z.number({ error: 'Isi angka.' }).int().min(1, 'Minimal 1 hari.').max(365, 'Maksimal 365 hari.'),
  reminder_km: z.number({ error: 'Isi angka.' }).int().min(0).max(20000, 'Maksimal 20.000 km.'),
  default_deposit: z.number().int().min(0).max(100_000_000),
  business_name: z.string().trim().min(2, 'Nama usaha minimal 2 karakter.').max(80),
  business_tagline: z.string().trim().max(120),
  business_address: z.string().trim().max(300),
  business_whatsapp: z.string().trim().refine((v) => v === '' || isValidIndonesianPhone(v), 'Gunakan nomor HP Indonesia, mis. 0812xxxx.'),
  invoice_terms: z.string().max(1500),
  invoice_footer: z.string().trim().max(200),
})
type Values = z.infer<typeof schema>
const KEYS = Object.keys(schema.shape) as (keyof Values)[]
const DAYS = [{ v: 1, l: 'Sen' }, { v: 2, l: 'Sel' }, { v: 3, l: 'Rab' }, { v: 4, l: 'Kam' }, { v: 5, l: 'Jum' }, { v: 6, l: 'Sab' }, { v: 7, l: 'Min' }]

/** Sama dengan PricingService::lateFee di server (untuk pratinjau saja). */
function lateFee(minutes: number, v: Pick<Values, 'grace_period_minutes' | 'late_fee_per_hour' | 'late_fee_cap_days'>, rate: number) {
  if (minutes <= v.grace_period_minutes) return 0
  const days = Math.floor(minutes / 1440)
  const hours = Math.ceil((minutes - days * 1440) / 60)
  return days * rate + Math.min(hours * v.late_fee_per_hour, rate * v.late_fee_cap_days)
}

function Holidays() {
  const qc = useQueryClient()
  const toast = useToast()
  const year = new Date().getFullYear()
  const [form, setForm] = useState({ date: '', name: '' })
  const [err, setErr] = useState<Record<string, string>>({})
  const list = useQuery({ queryKey: ['holidays', year], queryFn: async () => (await api.get<{ data: { id: number; date: string; name: string }[] }>('/holidays', { params: { year } })).data.data })
  const add = useMutation({
    mutationFn: async () => (await api.post<{ message: string }>('/holidays', form)).data,
    onSuccess: (r) => { toast(r.message); setForm({ date: '', name: '' }); setErr({}); qc.invalidateQueries({ queryKey: ['holidays'] }) },
    onError: (e) => { setErr(fieldErrors(e)); if (!Object.keys(fieldErrors(e)).length) toast(errorMessage(e), 'error') },
  })
  const del = useMutation({
    mutationFn: async (id: number) => (await api.delete<{ message: string }>(`/holidays/${id}`)).data,
    onSuccess: (r) => { toast(r.message); qc.invalidateQueries({ queryKey: ['holidays'] }) },
  })

  return (
    <div className="space-y-3 sm:col-span-2">
      <p className="text-xs font-semibold text-ink-soft">Tanggal libur {year} (memakai tarif akhir pekan)</p>
      {list.data && list.data.length > 0 ? (
        <ul className="divide-y divide-line rounded-xl border border-line">
          {list.data.map((h) => (
            <li key={h.id} className="flex items-center justify-between px-3 py-2 text-sm">
              <span><strong>{dateOnly(h.date)}</strong> · {h.name}</span>
              <button type="button" onClick={() => del.mutate(h.id)} className="rounded p-1 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label={`Hapus libur ${h.name}`}><Trash2 className="size-4" /></button>
            </li>
          ))}
        </ul>
      ) : <p className="rounded-xl bg-canvas px-4 py-3 text-center text-xs text-ink-muted">Belum ada tanggal libur untuk {year}.</p>}
      <div className="grid gap-2 sm:grid-cols-[160px_1fr_auto] sm:items-start">
        <Field label="Tanggal" error={err.date}>{(a) => <Input {...a} type="date" value={form.date} onChange={(e) => setForm((f) => ({ ...f, date: e.target.value }))} invalid={!!err.date} />}</Field>
        <Field label="Nama hari libur" error={err.name}>{(a) => <Input {...a} value={form.name} placeholder="Mis. Hari Natal" onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} invalid={!!err.name} />}</Field>
        <Button variant="secondary" className="sm:mt-[22px]" loading={add.isPending} onClick={() => add.mutate()}><CalendarPlus className="size-4" /> Tambah</Button>
      </div>
    </div>
  )
}

export function SettingsPage() {
  const qc = useQueryClient()
  const toast = useToast()
  const [formError, setFormError] = useState<string | null>(null)
  const query = useQuery({ queryKey: ['settings'], queryFn: async () => (await api.get<{ data: SettingRow[] }>('/settings')).data.data })
  const byKey = useMemo(() => Object.fromEntries((query.data ?? []).map((s) => [s.key, s])), [query.data])

  const { register, control, handleSubmit, reset, setError, formState: { errors, dirtyFields, isDirty } } = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!query.data) return
    const v = Object.fromEntries(query.data.map((s) => [s.key, s.value ?? ''])) as unknown as Values
    reset({ ...v, weekend_days: (v.weekend_days as number[]) ?? [] })
  }, [query.data, reset])

  const save = useMutation({
    mutationFn: async (settings: Partial<Values>) => (await api.put<{ data: SettingRow[]; message: string }>('/settings', { settings })).data,
    onSuccess: (r) => { toast(r.message); qc.setQueryData(['settings'], r.data); qc.invalidateQueries({ queryKey: ['rentals'] }) },
    onError: (e) => {
      const fe = fieldErrors(e)
      const orphan: string[] = []
      for (const [k, m] of Object.entries(fe)) {
        const key = k.replace(/^settings\./, '').replace(/\.\d+$/, '') as keyof Values
        if (KEYS.includes(key)) setError(key, { message: m }); else orphan.push(m)
      }
      setFormError(orphan.join(' ') || (Object.keys(fe).length ? null : errorMessage(e)))
    },
  })

  const onSubmit = handleSubmit((v) => {
    setFormError(null)
    const changed = Object.fromEntries(KEYS.filter((k) => dirtyFields[k]).map((k) => [k, v[k]])) as Partial<Values>
    if (!Object.keys(changed).length) { toast('Tidak ada perubahan.'); return }
    save.mutate(changed)
  })

  const w = useWatch({ control }) as Values
  const label = (k: keyof Values) => byKey[k]?.label ?? k
  const hint = (k: keyof Values) => byKey[k]?.description ?? undefined
  const sampleRate = 550000
  const examples = w && w.late_fee_per_hour !== undefined ? [60, 300, 1800].map((m) => ({ m, fee: lateFee(m, w, sampleRate) })) : []

  if (query.isLoading) return <><PageHeader eyebrow="Sistem" title="Pengaturan" /><Skeleton className="h-96" /></>
  if (query.isError) return <><PageHeader eyebrow="Sistem" title="Pengaturan" /><div className="card"><ErrorState error={query.error} onRetry={() => query.refetch()} /></div></>

  return (
    <form onSubmit={onSubmit} noValidate>
      <PageHeader
        eyebrow="Sistem"
        title="Pengaturan"
        subtitle="Aturan tarif, denda, jadwal, dan identitas usaha. Perubahan berlaku untuk transaksi berikutnya dan tercatat di log aktivitas."
        action={<Button type="submit" loading={save.isPending} disabled={!isDirty}><Save className="size-4" /> Simpan perubahan</Button>}
      />
      {formError && <div className="mb-4"><Alert>{formError}</Alert></div>}

      <div className="grid gap-6 xl:grid-cols-[1fr_320px]">
        <div className="card space-y-6 p-5">
          <FormSection title="Jadwal & toleransi">
            <Field label={label('grace_period_minutes')} hint={`${hint('grace_period_minutes')} (${((w?.grace_period_minutes ?? 0) / 60).toLocaleString('id-ID')} jam)`} error={errors.grace_period_minutes?.message}>
              {(a) => <Input {...a} type="number" min={0} max={720} {...register('grace_period_minutes', { valueAsNumber: true })} invalid={!!errors.grace_period_minutes} />}
            </Field>
            <Field label={label('booking_buffer_minutes')} hint={hint('booking_buffer_minutes')} error={errors.booking_buffer_minutes?.message}>
              {(a) => <Input {...a} type="number" min={0} max={1440} {...register('booking_buffer_minutes', { valueAsNumber: true })} invalid={!!errors.booking_buffer_minutes} />}
            </Field>
          </FormSection>

          <FormSection title="Denda keterlambatan">
            <Field label={label('late_fee_per_hour')} hint={hint('late_fee_per_hour')} error={errors.late_fee_per_hour?.message}>
              {(a) => <Controller control={control} name="late_fee_per_hour" render={({ field }) => <MoneyInput {...a} value={field.value} onChange={(x) => field.onChange(x ?? 0)} invalid={!!errors.late_fee_per_hour} />} />}
            </Field>
            <Field label={label('late_fee_cap_days')} hint="Denda per 24 jam maksimal sebesar tarif harian × nilai ini." error={errors.late_fee_cap_days?.message}>
              {(a) => <Select {...a} {...register('late_fee_cap_days', { valueAsNumber: true })}>{[1, 2, 3, 4, 5, 6, 7].map((n) => <option key={n} value={n}>{n}× tarif harian</option>)}</Select>}
            </Field>
            <div className="sm:col-span-2">
              <Controller control={control} name="auto_flag_late_customer" render={({ field }) => <Switch checked={!!field.value} onChange={field.onChange} label={label('auto_flag_late_customer')} description={hint('auto_flag_late_customer')} />} />
            </div>
          </FormSection>

          <FormSection title="Tarif akhir pekan & hari libur" description="Dipakai bila mobil memiliki tarif akhir pekan sendiri.">
            <div className="sm:col-span-2">
              <Controller control={control} name="weekend_rate_enabled" render={({ field }) => <Switch checked={!!field.value} onChange={field.onChange} label={label('weekend_rate_enabled')} description={hint('weekend_rate_enabled')} />} />
            </div>
            <div className="sm:col-span-2">
              <p className="mb-1.5 text-xs font-semibold text-ink-soft">Hari akhir pekan</p>
              <Controller control={control} name="weekend_days" render={({ field }) => (
                <div className="flex flex-wrap gap-2" role="group" aria-label="Hari akhir pekan">
                  {DAYS.map((d) => {
                    const on = (field.value ?? []).includes(d.v)
                    return (
                      <button key={d.v} type="button" aria-pressed={on} onClick={() => field.onChange(on ? field.value.filter((x: number) => x !== d.v) : [...(field.value ?? []), d.v].sort())}
                        className={cn('h-9 w-12 rounded-lg border text-xs font-semibold', on ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-line-strong text-ink-soft hover:border-brand-300')}>{d.l}</button>
                    )
                  })}
                </div>
              )} />
            </div>
            <Holidays />
          </FormSection>

          <FormSection title="Pengingat & deposit">
            <Field label={label('reminder_days')} hint={hint('reminder_days')} error={errors.reminder_days?.message}>
              {(a) => <Input {...a} type="number" {...register('reminder_days', { valueAsNumber: true })} invalid={!!errors.reminder_days} />}
            </Field>
            <Field label={label('reminder_km')} hint={hint('reminder_km')} error={errors.reminder_km?.message}>
              {(a) => <Input {...a} type="number" {...register('reminder_km', { valueAsNumber: true })} invalid={!!errors.reminder_km} />}
            </Field>
            <Field label={label('default_deposit')} hint={hint('default_deposit')} error={errors.default_deposit?.message}>
              {(a) => <Controller control={control} name="default_deposit" render={({ field }) => <MoneyInput {...a} value={field.value} onChange={(x) => field.onChange(x ?? 0)} />} />}
            </Field>
          </FormSection>

          <FormSection title="Identitas usaha (nota & email)">
            <Field label={label('business_name')} error={errors.business_name?.message}>{(a) => <Input {...a} {...register('business_name')} invalid={!!errors.business_name} />}</Field>
            <Field label={label('business_tagline')} error={errors.business_tagline?.message}>{(a) => <Input {...a} {...register('business_tagline')} />}</Field>
            <Field label={label('business_whatsapp')} hint="Format 08…, 62…, atau +62…" error={errors.business_whatsapp?.message}>{(a) => <Input {...a} type="tel" {...register('business_whatsapp')} invalid={!!errors.business_whatsapp} />}</Field>
            <Field label={label('invoice_footer')} error={errors.invoice_footer?.message}>{(a) => <Input {...a} {...register('invoice_footer')} />}</Field>
            <Field label={label('business_address')} className="sm:col-span-2" error={errors.business_address?.message}>{(a) => <Textarea {...a} rows={2} {...register('business_address')} />}</Field>
            <Field label={label('invoice_terms')} hint={hint('invoice_terms')} className="sm:col-span-2" error={errors.invoice_terms?.message}>{(a) => <Textarea {...a} rows={4} {...register('invoice_terms')} />}</Field>
          </FormSection>
        </div>

        <aside className="space-y-4 xl:sticky xl:top-20 xl:self-start">
          <div className="card p-4">
            <p className="eyebrow mb-2">Pratinjau denda</p>
            <p className="mb-3 text-xs text-ink-muted">Contoh mobil bertarif {rupiah(sampleRate)}/hari dengan pengaturan di samping (belum disimpan).</p>
            <ul className="space-y-1.5 text-sm">
              {examples.map((e) => (
                <li key={e.m} className="flex justify-between gap-3"><span className="text-ink-muted">Terlambat {e.m >= 1440 ? `${Math.floor(e.m / 1440)} hari ${Math.round((e.m % 1440) / 60)} jam` : `${e.m / 60} jam`}</span><strong className="tabular-nums">{rupiah(e.fee)}</strong></li>
              ))}
            </ul>
          </div>
          <div className="card p-4 text-xs text-ink-muted">
            <p className="mb-1 font-semibold text-ink">Catatan</p>
            Harga sewa yang sudah dibuat tidak berubah. Denda final dihitung saat Kembali memakai pengaturan yang berlaku saat itu.
          </div>
        </aside>
      </div>
    </form>
  )
}
