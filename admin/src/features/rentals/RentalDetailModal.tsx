import { AlertTriangle, Ban, Camera, KeyRound, MessageCircle, Plus, Trash2 } from 'lucide-react'
import { useRef, useState, type ReactNode } from 'react'
import { Avatar } from '@/components/ui/Avatar'
import { Badge, RiskBadge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Alert, Skeleton } from '@/components/ui/Feedback'
import { Field, Textarea } from '@/components/ui/Field'
import { Modal } from '@/components/ui/Modal'
import { ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { errorMessage, fieldErrors } from '@/lib/api'
import { dateTimeWib, number, phone, rupiah, waLink } from '@/lib/format'
import { humanDuration } from '@/lib/datetime'
import { uploadInspectionPhotos, useCancelRental, useDeletePayment, useHandover, useRental } from './api'
import { PaymentBadge, RentalStatusBadge } from './badges'
import { emptyHandover, FUEL_LEVELS, type HandoverValue } from './handover'
import { HandoverFields } from './HandoverFields'
import { PaymentForm } from './PaymentForm'
import type { Inspection, Rental } from './types'

function Section({ title, action, children }: { title: string; action?: ReactNode; children: ReactNode }) {
  return (
    <section>
      <div className="mb-2 flex items-center justify-between gap-2">
        <h3 className="text-2xs font-bold tracking-[0.12em] text-ink-muted uppercase">{title}</h3>
        {action}
      </div>
      {children}
    </section>
  )
}

function Line({ label, value, strong }: { label: ReactNode; value: ReactNode; strong?: boolean }) {
  return (
    <div className={strong ? 'flex justify-between gap-4 text-base font-extrabold text-ink' : 'flex justify-between gap-4 text-sm'}>
      <span className={strong ? '' : 'text-ink-muted'}>{label}</span>
      <span className="text-right font-semibold tabular-nums">{value}</span>
    </div>
  )
}

function InspectionView({ rental, inspection }: { rental: Rental; inspection: Inspection }) {
  const toast = useToast()
  const input = useRef<HTMLInputElement>(null)
  const [uploading, setUploading] = useState(false)
  const fuel = FUEL_LEVELS.find((f) => f.value === inspection.fuel_level)?.label ?? `${inspection.fuel_level}%`
  const issues = inspection.checklist.filter((c) => !c.ok)

  return (
    <div className="space-y-2 rounded-xl border border-line p-4 text-sm">
      <div className="flex flex-wrap gap-x-6 gap-y-1">
        <span><span className="text-ink-muted">Odometer:</span> <strong>{number(inspection.odometer)} km</strong></span>
        <span><span className="text-ink-muted">BBM:</span> <strong>{fuel}</strong></span>
        <span className="text-ink-muted">{dateTimeWib(inspection.inspected_at)}</span>
      </div>
      {issues.length > 0 ? (
        <ul className="space-y-0.5 text-xs text-amber-800">{issues.map((c) => <li key={c.item}>• {c.item}{c.note ? `: ${c.note}` : ''}</li>)}</ul>
      ) : (
        <p className="text-xs text-brand-700">Semua item checklist dalam kondisi baik.</p>
      )}
      {inspection.notes && <p className="text-xs text-ink-soft">{inspection.notes}</p>}
      <div className="flex flex-wrap gap-2 pt-1">
        {inspection.photo_urls.map((u, i) => (
          <a key={u} href={u} target="_blank" rel="noreferrer"><img src={u} alt={`Foto serah terima ${i + 1}`} className="size-16 rounded-lg object-cover ring-1 ring-line" /></a>
        ))}
        {rental.status === 'aktif' && (
          <>
            <button type="button" disabled={uploading} onClick={() => input.current?.click()} className="grid size-16 place-items-center rounded-lg border-2 border-dashed border-line-strong text-ink-muted hover:border-brand-300" aria-label="Tambah foto serah terima">
              <Camera className="size-5" />
            </button>
            <input ref={input} type="file" hidden multiple accept="image/jpeg,image/png,image/webp" onChange={async (e) => {
              const files = Array.from(e.target.files ?? [])
              e.target.value = ''
              if (!files.length) return
              setUploading(true)
              try { toast((await uploadInspectionPhotos(rental.id, 'serah_terima', files)).message) } catch (err) { toast(errorMessage(err), 'error') } finally { setUploading(false) }
            }} />
          </>
        )}
      </div>
    </div>
  )
}

function HandoverModal({ rental, open, onClose }: { rental: Rental; open: boolean; onClose: () => void }) {
  const handover = useHandover(rental.id)
  const toast = useToast()
  const [value, setValue] = useState<HandoverValue>(emptyHandover(rental.car.odometer))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [busy, setBusy] = useState(false)

  const submit = async () => {
    if (value.odometer === null) { setErrors({ odometer: 'Isi odometer awal.' }); return }
    if (value.odometer < rental.car.odometer) return // pesan sudah tampil di field
    setErrors({})
    setBusy(true)
    try {
      const res = await handover.mutateAsync({ odometer: value.odometer, fuel_level: value.fuel_level, checklist: value.checklist.map((c) => ({ ...c, note: c.ok ? null : c.note || null })), notes: value.notes || null })
      if (value.photos.length) await uploadInspectionPhotos(rental.id, 'serah_terima', value.photos).catch((e) => toast(`Foto gagal diunggah: ${errorMessage(e)}`, 'error'))
      toast(res.message)
      onClose()
    } catch (e) {
      // Server memakai key "handover.odometer" (alur Sewa Baru) atau "odometer" (endpoint serah terima).
      const fe = Object.fromEntries(Object.entries(fieldErrors(e)).map(([k, m]) => [k.replace(/^handover\./, ''), m]))
      setErrors(fe)
      if (!Object.keys(fe).some((k) => ['odometer', 'fuel_level', 'checklist', 'notes'].includes(k))) toast(errorMessage(e), 'error')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Modal open={open} onClose={onClose} dismissible={!busy} size="lg" title={`Serah terima ${rental.code}`} description={`${rental.car.name} · ${rental.car.plate_number} → ${rental.customer.name}`}
      footer={<><Button variant="secondary" onClick={onClose} disabled={busy}>Batal</Button><Button onClick={submit} loading={busy}><KeyRound className="size-4" /> Serahkan mobil</Button></>}>
      <HandoverFields value={value} onChange={setValue} errors={errors} minOdometer={rental.car.odometer} />
    </Modal>
  )
}

function CancelModal({ rental, open, onClose }: { rental: Rental; open: boolean; onClose: () => void }) {
  const cancel = useCancelRental(rental.id)
  const toast = useToast()
  const [reason, setReason] = useState('')
  const error = cancel.error ? fieldErrors(cancel.error).reason ?? errorMessage(cancel.error) : undefined
  return (
    <Modal open={open} onClose={onClose} size="sm" dismissible={!cancel.isPending} title={`Batalkan ${rental.code}?`}
      footer={<><Button variant="secondary" onClick={onClose} disabled={cancel.isPending}>Kembali</Button>
        <Button variant="danger" loading={cancel.isPending} onClick={() => cancel.mutate(reason, { onSuccess: (r) => { toast(r.message); onClose() } })}>Ya, batalkan</Button></>}>
      <div className="space-y-3">
        <p className="text-sm text-ink-soft">Jadwal mobil akan dibebaskan.{rental.billing.paid_amount + rental.billing.deposit_held > 0 && ' Pembayaran yang sudah diterima perlu dikembalikan secara terpisah.'}</p>
        <Field label="Alasan pembatalan" required error={error}>
          {(a) => <Textarea {...a} rows={2} value={reason} onChange={(e) => setReason(e.target.value)} invalid={!!error} />}
        </Field>
      </div>
    </Modal>
  )
}

export function RentalDetailModal({ rentalId, onClose }: { rentalId: number | null; onClose: () => void }) {
  const { data: r, isLoading, isError, error, refetch } = useRental(rentalId)
  const del = useDeletePayment(rentalId ?? 0)
  const toast = useToast()
  const [paying, setPaying] = useState(false)
  const [handoverOpen, setHandoverOpen] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const close = () => { setPaying(false); onClose() }

  return (
    <>
      <Modal
        open={rentalId !== null}
        onClose={close}
        size="lg"
        title={r ? r.code : 'Detail transaksi'}
        description={r ? `Dibuat ${dateTimeWib(r.created_at)}${r.created_by ? ` oleh ${r.created_by}` : ''}` : undefined}
        footer={r && (r.can.cancel || r.can.handover) ? (
          <>
            {r.can.cancel && <Button variant="ghost" className="mr-auto text-red-600" onClick={() => setCancelOpen(true)}><Ban className="size-4" /> Batalkan</Button>}
            {r.can.handover && <Button onClick={() => setHandoverOpen(true)}><KeyRound className="size-4" /> Serah terima</Button>}
          </>
        ) : undefined}
      >
        {isLoading && <div className="space-y-3"><Skeleton className="h-10 w-1/2" /><Skeleton className="h-40 w-full" /></div>}
        {isError && <ErrorState error={error} onRetry={() => refetch()} />}
        {r && (
          <div className="space-y-6">
            <div className="flex flex-wrap items-center gap-2">
              <RentalStatusBadge rental={r} />
              <PaymentBadge status={r.billing.payment_status} label={r.billing.payment_status_label} />
              <Badge tone="gray" dot={false}>{r.service_type_label}</Badge>
            </div>

            {r.is_overdue && r.billing.running_late && (
              <Alert tone={r.billing.running_late_fee > 0 ? 'red' : 'yellow'}>
                <span className="flex gap-2">
                  <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                  <span>
                    Terlambat {humanDuration(r.billing.running_late.late_minutes)}.{' '}
                    {r.billing.running_late_fee > 0
                      ? <>Estimasi denda berjalan <strong>{rupiah(r.billing.running_late_fee)}</strong>{r.billing.running_late.extra_days > 0 && ` (termasuk ${r.billing.running_late.extra_days} hari tambahan)`}. Final dihitung saat Kembali.</>
                      : 'Masih dalam masa toleransi, belum ada denda.'}
                  </span>
                </span>
              </Alert>
            )}
            {r.blacklist_override && <Alert tone="yellow">Pengecualian blacklist oleh {r.blacklist_override.by ?? 'pemilik'}: {r.blacklist_override.reason}</Alert>}
            {r.status === 'batal' && <Alert tone="yellow">Dibatalkan: {r.cancel_reason}</Alert>}

            <div className="grid gap-6 md:grid-cols-2">
              <Section title="Pelanggan">
                <div className="flex items-start gap-3">
                  <Avatar initials={r.customer.initials} className="size-10" />
                  <div className="min-w-0 flex-1 text-sm">
                    <p className="font-bold text-ink">{r.customer.name}</p>
                    <p className="text-ink-muted">{phone(r.customer.phone)}</p>
                    <p className="text-ink-muted tabular-nums">KTP {r.customer.nik_masked}</p>
                    <div className="mt-1 flex items-center gap-2">
                      <RiskBadge status={r.customer.risk_status} label={r.customer.risk_status_label} />
                      <a href={waLink(r.customer.phone)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:underline"><MessageCircle className="size-3.5" /> WhatsApp</a>
                    </div>
                  </div>
                </div>
              </Section>
              <Section title="Kendaraan">
                <p className="text-sm font-bold text-ink">{r.car.name}</p>
                <p className="text-sm text-ink-muted">{r.car.plate_number} · {r.car.transmission_label}{r.car.color ? ` · ${r.car.color}` : ''}</p>
                {r.driver && <p className="mt-1 text-sm"><span className="text-ink-muted">Driver:</span> <strong>{r.driver.name}</strong> · {phone(r.driver.phone)}</p>}
              </Section>
            </div>

            <Section title="Jadwal (WIB)">
              <div className="grid gap-2 rounded-xl border border-line p-4 text-sm sm:grid-cols-3">
                <div><p className="text-2xs text-ink-muted">Mulai</p><p className="font-semibold">{dateTimeWib(r.start_at)}</p></div>
                <div><p className="text-2xs text-ink-muted">Kembali rencana</p><p className={r.is_overdue ? 'font-semibold text-red-700' : 'font-semibold'}>{dateTimeWib(r.planned_end_at)}</p></div>
                <div><p className="text-2xs text-ink-muted">Diserahkan</p><p className="font-semibold">{r.picked_up_at ? dateTimeWib(r.picked_up_at) : 'Belum'}</p></div>
              </div>
            </Section>

            <Section title="Tagihan">
              <div className="space-y-1.5 rounded-xl border border-line p-4">
                <Line label={`Durasi × tarif (${r.billed_days} hari × ${rupiah(r.daily_rate)})`} value={rupiah(r.rental_amount)} />
                {r.price_breakdown?.lines.some((l) => l.special) && <p className="text-2xs text-ink-muted">Termasuk tarif akhir pekan/libur pada {r.price_breakdown.lines.filter((l) => l.special).length} hari.</p>}
                {r.discount > 0 && <Line label={`Diskon${r.discount_reason ? ` (${r.discount_reason})` : ''}`} value={`− ${rupiah(r.discount)}`} />}
                <div className="border-t border-dashed border-line-strong pt-2"><Line strong label="Total tagihan" value={rupiah(r.billing.total_amount)} /></div>
                <Line label="Pembayaran diterima" value={rupiah(r.billing.paid_amount)} />
                <Line label="Sisa tagihan" value={<span className={r.billing.balance > 0 ? 'text-amber-700' : 'text-brand-700'}>{rupiah(r.billing.balance)}</span>} />
                {r.billing.running_late_fee > 0 && <Line label="Estimasi denda berjalan" value={<span className="text-red-700">{rupiah(r.billing.running_late_fee)}</span>} />}
                <div className="border-t border-line pt-2">
                  <Line label="Deposit diminta / ditahan" value={`${rupiah(r.billing.deposit_required)} / ${rupiah(r.billing.deposit_held)}`} />
                </div>
              </div>
            </Section>

            <Section title="Riwayat pembayaran" action={r.can.add_payment && !paying && (
              <Button size="sm" variant="secondary" onClick={() => setPaying(true)}><Plus className="size-3.5" /> Catat pembayaran</Button>
            )}>
              {paying && <div className="mb-3"><PaymentForm rental={r} onDone={() => setPaying(false)} /></div>}
              {r.payments && r.payments.length > 0 ? (
                <ul className="divide-y divide-line rounded-xl border border-line">
                  {r.payments.map((p) => (
                    <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm">
                      <div className="min-w-0">
                        <p className="font-semibold text-ink">{p.type_label} · {p.method_label}</p>
                        <p className="text-2xs text-ink-muted">
                          {dateTimeWib(p.paid_at)}{p.reference_number && ` · Ref ${p.reference_number}`}{p.received_by && ` · ${p.received_by}`}
                          {p.proof_url && <> · <a href={p.proof_url} target="_blank" rel="noreferrer" className="font-semibold text-brand-600 hover:underline">Bukti</a></>}
                        </p>
                      </div>
                      <div className="flex items-center gap-2">
                        <span className={p.type === 'deposit' ? 'font-semibold text-ink-muted tabular-nums' : 'font-semibold tabular-nums'}>{rupiah(p.amount)}</span>
                        {r.can.delete_payment && (
                          <button type="button" onClick={() => del.mutate(p.id, { onSuccess: (x) => toast(x.message), onError: (e) => toast(errorMessage(e), 'error') })}
                            className="rounded p-1 text-ink-muted hover:bg-red-50 hover:text-red-600" aria-label={`Hapus pembayaran ${p.type_label}`}>
                            <Trash2 className="size-4" />
                          </button>
                        )}
                      </div>
                    </li>
                  ))}
                </ul>
              ) : !paying && <p className="rounded-xl bg-canvas px-4 py-3 text-center text-xs text-ink-muted">Belum ada pembayaran.</p>}
            </Section>

            <Section title="Serah terima">
              {r.inspections?.serah_terima
                ? <InspectionView rental={r} inspection={r.inspections.serah_terima} />
                : <p className="rounded-xl bg-canvas px-4 py-3 text-center text-xs text-ink-muted">{r.status === 'booking' ? 'Mobil belum diserahkan. Gunakan tombol "Serah terima" saat pelanggan mengambil mobil.' : 'Tidak ada data serah terima.'}</p>}
            </Section>

            {r.notes && <Section title="Catatan"><p className="text-sm whitespace-pre-line text-ink-soft">{r.notes}</p></Section>}
          </div>
        )}
      </Modal>

      {r && <HandoverModal key={`h${r.id}${handoverOpen}`} rental={r} open={handoverOpen} onClose={() => setHandoverOpen(false)} />}
      {r && <CancelModal key={`c${r.id}${cancelOpen}`} rental={r} open={cancelOpen} onClose={() => setCancelOpen(false)} />}
    </>
  )
}
