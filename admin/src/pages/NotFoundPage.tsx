import { Link } from 'react-router'
import { EmptyState } from '@/components/ui/States'

export function NotFoundPage() {
  return (
    <div className="card mx-auto mt-10 max-w-lg">
      <EmptyState
        title="Halaman tidak ditemukan"
        description="Alamat yang Anda buka tidak tersedia."
        action={<Link to="/" className="text-sm font-semibold text-brand-600 hover:underline">Kembali ke Dashboard</Link>}
      />
    </div>
  )
}
