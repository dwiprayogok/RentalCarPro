import Link from 'next/link'

export default function NotFound() {
  return (
    <div className="mx-auto max-w-xl px-4 py-24 text-center">
      <p className="eyebrow">404</p>
      <h1 className="mt-2 text-3xl font-extrabold">Halaman tidak ditemukan</h1>
      <p className="mt-2 text-muted">Mobil atau halaman yang Anda cari mungkin sudah tidak tersedia.</p>
      <Link href="/armada" className="btn-primary mt-6">Lihat armada</Link>
    </div>
  )
}
