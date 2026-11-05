import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useState } from 'react'
import { Controller, useForm } from 'react-hook-form'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, FormSection, Input, Select, Switch, Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { MoneyInput } from '@/components/ui/MoneyInput'
import { useToast } from '@/components/ui/Toast'
import { useCan } from '@/auth/useAuth'
import { errorMessage, errorStatus } from '@/lib/api'
import { applyServerErrors } from '@/lib/form'
import { P } from '@/lib/permissions'
import type { Car } from '@/lib/types'
import { useOwners } from '@/features/owners/api'
import { useSaveCar, type CarPayload } from './api'
import { carSchema, type CarFormInput, type CarFormValues } from './schema'

const FIELDS = [
  'brand', 'model', 'plate_number', 'transmission', 'year', 'color', 'seats', 'fuel_type', 'rate_self_drive', 'rate_with_driver',
  'weekend_rate_self_drive', 'weekend_rate_with_driver', 'default_deposit', 'odometer', 'status', 'tax_due_date', 'stnk_due_date',
  'next_service_date', 'next_service_odometer', 'slug', 'is_published', 'description', 'notes', 'owner_id',
] as const

function toForm(car?: Car | null): CarFormInput {
  return {
    brand: car?.brand ?? '',
    model: car?.model ?? '',
    plate_number: car?.plate_number ?? '',
    transmission: car?.transmission ?? 'matic',
    year: car?.year ?? new Date().getFullYear(),
    color: car?.color ?? '',
    seats: car?.seats ?? 5,
    fuel_type: car?.fuel_type ?? '',
    rate_self_drive: car?.rate_self_drive ?? (null as unknown as number),
    rate_with_driver: car?.rate_with_driver ?? (null as unknown as number),
    weekend_rate_self_drive: car?.weekend_rate_self_drive ?? null,
    weekend_rate_with_driver: car?.weekend_rate_with_driver ?? null,
    default_deposit: car?.default_deposit ?? null,
    odometer: car?.odometer ?? 0,
    status: car?.status ?? 'tersedia',
    tax_due_date: car?.tax_due_date ?? '',
    stnk_due_date: car?.stnk_due_date ?? '',
    next_service_date: car?.next_service_date ?? '',
    next_service_odometer: car?.next_service_odometer ?? null,
    slug: car?.slug ?? '',
    is_published: car?.is_published ?? false,
    description: car?.description ?? '',
    features_text: (car?.features ?? []).join(', '),
    notes: car?.notes ?? '',
    owner_id: car?.owner_id ? String(car.owner_id) : '',
  }
}

function toPayload(v: CarFormValues): CarPayload {
  const blank = (s: string) => (s.trim() === '' ? null : s.trim())
  return {
    brand: v.brand,
    model: v.model,
    plate_number: v.plate_number,
    transmission: v.transmission,
    year: v.year,
    color: blank(v.color),
    seats: v.seats,
    fuel_type: blank(v.fuel_type),
    rate_self_drive: v.rate_self_drive,
    rate_with_driver: v.rate_with_driver,
    weekend_rate_self_drive: v.weekend_rate_self_drive,
    weekend_rate_with_driver: v.weekend_rate_with_driver,
    default_deposit: v.default_deposit ?? 0,
    odometer: v.odometer,
    status: v.status,
    tax_due_date: blank(v.tax_due_date),
    stnk_due_date: blank(v.stnk_due_date),
    next_service_date: blank(v.next_service_date),
    next_service_odometer: v.next_service_odometer,
    service_interval_km: null,
    slug: blank(v.slug),
    is_published: v.is_published,
    description: blank(v.description),
    features: v.features_text.split(',').map((f) => f.trim()).filter(Boolean),
    notes: blank(v.notes),
    owner_id: v.owner_id ? Number(v.owner_id) : null,
  }
}

export function CarFormModal({ open, car, onClose, onSaved }: { open: boolean; car?: Car | null; onClose: () => void; onSaved?: (car: Car) => void }) {
  const isEdit = !!car
  const save = useSaveCar()
  const toast = useToast()
  const can = useCan()
  const [formError, setFormError] = useState<string | null>(null)
  const [showAdvanced, setShowAdvanced] = useState(false)
  const owners = useOwners('', '', open && can(P.carOwnerView))

  const { register, control, handleSubmit, reset, setError, formState: { errors } } = useForm<CarFormInput, unknown, CarFormValues>({
    resolver: zodResolver(carSchema),
    defaultValues: toForm(car),
  })

  useEffect(() => {
    if (open) {
      reset(toForm(car))
      setFormError(null)
      setShowAdvanced(!!(car?.weekend_rate_self_drive || car?.weekend_rate_with_driver))
    }
  }, [open, car, reset])

  const onSubmit = handleSubmit((values) => {
    setFormError(null)
    save.mutate({ id: car?.id, payload: toPayload(values) }, {
      onSuccess: ({ data, message }) => {
        toast(message)
        onSaved?.(data)
        onClose()
      },
      onError: (error) => {
        const orphan = applyServerErrors(error, setError, [...FIELDS, 'features'])
        if (errorStatus(error) !== 422 || orphan) setFormError(orphan ?? errorMessage(error))
      },
    })
  })

  const canPublish = car ? car.can.publish : can(P.carPublish)
  const lockedStatus = car?.status === 'disewa'

  return (
    <Modal
      open={open}
      onClose={onClose}
      dismissible={!save.isPending}
      size="lg"
      title={isEdit ? 'Edit armada' : 'Tambah armada'}
      description="Tarif dengan driver adalah total per hari, termasuk driver."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={save.isPending}>Batal</Button>
          <Button type="submit" form="car-form" loading={save.isPending}>Simpan data</Button>
        </>
      }
    >
      <form id="car-form" onSubmit={onSubmit} noValidate className="space-y-6">
        {formError && <Alert>{formError}</Alert>}

        <FormSection title="Identitas kendaraan">
          <Field label="Merk" required error={errors.brand?.message}>
            {(a) => <Input {...a} {...register('brand')} placeholder="Toyota" invalid={!!errors.brand} />}
          </Field>
          <Field label="Tipe" required error={errors.model?.message}>
            {(a) => <Input {...a} {...register('model')} placeholder="Innova Reborn" invalid={!!errors.model} />}
          </Field>
          <Field label="Plat nomor" required hint="Contoh: F 1576 FGH" error={errors.plate_number?.message}>
            {(a) => <Input {...a} {...register('plate_number')} className="uppercase" autoCapitalize="characters" invalid={!!errors.plate_number} />}
          </Field>
          <Field label="Tahun" required error={errors.year?.message}>
            {(a) => <Input {...a} {...register('year', { valueAsNumber: true })} type="number" inputMode="numeric" invalid={!!errors.year} />}
          </Field>
          <Field label="Transmisi" error={errors.transmission?.message}>
            {(a) => (
              <Select {...a} {...register('transmission')}>
                <option value="matic">Matic</option>
                <option value="manual">Manual</option>
              </Select>
            )}
          </Field>
          <Field label="Warna" error={errors.color?.message}>
            {(a) => <Input {...a} {...register('color')} placeholder="Hitam" />}
          </Field>
          <Field label="Kapasitas kursi" error={errors.seats?.message}>
            {(a) => <Input {...a} {...register('seats', { valueAsNumber: true })} type="number" inputMode="numeric" invalid={!!errors.seats} />}
          </Field>
          <Field label="Bahan bakar" error={errors.fuel_type?.message}>
            {(a) => <Input {...a} {...register('fuel_type')} placeholder="Bensin / Solar / Listrik" />}
          </Field>
        </FormSection>

        <FormSection title="Tarif & deposit" description="Semua nominal dalam rupiah per hari (24 jam).">
          <Field label="Tarif lepas kunci / hari" required error={errors.rate_self_drive?.message}>
            {(a) => (
              <Controller control={control} name="rate_self_drive" render={({ field }) => (
                <MoneyInput {...a} value={field.value} onChange={field.onChange} onBlur={field.onBlur} invalid={!!errors.rate_self_drive} />
              )} />
            )}
          </Field>
          <Field label="Tarif total dengan driver / hari" required hint="Total per hari, sudah termasuk driver." error={errors.rate_with_driver?.message}>
            {(a) => (
              <Controller control={control} name="rate_with_driver" render={({ field }) => (
                <MoneyInput {...a} value={field.value} onChange={field.onChange} onBlur={field.onBlur} invalid={!!errors.rate_with_driver} />
              )} />
            )}
          </Field>
          <Field label="Deposit / jaminan" hint="Dana titipan; dikembalikan saat mobil kembali." error={errors.default_deposit?.message}>
            {(a) => (
              <Controller control={control} name="default_deposit" render={({ field }) => (
                <MoneyInput {...a} value={field.value} onChange={field.onChange} onBlur={field.onBlur} placeholder="0" />
              )} />
            )}
          </Field>
          <div className="flex items-end">
            <button type="button" onClick={() => setShowAdvanced((v) => !v)} className="text-xs font-semibold text-brand-600 hover:underline">
              {showAdvanced ? 'Sembunyikan tarif akhir pekan' : '+ Tarif akhir pekan / libur (opsional)'}
            </button>
          </div>
          {showAdvanced && (
            <>
              <Field label="Akhir pekan: lepas kunci" error={errors.weekend_rate_self_drive?.message}>
                {(a) => (
                  <Controller control={control} name="weekend_rate_self_drive" render={({ field }) => (
                    <MoneyInput {...a} value={field.value} onChange={field.onChange} placeholder="Sama dengan tarif biasa" />
                  )} />
                )}
              </Field>
              <Field label="Akhir pekan: dengan driver" error={errors.weekend_rate_with_driver?.message}>
                {(a) => (
                  <Controller control={control} name="weekend_rate_with_driver" render={({ field }) => (
                    <MoneyInput {...a} value={field.value} onChange={field.onChange} placeholder="Sama dengan tarif biasa" />
                  )} />
                )}
              </Field>
            </>
          )}
        </FormSection>

        <FormSection title="Operasional & perawatan">
          <Field label="Odometer terakhir (km)" required error={errors.odometer?.message}>
            {(a) => <Input {...a} {...register('odometer', { valueAsNumber: true })} type="number" inputMode="numeric" min={0} invalid={!!errors.odometer} />}
          </Field>
          <Field label="Status operasional" hint={lockedStatus ? 'Mobil sedang disewa; status berubah otomatis saat kembali.' : '"Disewa" diatur otomatis oleh transaksi.'} error={errors.status?.message}>
            {(a) => (
              <Select {...a} {...register('status')} disabled={lockedStatus}>
                {lockedStatus && <option value="disewa">Disewa</option>}
                <option value="tersedia">Tersedia</option>
                <option value="servis">Servis</option>
                <option value="nonaktif">Nonaktif</option>
              </Select>
            )}
          </Field>
          {can(P.carOwnerView) && (
            <Field label="Kepemilikan" hint="Pilih pemilik bila mobil ini titipan; pendapatannya masuk setoran bagi hasil." error={errors.owner_id?.message}>
              {(a) => (
                <Select {...a} {...register('owner_id')}>
                  <option value="">Milik rental sendiri</option>
                  {car?.owner && !(owners.data?.data ?? []).some((o) => o.id === car.owner?.id) && <option value={car.owner.id}>Titipan · {car.owner.name}</option>}
                  {(owners.data?.data ?? []).filter((o) => o.is_active || o.id === car?.owner_id).map((o) => <option key={o.id} value={o.id}>Titipan · {o.name} ({o.share_label})</option>)}
                </Select>
              )}
            </Field>
          )}
          <Field label="Jatuh tempo pajak" error={errors.tax_due_date?.message}>
            {(a) => <Input {...a} {...register('tax_due_date')} type="date" />}
          </Field>
          <Field label="Jatuh tempo STNK" error={errors.stnk_due_date?.message}>
            {(a) => <Input {...a} {...register('stnk_due_date')} type="date" />}
          </Field>
          <Field label="Servis berikutnya (tanggal)" error={errors.next_service_date?.message}>
            {(a) => <Input {...a} {...register('next_service_date')} type="date" />}
          </Field>
          <Field label="Servis berikutnya (km)" error={errors.next_service_odometer?.message}>
            {(a) => (
              <Input
                {...a}
                {...register('next_service_odometer', { setValueAs: (v) => (v === '' || v === null ? null : Number(v)) })}
                type="number"
                inputMode="numeric"
                min={0}
              />
            )}
          </Field>
        </FormSection>

        <FormSection title="Landing page" description="Informasi yang tampil ke calon penyewa. Plat nomor & odometer tidak pernah ditampilkan.">
          <div className="sm:col-span-2">
            <Controller control={control} name="is_published" render={({ field }) => (
              <Switch
                checked={field.value}
                onChange={field.onChange}
                disabled={!canPublish}
                label="Tampilkan di landing"
                description={canPublish ? 'Mobil muncul di daftar armada publik.' : 'Hanya pengguna dengan izin publikasi yang dapat mengubah ini.'}
              />
            )} />
            {errors.is_published && <p className="mt-1 text-2xs font-medium text-red-600">{errors.is_published.message}</p>}
          </div>
          <Field label="Slug URL" hint="Kosongkan untuk dibuat otomatis, mis. toyota-innova-2019." error={errors.slug?.message}>
            {(a) => <Input {...a} {...register('slug')} placeholder="otomatis" invalid={!!errors.slug} />}
          </Field>
          <Field label="Fitur unggulan" hint="Pisahkan dengan koma. Maks. 12." error={errors.features_text?.message}>
            {(a) => <Input {...a} {...register('features_text')} placeholder="AC, Bluetooth, 7 kursi" />}
          </Field>
          <Field label="Deskripsi" className="sm:col-span-2" error={errors.description?.message}>
            {(a) => <Textarea {...a} {...register('description')} rows={3} />}
          </Field>
        </FormSection>

        <FormSection title="Catatan internal">
          <Field label="Catatan (tidak tampil publik)" className="sm:col-span-2" error={errors.notes?.message}>
            {(a) => <Textarea {...a} {...register('notes')} rows={2} />}
          </Field>
        </FormSection>
      </form>
    </Modal>
  )
}
