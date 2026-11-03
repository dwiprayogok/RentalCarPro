import type { ReactNode } from 'react'
import { AlertTriangle, Inbox, Lock, RefreshCw } from 'lucide-react'
import { Link } from 'react-router'
import { errorMessage } from '@/lib/api'
import { Button } from './Button'

export function EmptyState({ icon, title, description, action }: { icon?: ReactNode; title: string; description?: string; action?: ReactNode }) {
  return (
    <div className="flex flex-col items-center px-6 py-14 text-center">
      <div className="mb-4 grid size-14 place-items-center rounded-2xl bg-brand-50 text-brand-500">{icon ?? <Inbox className="size-7" />}</div>
      <h3 className="text-base font-bold text-ink">{title}</h3>
      {description && <p className="mt-1 max-w-sm text-sm text-ink-muted">{description}</p>}
      {action && <div className="mt-5">{action}</div>}
    </div>
  )
}

export function ErrorState({ error, onRetry }: { error?: unknown; onRetry?: () => void }) {
  return (
    <div className="flex flex-col items-center px-6 py-14 text-center" role="alert">
      <div className="mb-4 grid size-14 place-items-center rounded-2xl bg-red-50 text-red-600"><AlertTriangle className="size-7" /></div>
      <h3 className="text-base font-bold text-ink">Gagal memuat data</h3>
      <p className="mt-1 max-w-sm text-sm text-ink-muted">{errorMessage(error)}</p>
      {onRetry && (
        <Button variant="secondary" className="mt-5" onClick={onRetry}>
          <RefreshCw className="size-4" /> Coba lagi
        </Button>
      )}
    </div>
  )
}

export function ForbiddenState() {
  return (
    <div className="card mx-auto mt-10 max-w-lg">
      <EmptyState
        icon={<Lock className="size-7" />}
        title="Akses dibatasi"
        description="Peran Anda tidak memiliki izin untuk membuka halaman ini. Hubungi pemilik usaha bila Anda membutuhkannya."
        action={<Link to="/" className="text-sm font-semibold text-brand-600 hover:underline">Kembali ke Dashboard</Link>}
      />
    </div>
  )
}
