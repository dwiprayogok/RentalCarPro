import { zodResolver } from '@hookform/resolvers/zod'
import { Paperclip } from 'lucide-react'
import { useRef, useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, Input, Select } from '@/components/ui/Field'
import { MoneyInput } from '@/components/ui/MoneyInput'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, errorStatus } from '@/lib/api'
import { applyServerErrors } from '@/lib/form'
import { rupiah } from '@/lib/format'
import { useAddPayment } from './api'
import type { Rental } from './types'

const schema = z
  .object({
    type: z.enum(['dp', 'pelunasan', 'deposit']),
    method: z.enum(['tunai', 'transfer', 'qris']),
    amount: z.number({ error: 'Isi jumlah pembayaran.' }).int().min(1, 'Isi jumlah pembayaran.'),
    reference_number: z.string().trim().max(100),
    notes: z.string().trim().max(500),
    has_proof: z.boolean(),
  })
  .refine((v) => v.method === 'tunai' || v.reference_number || v.has_proof, {
    path: ['reference_number'],
    message: 'Isi nomor referensi atau lampirkan bukti untuk Transfer/QRIS.',
  })
type Values = z.infer<typeof schema>

/** Catat pembayaran. Server memvalidasi ulang dan menghitung ulang total terbayar. */
export function PaymentForm({ rental, onDone }: { rental: Rental; onDone: () => void }) {
  const add = useAddPayment(rental.id)
  const toast = useToast()
  const fileRef = useRef<HTMLInputElement>(null)
  const [proof, setProof] = useState<File | null>(null)
  const [formError, setFormError] = useState<string | null>(null)
  const b = rental.billing
  const defaultType = b.balance > 0 ? (b.paid_amount > 0 ? 'pelunasan' : 'dp') : 'deposit'

  const { register, control, handleSubmit, setValue, setError, formState: { errors } } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: {
      type: defaultType,
      method: 'tunai',
      amount: (defaultType === 'deposit' ? b.deposit_outstanding : b.balance) || (undefined as unknown as number),
      reference_number: '',
      notes: '',
      has_proof: false,
    },
  })
  const type = useWatch({ control, name: 'type' })
  const max = type === 'deposit' ? (b.deposit_required > 0 ? b.deposit_outstanding : undefined) : b.balance

  const onSubmit = handleSubmit((v) => {
    setFormError(null)
    add.mutate({ type: v.type, method: v.method, amount: v.amount, reference_number: v.reference_number || null, notes: v.notes || null, proof }, {
      onSuccess: (r) => { toast(r.message); onDone() },
      onError: (e) => {
        const orphan = applyServerErrors(e, setError, ['type', 'method', 'amount', 'reference_number', 'notes'])
        if (errorStatus(e) !== 422 || orphan) setFormError(orphan ?? errorMessage(e))
      },
    })
  })

  return (
    <form onSubmit={onSubmit} noValidate className="space-y-3 rounded-xl border border-brand-200 bg-brand-50/40 p-4">
      {formError && <Alert>{formError}</Alert>}
      <div className="grid gap-3 sm:grid-cols-3">
        <Field label="Jenis" error={errors.type?.message}>
          {(a) => (
            <Select {...a} {...register('type', {
              onChange: (e) => setValue('amount', (e.target.value === 'deposit' ? b.deposit_outstanding : b.balance) || (undefined as unknown as number)),
            })}>
              <option value="dp" disabled={b.balance <= 0}>DP</option>
              <option value="pelunasan" disabled={b.balance <= 0}>Pelunasan</option>
              <option value="deposit">Deposit</option>
            </Select>
          )}
        </Field>
        <Field label="Metode" error={errors.method?.message}>
          {(a) => <Select {...a} {...register('method')}><option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></Select>}
        </Field>
        <Field label="Jumlah" required hint={max !== undefined ? `Maks. ${rupiah(max)}` : undefined} error={errors.amount?.message}>
          {(a) => <Controller control={control} name="amount" render={({ field }) => <MoneyInput {...a} value={field.value} onChange={field.onChange} invalid={!!errors.amount} />} />}
        </Field>
        <Field label="No. referensi" error={errors.reference_number?.message}>
          {(a) => <Input {...a} {...register('reference_number')} invalid={!!errors.reference_number} />}
        </Field>
        <div className="sm:col-span-2">
          <p className="mb-1.5 text-xs font-semibold text-ink-soft">Bukti bayar (privat)</p>
          <div className="flex items-center gap-2">
            <Button size="sm" variant="secondary" onClick={() => fileRef.current?.click()}><Paperclip className="size-3.5" /> {proof ? 'Ganti berkas' : 'Lampirkan'}</Button>
            <span className="truncate text-xs text-ink-muted">{proof?.name ?? 'JPG/PNG/WebP/PDF maks. 5 MB'}</span>
          </div>
          <input ref={fileRef} type="file" hidden accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => {
            const f = e.target.files?.[0] ?? null
            setProof(f && f.size <= 5 * 1024 * 1024 ? f : null)
            setValue('has_proof', !!f)
            e.target.value = ''
          }} />
        </div>
      </div>
      <div className="flex justify-end gap-2">
        <Button size="sm" variant="ghost" onClick={onDone}>Batal</Button>
        <Button size="sm" type="submit" loading={add.isPending}>Simpan pembayaran</Button>
      </div>
    </form>
  )
}
