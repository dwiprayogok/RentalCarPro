import { zodResolver } from '@hookform/resolvers/zod'
import { useEffect, useMemo, useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { useCan } from '@/auth/useAuth'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, FormSection, Input, Select, Switch, Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, errorStatus } from '@/lib/api'
import { applyServerErrors } from '@/lib/form'
import { P } from '@/lib/permissions'
import type { Customer } from '@/lib/types'
import { useDeleteCustomerDocument, useSaveCustomer, useUploadCustomerDocument, type CustomerPayload } from './api'
import { DocumentField } from './DocumentField'
import { makeCustomerSchema, type CustomerFormInput, type CustomerFormValues } from './schema'

const FIELDS = [
  'name', 'phone', 'nik', 'for_self_drive', 'sim_number', 'sim_type', 'sim_expires_at', 'address', 'emergency_contact_name',
  'emergency_contact_phone', 'emergency_contact_relation', 'risk_status', 'risk_note', 'notes',
] as const

const SIM_TYPES = ['A', 'A Umum', 'B1', 'B1 Umum', 'B2', 'B2 Umum', 'Internasional']

function toForm(c?: Customer | null): CustomerFormInput {
  return {
    name: c?.name ?? '',
    phone: c?.phone ?? '',
    nik: '',
    // Default: pelanggan baru diasumsikan lepas kunci (data lengkap dikumpulkan di awal).
    for_self_drive: c ? c.self_drive_ready || !!c.sim_number : true,
    sim_number: c?.sim_number ?? '',
    sim_type: c?.sim_type ?? 'A',
    sim_expires_at: c?.sim_expires_at ?? '',
    address: c?.address ?? '',
    emergency_contact_name: c?.emergency_contact_name ?? '',
    emergency_contact_phone: c?.emergency_contact_phone ?? '',
    emergency_contact_relation: c?.emergency_contact_relation ?? '',
    risk_status: c?.risk_status ?? 'lancar',
    risk_note: c?.risk_note ?? '',
    notes: c?.notes ?? '',
  }
}

function toPayload(v: CustomerFormValues): CustomerPayload {
  const blank = (s: string) => (s.trim() === '' ? null : s.trim())
  return {
    name: v.name,
    phone: v.phone,
    nik: blank(v.nik),
    for_self_drive: v.for_self_drive,
    sim_number: blank(v.sim_number),
    sim_type: v.sim_number ? blank(v.sim_type) : null,
    sim_expires_at: blank(v.sim_expires_at),
    address: blank(v.address),
    emergency_contact_name: blank(v.emergency_contact_name),
    emergency_contact_phone: blank(v.emergency_contact_phone),
    emergency_contact_relation: blank(v.emergency_contact_relation),
    risk_status: v.risk_status,
    risk_note: blank(v.risk_note),
    notes: blank(v.notes),
  }
}

type DocType = 'ktp' | 'sim'

export function CustomerFormModal({ open, customer, onClose, prefill, onSaved }: {
  open: boolean
  customer?: Customer | null
  onClose: () => void
  prefill?: { name?: string; phone?: string } | null
  onSaved?: (c: Customer) => void
}) {
  const isEdit = !!customer
  const can = useCan()
  const toast = useToast()
  const save = useSaveCustomer()
  const upload = useUploadCustomerDocument()
  const removeDoc = useDeleteCustomerDocument()
  const [formError, setFormError] = useState<string | null>(null)
  const [files, setFiles] = useState<Record<DocType, File | null>>({ ktp: null, sim: null })
  const [removed, setRemoved] = useState<Record<DocType, boolean>>({ ktp: false, sim: false })

  const schema = useMemo(() => makeCustomerSchema(isEdit), [isEdit])
  const { register, control, handleSubmit, reset, setError, formState: { errors } } = useForm<CustomerFormInput, unknown, CustomerFormValues>({
    resolver: zodResolver(schema),
    defaultValues: toForm(customer),
  })

  useEffect(() => {
    if (open) {
      reset(customer ? toForm(customer) : { ...toForm(null), name: prefill?.name ?? '', phone: prefill?.phone ?? '' })
      setFiles({ ktp: null, sim: null })
      setRemoved({ ktp: false, sim: false })
      setFormError(null)
    }
  }, [open, customer, prefill, reset])

  const selfDrive = useWatch({ control, name: 'for_self_drive' })
  const riskStatus = useWatch({ control, name: 'risk_status' })
  const canChangeRisk = customer ? customer.can.change_risk : can(P.customerChangeRisk)
  const canManageDocs = customer ? customer.can.manage_documents : can(P.customerViewDocuments) && can(P.customerCreate)
  const busy = save.isPending || upload.isPending || removeDoc.isPending

  const onSubmit = handleSubmit(async (values) => {
    setFormError(null)
    let saved: Customer
    let message: string
    try {
      const res = await save.mutateAsync({ id: customer?.id, payload: toPayload(values) })
      saved = res.data
      message = res.message
    } catch (error) {
      const orphan = applyServerErrors(error, setError, FIELDS)
      if (errorStatus(error) !== 422 || orphan) setFormError(orphan ?? errorMessage(error))
      return
    }

    // Dokumen diproses setelah data tersimpan; kegagalan tidak membatalkan data pelanggan.
    const failures: string[] = []
    for (const type of ['ktp', 'sim'] as const) {
      try {
        if (files[type]) await upload.mutateAsync({ id: saved.id, type, file: files[type]! })
        else if (removed[type]) await removeDoc.mutateAsync({ id: saved.id, type })
      } catch (error) {
        failures.push(`${type.toUpperCase()}: ${errorMessage(error)}`)
      }
    }

    if (failures.length) {
      toast(`${message} Namun dokumen gagal diproses. ${failures.join(' ')}`, 'error')
    } else {
      toast(message)
    }
    onSaved?.(saved)
    onClose()
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      dismissible={!busy}
      size="lg"
      title={isEdit ? 'Edit pelanggan' : 'Tambah pelanggan'}
      description="Identitas dan status risiko pelanggan."
      footer={
        <>
          <Button variant="secondary" onClick={onClose} disabled={busy}>Batal</Button>
          <Button type="submit" form="customer-form" loading={busy}>Simpan data</Button>
        </>
      }
    >
      <form id="customer-form" onSubmit={onSubmit} noValidate className="space-y-6">
        {formError && <Alert>{formError}</Alert>}

        <FormSection title="Identitas">
          <Field label="Nama lengkap" required error={errors.name?.message}>
            {(a) => <Input {...a} {...register('name')} autoComplete="off" invalid={!!errors.name} />}
          </Field>
          <Field label="Nomor HP / WhatsApp" required hint="Format 08…, 62…, atau +62…" error={errors.phone?.message}>
            {(a) => <Input {...a} {...register('phone')} type="tel" inputMode="tel" placeholder="0812xxxxxxxx" invalid={!!errors.phone} />}
          </Field>
          <Field
            label="Nomor KTP (NIK)"
            required={!isEdit}
            hint={isEdit ? `Tersimpan: ${customer?.nik_masked}. Kosongkan bila tidak diubah.` : '16 digit. Disimpan terenkripsi.'}
            error={errors.nik?.message}
          >
            {(a) => <Input {...a} {...register('nik')} inputMode="numeric" autoComplete="off" maxLength={20} placeholder={isEdit ? customer?.nik_masked : '3201xxxxxxxxxxxx'} invalid={!!errors.nik} />}
          </Field>
          <div className="flex items-center sm:pt-5">
            <Controller control={control} name="for_self_drive" render={({ field }) => (
              <Switch checked={field.value} onChange={field.onChange} label="Pelanggan Lepas Kunci" description="SIM, alamat, dan kontak darurat menjadi wajib." />
            )} />
          </div>
        </FormSection>

        <FormSection title="SIM & domisili" description={selfDrive ? 'Wajib untuk layanan Lepas Kunci.' : 'Opsional untuk layanan Dengan Driver.'}>
          <Field label="Nomor SIM" required={selfDrive} error={errors.sim_number?.message}>
            {(a) => <Input {...a} {...register('sim_number')} autoComplete="off" invalid={!!errors.sim_number} />}
          </Field>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Jenis SIM" error={errors.sim_type?.message}>
              {(a) => <Select {...a} {...register('sim_type')}>{SIM_TYPES.map((t) => <option key={t} value={t}>{t}</option>)}</Select>}
            </Field>
            <Field label="Berlaku s.d." required={selfDrive} error={errors.sim_expires_at?.message}>
              {(a) => <Input {...a} {...register('sim_expires_at')} type="date" invalid={!!errors.sim_expires_at} />}
            </Field>
          </div>
          <Field label="Alamat domisili" required={selfDrive} className="sm:col-span-2" error={errors.address?.message}>
            {(a) => <Textarea {...a} {...register('address')} rows={2} invalid={!!errors.address} />}
          </Field>
          <Field label="Kontak darurat: nama" required={selfDrive} error={errors.emergency_contact_name?.message}>
            {(a) => <Input {...a} {...register('emergency_contact_name')} invalid={!!errors.emergency_contact_name} />}
          </Field>
          <div className="grid grid-cols-[1fr_7rem] gap-3">
            <Field label="Kontak darurat: HP" required={selfDrive} error={errors.emergency_contact_phone?.message}>
              {(a) => <Input {...a} {...register('emergency_contact_phone')} type="tel" inputMode="tel" invalid={!!errors.emergency_contact_phone} />}
            </Field>
            <Field label="Hubungan" error={errors.emergency_contact_relation?.message}>
              {(a) => <Input {...a} {...register('emergency_contact_relation')} placeholder="Istri" />}
            </Field>
          </div>
        </FormSection>

        <FormSection title="Dokumen" description="Foto KTP & SIM disimpan di penyimpanan privat dan hanya bisa dibuka lewat tautan sementara.">
          {canManageDocs ? (
            (['ktp', 'sim'] as const).map((type) => (
              <DocumentField
                key={type}
                label={`Foto ${type.toUpperCase()}`}
                file={files[type]}
                onChange={(f) => setFiles((s) => ({ ...s, [type]: f }))}
                existing={!!customer?.documents[type].uploaded && !removed[type]}
                existingUrl={removed[type] ? null : customer?.documents[type].url}
                onRemoveExisting={() => setRemoved((s) => ({ ...s, [type]: true }))}
                disabled={busy}
              />
            ))
          ) : (
            <p className="text-xs text-ink-muted sm:col-span-2">Peran Anda tidak dapat mengelola dokumen pelanggan.</p>
          )}
        </FormSection>

        <FormSection title="Risiko">
          <Field
            label="Status risiko"
            hint={canChangeRisk ? undefined : 'Hanya admin/pemilik yang dapat mengubah status risiko.'}
            error={errors.risk_status?.message}
          >
            {(a) => (
              <Select {...a} {...register('risk_status')} disabled={!canChangeRisk}>
                <option value="lancar">Lancar</option>
                <option value="perhatian">Perhatian</option>
                <option value="blacklist">Blacklist</option>
              </Select>
            )}
          </Field>
          <div className="hidden sm:block" />
          <Field
            label="Catatan risiko"
            required={riskStatus !== 'lancar'}
            hint="Wajib untuk status Perhatian / Blacklist."
            className="sm:col-span-2"
            error={errors.risk_note?.message}
          >
            {(a) => <Textarea {...a} {...register('risk_note')} rows={2} disabled={!canChangeRisk} invalid={!!errors.risk_note} />}
          </Field>
          <Field label="Catatan lain" className="sm:col-span-2" error={errors.notes?.message}>
            {(a) => <Textarea {...a} {...register('notes')} rows={2} />}
          </Field>
        </FormSection>
      </form>
    </Modal>
  )
}
