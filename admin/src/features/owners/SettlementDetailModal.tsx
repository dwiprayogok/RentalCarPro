import { FileText, MessageCircle, Paperclip, Printer, X } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Alert, Skeleton } from '@/components/ui/Feedback'
import { Field, Input, Select } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, fieldErrors } from '@/lib/api'
import { toWibInput } from '@/lib/datetime'
import { dateOnly, dateTimeWib, rupiah } from '@/lib/format'
import { openSettlementPdf, useCancelSettlement, usePaySettlement, useSettlement, type Settlement } from './api'
import { SettlementTable } from './SettlementTable'

function PayForm({ s, onDone }: { s: Settlement; onDone: () => void }) {
  const toast = useToast()
  const pay = usePaySettlement(s.id)
  const fileRef = useRef<HTMLInputElement>(null)
  const [method, setMethod] = useState('transfer')
  const [paidAt, setPaidAt] = useState(() => toWibInput(new Date()))
  const [reference, setReference] = useState('')
  const [proof, setProof] = useState<File | null>(null)
  const errs = pay.error ? fieldErrors(pay.error) : {}
  const general = pay.error && !Object.keys(errs).length ? errorMessage(pay.error) : errs.settlement

  return (
    <form className="space-y-3 rounded-xl border border-brand-200 bg-brand-50/40 p-4" noValidate onSubmit={(e) => {
      e.preventDefault()
      pay.mutate({ method, paid_at: paidAt, reference, proof }, { onSuccess: (r) => { toast(r.message); onDone() } })
    }}>
      <p className="text-sm font-bold text-ink">Catat pembayaran ke pemilik · {rupiah(s.owner_amount)}</p>
      {general && <Alert>{general}</Alert>}
      <div className="grid gap-3 sm:grid-cols-3">
        <Field label="Metode" error={errs.method}>{(a) => <Select {...a} value={method} onChange={(e) => setMethod(e.target.value)}><option value="transfer">Transfer</option><option value="tunai">Tunai</option><option value="qris">QRIS</option></Select>}</Field>
        <Field label="Waktu bayar (WIB)" error={errs.paid_at}>{(a) => <Input {...a} type="datetime-local" value={paidAt} max={toWibInput(new Date())} onChange={(e) => setPaidAt(e.target.value)} invalid={!!errs.paid_at} />}</Field>
        <Field label="No. referensi" error={errs.reference}>{(a) => <Input {...a} value={reference} onChange={(e) => setReference(e.target.value)} placeholder="Opsional" />}</Field>
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <Button size="sm" variant="secondary" onClick={() => fileRef.current?.click()}><Paperclip className="size-3.5" /> {proof ? 'Ganti bukti' : 'Lampirkan bukti transfer'}</Button>
        <span className="truncate text-xs text-ink-muted">{proof?.name ?? 'JPG/PNG/WebP/PDF maks. 5 MB (privat)'}</span>
        {errs.proof && <span className="text-xs font-medium text-red-600">{errs.proof}</span>}
        <input ref={fileRef} type="file" hidden accept="image/jpeg,image/png,image/webp,application/pdf" onChange={(e) => {
          const f = e.target.files?.[0] ?? null
          e.target.value = ''
          if (f && f.size > 5 * 1024 * 1024) { toast('Ukuran bukti maksimal 5 MB.', 'error'); return }
          setProof(f)
        }} />
      </div>
      <div className="flex justify-end gap-2">
        <Button size="sm" variant="ghost" onClick={onDone}>Batal</Button>
        <Button size="sm" type="submit" loading={pay.isPending}>Tandai sudah dibayar</Button>
      </div>
    </form>
  )
}

export function SettlementDetailModal({ id, onClose }: { id: number | null; onClose: () => void }) {
  const toast = useToast()
  const q = useSettlement(id)
  const cancel = useCancelSettlement()
  const [paying, setPaying] = useState(false)
  const [confirm, setConfirm] = useState(false)
  const [printing, setPrinting] = useState(false)
  useEffect(() => { setPaying(false) }, [id])
  const s = q.data

  return (
    <>
      <Modal open={!!id} onClose={onClose} size="lg" title={s ? `Setoran ${s.code}` : 'Setoran'}
        description={s ? `${s.owner.name} · ${dateOnly(s.period_from)} – ${dateOnly(s.period_to)}` : undefined}
        footer={s && (
          <div className="flex w-full flex-wrap items-center gap-2">
            {s.can.delete && <Button variant="ghost" className="text-red-600" onClick={() => setConfirm(true)}><X className="size-4" /> Batalkan</Button>}
            <div className="ml-auto flex flex-wrap gap-2">
              <Button variant="secondary" loading={printing} onClick={async () => {
                setPrinting(true)
                try { await openSettlementPdf(s.id) } catch (e) { toast(errorMessage(e), 'error') } finally { setPrinting(false) }
              }}><Printer className="size-4" /> Slip PDF</Button>
              {s.whatsapp_url && <a href={s.whatsapp_url} target="_blank" rel="noreferrer" className="inline-flex h-10 items-center gap-2 rounded-xl bg-[#1FAF5A] px-4 text-sm font-semibold text-white hover:bg-[#1a9a4f]"><MessageCircle className="size-4" /> Kirim ke pemilik</a>}
              {s.can.pay && !paying && <Button onClick={() => setPaying(true)}>Catat pembayaran</Button>}
            </div>
          </div>
        )}>
        {q.isLoading ? <Skeleton className="h-72" /> : q.isError ? <ErrorState error={q.error} onRetry={() => q.refetch()} /> : s && (
          <div className="space-y-4">
            <div className="flex flex-wrap items-center gap-2">
              <Badge tone={s.status === 'dibayar' ? 'green' : 'yellow'}>{s.status_label}</Badge>
              <Badge tone="gray" dot={false}>{s.share_basis_label} · {s.share_percent.toLocaleString('id-ID')}%</Badge>
              <span className="text-xs text-ink-muted">Dibuat {dateTimeWib(s.created_at)}{s.created_by && ` oleh ${s.created_by}`}</span>
            </div>
            {s.status === 'dibayar' && (
              <div className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">
                <p className="text-emerald-900">Dibayar <b>{dateTimeWib(s.paid_at)}</b> via {s.paid_method_label}{s.paid_reference && ` · Ref ${s.paid_reference}`}{s.paid_by && ` · oleh ${s.paid_by}`}</p>
                {s.proof_url && <a href={s.proof_url} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-800 hover:underline"><FileText className="size-3.5" /> Lihat bukti</a>}
              </div>
            )}
            {paying && <PayForm s={s} onDone={() => setPaying(false)} />}
            <SettlementTable lines={s.lines ?? []} t={{ ...s, share_label: `${s.share_percent.toLocaleString('id-ID')}% ${s.share_basis === 'kotor' ? 'pendapatan kotor' : 'laba bersih'}` }} />
            {s.notes && <p className="text-sm text-ink-soft"><span className="font-semibold text-ink">Catatan:</span> {s.notes}</p>}
          </div>
        )}
      </Modal>
      <ConfirmDialog open={confirm} title="Batalkan setoran?" confirmLabel="Ya, batalkan" loading={cancel.isPending}
        description={s && <>Setoran <b>{s.code}</b> ({rupiah(s.owner_amount)}) akan dibatalkan. Periode yang sama bisa dibuat ulang setelahnya.</>}
        onClose={() => setConfirm(false)}
        onConfirm={() => s && cancel.mutate(s.id, {
          onSuccess: (r) => { toast(r.message); setConfirm(false); onClose() },
          onError: (e) => { toast(errorMessage(e), 'error'); setConfirm(false) },
        })} />
    </>
  )
}
