import { ImagePlus, Loader2, Star, Trash2 } from 'lucide-react'
import { useRef, useState, type ReactNode } from 'react'
import { CarStatusBadge, Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Skeleton } from '@/components/ui/Feedback'
import { Modal } from '@/components/ui/Modal'
import { ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { errorMessage } from '@/lib/api'
import { cn } from '@/lib/cn'
import { dateOnly, daysUntil, number, rupiah } from '@/lib/format'
import type { Car } from '@/lib/types'
import { useCar, useDeleteCar, useDeleteCarPhoto, useSetCoverPhoto, useUploadCarPhotos } from './api'
import { CarIllustration } from './CarIllustration'

function Info({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <dt className="text-2xs font-semibold tracking-wide text-ink-muted uppercase">{label}</dt>
      <dd className="mt-0.5 text-sm font-medium text-ink">{children}</dd>
    </div>
  )
}

function DueDate({ value }: { value: string | null }) {
  const days = daysUntil(value)
  if (days === null) return <span className="text-ink-muted">Belum diisi</span>
  const tone = days < 0 ? 'red' : days <= 30 ? 'yellow' : null
  return (
    <span className="inline-flex flex-wrap items-center gap-2">
      {dateOnly(value)}
      {tone && <Badge tone={tone}>{days < 0 ? `Lewat ${-days} hari` : days === 0 ? 'Hari ini' : `${days} hari lagi`}</Badge>}
    </span>
  )
}

function Gallery({ car }: { car: Car }) {
  const toast = useToast()
  const inputRef = useRef<HTMLInputElement>(null)
  const upload = useUploadCarPhotos(car.id)
  const setCover = useSetCoverPhoto(car.id)
  const remove = useDeleteCarPhoto(car.id)
  const photos = car.photos ?? []
  const cover = photos.find((p) => p.is_cover) ?? photos[0]
  const [selected, setSelected] = useState<number | null>(null)
  const active = photos.find((p) => p.id === selected) ?? cover

  const onFiles = (files: FileList | null) => {
    if (!files?.length) return
    upload.mutate(Array.from(files), {
      onSuccess: (r) => toast(r.message),
      onError: (e) => toast(errorMessage(e), 'error'),
    })
    if (inputRef.current) inputRef.current.value = ''
  }

  return (
    <div>
      <div className="relative aspect-[16/9] overflow-hidden rounded-xl bg-canvas">
        {active ? (
          <img src={active.url} alt={car.name} className="size-full object-cover" />
        ) : (
          <div className="flex size-full items-center justify-center px-10"><CarIllustration className="w-full max-w-xs" /></div>
        )}
        <div className="absolute top-3 left-3"><CarStatusBadge status={car.status} label={car.status_label} /></div>
      </div>

      {(photos.length > 0 || car.can.update) && (
        <div className="mt-3 flex flex-wrap gap-2">
          {photos.map((p) => (
            <div key={p.id} className="group relative">
              <button
                type="button"
                onClick={() => setSelected(p.id)}
                className={cn('block size-16 overflow-hidden rounded-lg ring-2', active?.id === p.id ? 'ring-brand-500' : 'ring-transparent hover:ring-brand-200')}
                aria-label="Lihat foto"
              >
                <img src={p.url} alt="" className="size-full object-cover" />
              </button>
              {p.is_cover && <span className="absolute -top-1.5 -left-1.5 grid size-5 place-items-center rounded-full bg-brand-500 text-white" title="Foto sampul"><Star className="size-3" fill="currentColor" /></span>}
            </div>
          ))}
          {car.can.update && (
            <>
              <button
                type="button"
                onClick={() => inputRef.current?.click()}
                disabled={upload.isPending}
                className="grid size-16 place-items-center rounded-lg border-2 border-dashed border-line-strong text-ink-muted hover:border-brand-300 hover:text-brand-600"
                aria-label="Unggah foto"
              >
                {upload.isPending ? <Loader2 className="size-5 animate-spin" /> : <ImagePlus className="size-5" />}
              </button>
              <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" multiple hidden onChange={(e) => onFiles(e.target.files)} />
            </>
          )}
        </div>
      )}

      {car.can.update && active && (
        <div className="mt-2 flex gap-2">
          {!active.is_cover && (
            <Button size="sm" variant="ghost" loading={setCover.isPending} onClick={() => setCover.mutate(active.id, { onError: (e) => toast(errorMessage(e), 'error') })}>
              <Star className="size-3.5" /> Jadikan sampul
            </Button>
          )}
          <Button size="sm" variant="ghost" className="text-red-600" loading={remove.isPending}
            onClick={() => remove.mutate(active.id, { onSuccess: () => setSelected(null), onError: (e) => toast(errorMessage(e), 'error') })}>
            <Trash2 className="size-3.5" /> Hapus foto
          </Button>
        </div>
      )}
      {car.can.update && <p className="mt-2 text-2xs text-ink-muted">JPG/PNG/WebP, maks. 5 MB per foto, hingga 12 foto.</p>}
    </div>
  )
}

export function CarDetailModal({ carId, onClose, onEdit }: { carId: number | null; onClose: () => void; onEdit: (car: Car) => void }) {
  const { data: car, isLoading, isError, error, refetch } = useCar(carId)
  const del = useDeleteCar()
  const toast = useToast()
  const [confirm, setConfirm] = useState(false)

  return (
    <>
      <Modal
        open={carId !== null}
        onClose={onClose}
        size="lg"
        title={car ? car.name : 'Detail armada'}
        description={car ? `${car.plate_number} · ${car.year}` : undefined}
        footer={car && (car.can.update || car.can.delete) ? (
          <>
            {car.can.delete && <Button variant="ghost" className="mr-auto text-red-600" onClick={() => setConfirm(true)}><Trash2 className="size-4" /> Hapus</Button>}
            {car.can.update && <Button onClick={() => onEdit(car)}>Edit data</Button>}
          </>
        ) : undefined}
      >
        {isLoading && (
          <div className="space-y-4"><Skeleton className="aspect-[16/9] w-full" /><Skeleton className="h-4 w-1/2" /><Skeleton className="h-4 w-1/3" /></div>
        )}
        {isError && <ErrorState error={error} onRetry={() => refetch()} />}
        {car && (
          <div className="grid gap-6 md:grid-cols-[1.1fr_1fr]">
            <Gallery car={car} />
            <dl className="grid grid-cols-2 content-start gap-x-4 gap-y-4">
              <Info label="Tarif lepas kunci">{rupiah(car.rate_self_drive)} / hari</Info>
              <Info label="Tarif dengan driver">{rupiah(car.rate_with_driver)} / hari</Info>
              {(car.weekend_rate_self_drive || car.weekend_rate_with_driver) && (
                <Info label="Akhir pekan">
                  {car.weekend_rate_self_drive ? rupiah(car.weekend_rate_self_drive) : '-'} / {car.weekend_rate_with_driver ? rupiah(car.weekend_rate_with_driver) : '-'}
                </Info>
              )}
              <Info label="Deposit">{rupiah(car.default_deposit)}</Info>
              <Info label="Transmisi">{car.transmission_label}</Info>
              <Info label="Kursi / BBM">{car.seats} kursi · {car.fuel_type ?? '-'}</Info>
              <Info label="Warna">{car.color ?? '-'}</Info>
              <Info label="Odometer">{number(car.odometer)} km</Info>
              <Info label="Pajak"><DueDate value={car.tax_due_date} /></Info>
              <Info label="STNK"><DueDate value={car.stnk_due_date} /></Info>
              <Info label="Servis berikutnya">
                {car.next_service_date || car.next_service_odometer ? (
                  <span className="flex flex-col gap-1">
                    {car.next_service_date && <DueDate value={car.next_service_date} />}
                    {car.next_service_odometer && <span className="text-xs text-ink-muted">atau di {number(car.next_service_odometer)} km</span>}
                  </span>
                ) : <span className="text-ink-muted">Belum diisi</span>}
              </Info>
              <Info label="Landing page">
                {car.is_published ? <Badge tone="teal">Tampil · /{car.slug}</Badge> : <Badge tone="gray">Tidak tampil</Badge>}
              </Info>
              {car.features.length > 0 && (
                <div className="col-span-2">
                  <dt className="text-2xs font-semibold tracking-wide text-ink-muted uppercase">Fitur</dt>
                  <dd className="mt-1 flex flex-wrap gap-1.5">{car.features.map((f) => <Badge key={f} tone="gray" dot={false}>{f}</Badge>)}</dd>
                </div>
              )}
              {car.notes && <div className="col-span-2"><Info label="Catatan internal"><span className="whitespace-pre-line">{car.notes}</span></Info></div>}
            </dl>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        open={confirm}
        title="Hapus armada?"
        description={<>Armada <strong>{car?.name} ({car?.plate_number})</strong> akan dihapus dari daftar. Riwayat transaksi tetap tersimpan.</>}
        confirmLabel="Ya, hapus"
        loading={del.isPending}
        onClose={() => setConfirm(false)}
        onConfirm={() => car && del.mutate(car.id, {
          onSuccess: (r) => { toast(r.message); setConfirm(false); onClose() },
          onError: (e) => { toast(errorMessage(e), 'error'); setConfirm(false) },
        })}
      />
    </>
  )
}
