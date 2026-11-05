import { zodResolver } from '@hookform/resolvers/zod'
import { FileText, Paperclip, Trash2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Controller, useForm, useWatch } from 'react-hook-form'
import { z } from 'zod'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, Input, Select, Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { MoneyInput } from '@/components/ui/MoneyInput'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, errorStatus } from '@/lib/api'
import { todayWib } from '@/lib/datetime'
import { applyServerErrors } from '@/lib/form'
import { useCarList, useSaveExpense, type Expense, type ExpenseCategory, type ExpenseMeta } from './api'

const MAX_PROOF = 5 * 1024 * 1024

const schema = z.object({
  car_id: z.string(),
  category: z.string().min(1, 'Pilih kategori.'),
  title: z.string().trim().min(3, 'Keterangan minimal 3 karakter.').max(150),
  spent_at: z.string().min(1, 'Isi tanggal.').refine((d) => d <= todayWib(), 'Tanggal tidak boleh di masa depan.'),
  amount: z.number({ error: 'Isi jumlah.' }).int().min(1, 'Isi jumlah.'),
  method: z.string(),
  vendor: z.string().trim().max(120),
  notes: z.string().trim().max(1000),
})
type Values = z.infer<typeof schema>
const FIELDS = ['car_id', 'category', 'title', 'spent_at', 'amount', 'method', 'vendor', 'notes'] as const

export function ExpenseFormModal({ open, expense, categories, onClose }: {
  open: boolean
  expense: Expense | null
  categories: ExpenseMeta['categories']
  onClose: () => void
}) {
  const toast = useToast()
  const save = useSaveExpense()
  const cars = useCarList()
  const fileRef = useRef<HTMLInputElement>(null)
  const [proof, setProof] = useState<File | null>(null)
  const [removeProof, setRemoveProof] = useState(false)
  const [proofError, setProofError] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const { register, control, handleSubmit, reset, setError, formState: { errors } } = useForm<Values>({ resolver: zodResolver(schema) })

  useEffect(() => {
    if (!open) return
    reset(expense ? {
      car_id: expense.car_id ? String(expense.car_id) : '', category: expense.category, title: expense.title, spent_at: expense.spent_at,
      amount: expense.amount, method: expense.method ?? '', vendor: expense.vendor ?? '', notes: expense.notes ?? '',
    } : { car_id: '', category: 'bbm', title: '', spent_at: todayWib(), amount: undefined as unknown as number, method: 'tunai', vendor: '', notes: '' })
    setProof(null); setRemoveProof(false); setProofError(null); setFormError(null)
  }, [open, expense, reset])

  const category = useWatch({ control, name: 'category' })
  const carId = useWatch({ control, name: 'car_id' })
  const perCar = categories.find((c) => c.value === category)?.per_car

  const onSubmit = handleSubmit((v) => {
    setFormError(null)
    save.mutate({ id: expense?.id, payload: {
      car_id: v.car_id ? Number(v.car_id) : null, category: v.category as ExpenseCategory, title: v.title, spent_at: v.spent_at, amount: v.amount,
      method: v.method || null, vendor: v.vendor || null, notes: v.notes || null, proof, remove_proof: removeProof && !proof,
    } }, {
      onSuccess: (r) => { toast(r.message); onClose() },
      onError: (e) => {
        const orphan = applyServerErrors(e, setError, FIELDS)
        const fe = (e as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data?.errors?.proof?.[0]
        if (fe) setProofError(fe)
        if (errorStatus(e) !== 422 || (orphan && !fe)) setFormError(orphan ?? errorMessage(e))
      },
    })
  })

  const existingProof = expense?.has_proof && !removeProof && !proof

  return (
    <Modal open={open} onClose={onClose} dismissible={!save.isPending} title={expense ? 'Ubah Pengeluaran' : 'Catat Pengeluaran'}
      description="BBM, cuci, parkir, gaji, sewa tempat, dan biaya operasional lain. Biaya servis dicatat di Perawatan."
      footer={<><Button variant="secondary" onClick={onClose} disabled={save.isPending}>Batal</Button><Button type="submit" form="expense-form" loading={save.isPending}>Simpan</Button></>}>
      <form id="expense-form" onSubmit={onSubmit} noValidate className="space-y-4">
        {formError && <Alert>{formError}</Alert>}
        <div className="grid gap-4 sm:grid-cols-2">
          <Field label="Kategori" required error={errors.category?.message}>
            {(a) => <Select {...a} {...register('category')} invalid={!!errors.category}>{categories.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}</Select>}
          </Field>
          <Field label="Mobil" error={errors.car_id?.message}
            hint={perCar && !carId ? 'Kategori ini biasanya untuk satu mobil — pilih mobilnya agar masuk ke laba per mobil.' : !carId ? 'Kosongkan untuk pengeluaran umum usaha.' : undefined}>
            {(a) => (
              <Select {...a} {...register('car_id')} invalid={!!errors.car_id}>
                <option value="">Umum (tidak terkait mobil)</option>
                {(cars.data ?? []).map((c) => <option key={c.id} value={c.id}>{c.name} · {c.plate_number}</option>)}
              </Select>
            )}
          </Field>
          <Field label="Keterangan" required className="sm:col-span-2" error={errors.title?.message}>
            {(a) => <Input {...a} {...register('title')} invalid={!!errors.title} placeholder="Mis. Isi BBM antar pelanggan ke Bandung" />}
          </Field>
          <Field label="Tanggal" required error={errors.spent_at?.message}>
            {(a) => <Input {...a} type="date" max={todayWib()} {...register('spent_at')} invalid={!!errors.spent_at} />}
          </Field>
          <Field label="Jumlah" required error={errors.amount?.message}>
            {(a) => <Controller control={control} name="amount" render={({ field }) => <MoneyInput {...a} value={field.value} onChange={field.onChange} onBlur={field.onBlur} invalid={!!errors.amount} />} />}
          </Field>
          <Field label="Metode bayar" error={errors.method?.message}>
            {(a) => <Select {...a} {...register('method')}><option value="">—</option><option value="tunai">Tunai</option><option value="transfer">Transfer</option><option value="qris">QRIS</option></Select>}
          </Field>
          <Field label="Toko/vendor" error={errors.vendor?.message}>
            {(a) => <Input {...a} {...register('vendor')} invalid={!!errors.vendor} placeholder="Mis. SPBU Pajajaran" />}
          </Field>
          <Field label="Catatan" className="sm:col-span-2" error={errors.notes?.message}>
            {(a) => <Textarea {...a} rows={2} {...register('notes')} invalid={!!errors.notes} />}
          </Field>
        </div>

        <div>
          <p className="mb-1.5 text-xs font-semibold text-ink-soft">Bukti / struk (privat)</p>
          <div className="flex flex-wrap items-center gap-2">
            {existingProof && expense?.proof_url && (
              <a href={expense.proof_url} target="_blank" rel="noreferrer" className="inline-flex h-8 items-center gap-1.5 rounded-lg border border-line-strong px-3 text-xs font-semibold text-ink hover:bg-canvas">
                <FileText className="size-3.5" /> Lihat bukti
              </a>
            )}
            <Button size="sm" variant="secondary" onClick={() => fileRef.current?.click()}><Paperclip className="size-3.5" /> {proof || existingProof ? 'Ganti berkas' : 'Lampirkan'}</Button>
            {(existingProof || proof) && (
              <Button size="sm" variant="ghost" className="text-red-600" onClick={() => { setProof(null); if (expense?.has_proof) setRemoveProof(true) }}><Trash2 className="size-3.5" /> Hapus</Button>
            )}
            <span className="min-w-0 truncate text-xs text-ink-muted">{proof?.name ?? (removeProof ? 'Bukti akan dihapus saat disimpan' : !existingProof ? 'JPG/PNG/WebP/PDF maks. 5 MB' : '')}</span>
          </div>
          {proofError && <p className="mt-1 text-xs font-medium text-red-600">{proofError}</p>}
          <input ref={fileRef} type="file" hidden accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => {
            const f = e.target.files?.[0] ?? null
            e.target.value = ''
            if (f && f.size > MAX_PROOF) { setProofError('Ukuran bukti maksimal 5 MB.'); return }
            setProofError(null); setProof(f); if (f) setRemoveProof(false)
          }} />
        </div>
      </form>
    </Modal>
  )
}
