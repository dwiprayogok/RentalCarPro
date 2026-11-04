import { BarChart3, CarFront, FileText, Inbox, KeyRound, LayoutGrid, Receipt, ShieldCheck, Undo2, Users, type LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { PageHeader } from '@/components/layout/PageHeader'

interface Section { id: string; icon: LucideIcon; title: string; body: ReactNode }

const SECTIONS: Section[] = [
  { id: 'mulai', icon: LayoutGrid, title: 'Memulai', body: (
    <ol>
      <li>Lengkapi data <strong>Armada</strong>: tarif lepas kunci, tarif dengan driver (total termasuk driver), deposit, dan jatuh tempo pajak/STNK.</li>
      <li>Tambahkan <strong>Pelanggan</strong>. Untuk layanan Lepas Kunci, isi SIM, alamat, kontak darurat, dan unggah foto KTP/SIM.</li>
      <li>Dashboard menampilkan peringatan keterlambatan, pembayaran bulan ini, dan pengingat perawatan setiap hari.</li>
    </ol>
  ) },
  { id: 'sewa-baru', icon: KeyRound, title: 'Membuat Sewa Baru', body: (
    <ol>
      <li>Klik <strong>Sewa Baru</strong>, pilih pelanggan (bisa dicari nama/HP/KTP), layanan, mobil, dan jadwal (WIB).</li>
      <li>Ringkasan biaya di kanan dihitung otomatis. Durasi dibulatkan per 24 jam setelah <strong>toleransi 2 jam</strong>.</li>
      <li>Jika jadwal bentrok dengan sewa lain (termasuk jeda 2 jam antar sewa), sistem menolak dan menampilkan sewa yang bentrok. Cek <strong>kalender ketersediaan</strong>.</li>
      <li>Aktifkan <strong>Mulai sekarang</strong> bila mobil langsung diserahkan, lalu isi checklist serah terima (odometer, BBM, kondisi, foto).</li>
      <li>Catat DP dan deposit bila diterima. Pelanggan <strong>Blacklist</strong> tidak bisa menyewa kecuali pemilik memberi pengecualian beralasan.</li>
    </ol>
  ) },
  { id: 'serah-terima', icon: CarFront, title: 'Serah terima booking', body: (
    <p>Saat pelanggan mengambil mobil yang sudah di-booking, buka transaksinya lalu klik <strong>Serah terima</strong>. Odometer tidak boleh lebih kecil dari odometer terakhir mobil. Mobil otomatis berstatus Disewa.</p>
  ) },
  { id: 'pembayaran', icon: Receipt, title: 'Pembayaran & deposit', body: (
    <ul>
      <li>Satu sewa bisa punya banyak pembayaran: DP, pelunasan, dan deposit. Metode Tunai, Transfer, atau QRIS; untuk Transfer/QRIS isi nomor referensi atau unggah bukti.</li>
      <li>Total, terbayar, dan sisa dihitung otomatis oleh sistem. Pembayaran melebihi sisa tagihan ditolak.</li>
      <li><strong>Deposit</strong> adalah dana titipan: tidak dihitung sebagai pembayaran sewa dan dikembalikan saat Kembali.</li>
    </ul>
  ) },
  { id: 'kembali', icon: Undo2, title: 'Proses Kembali & denda', body: (
    <ol>
      <li>Buka transaksi aktif, klik <strong>Kembali</strong>. Isi odometer, BBM, checklist, foto, dan biaya tambahan (BBM, kebersihan, kelebihan km, kerusakan) beserta alasannya.</li>
      <li>Denda dihitung otomatis: terlambat ≤ 2 jam gratis; setelahnya Rp 50.000 per jam, maksimal tarif 1 hari per 24 jam; setiap 24 jam penuh dihitung sebagai hari sewa tambahan.</li>
      <li>Sistem menyarankan memotong deposit untuk sisa tagihan; sisa deposit dikembalikan. Tinjau ringkasan, lalu <strong>Konfirmasi selesai</strong>.</li>
      <li>Ada biaya kerusakan → mobil otomatis ke status <strong>Servis</strong>. Pelanggan yang terlambat otomatis ditandai <strong>Perhatian</strong>.</li>
    </ol>
  ) },
  { id: 'nota', icon: FileText, title: 'Nota', body: (
    <p>Klik <strong>Nota</strong> di daftar atau detail transaksi. Gunakan <strong>Cetak / PDF</strong> untuk mencetak, atau <strong>WhatsApp</strong> untuk mengirim ringkasan beserta tautan PDF (berlaku 7 hari) ke pelanggan.</p>
  ) },
  { id: 'booking', icon: Inbox, title: 'Permintaan Booking', body: (
    <p>Permintaan dari landing page masuk dengan status <strong>Baru</strong>. Klik <strong>Hubungi</strong> untuk membuka WhatsApp (status menjadi Dihubungi). Klik <strong>Jadikan Sewa</strong> untuk membuka Sewa Baru yang sudah terisi. Jika nomor HP belum terdaftar, isi data pelanggan terlebih dahulu.</p>
  ) },
  { id: 'laporan', icon: BarChart3, title: 'Laporan', body: (
    <p>Menu <strong>Laporan Pembayaran</strong> (pemilik & admin) berisi pembayaran masuk per tanggal bayar, piutang, pendapatan per mobil, utilisasi armada, dan biaya servis. Pilih periode, metode, dan mobil, lalu <strong>Ekspor CSV</strong> untuk dibuka di Excel.</p>
  ) },
  { id: 'peran', icon: ShieldCheck, title: 'Peran & keamanan', body: (
    <ul>
      <li><strong>Pemilik</strong>: semua akses, termasuk pengaturan tarif/denda, kelola pengguna, dan pengecualian blacklist.</li>
      <li><strong>Admin</strong>: operasional penuh dan laporan, tanpa pengaturan sistem.</li>
      <li><strong>Staf</strong>: catat sewa, serah terima, kembali, pembayaran masuk, dan pelanggan; tidak bisa menghapus data atau melihat laporan keuangan.</li>
      <li>NIK disimpan terenkripsi dan selalu tampil tersamar. Foto KTP/SIM, bukti bayar, dan foto kondisi hanya bisa dibuka lewat tautan sementara.</li>
    </ul>
  ) },
  { id: 'pelanggan', icon: Users, title: 'Status risiko pelanggan', body: (
    <p><strong>Lancar</strong> (normal), <strong>Perhatian</strong> (pernah bermasalah, misalnya terlambat), dan <strong>Blacklist</strong> (tidak boleh menyewa). Perhatian dan Blacklist wajib disertai catatan; hanya pemilik/admin yang dapat mengubahnya.</p>
  ) },
]

export function GuidePage() {
  return (
    <>
      <PageHeader eyebrow="Insight & Bantuan" title="Panduan Penggunaan" subtitle="Langkah singkat memakai RentalPro sehari-hari." />
      <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
        <nav aria-label="Daftar isi" className="hidden lg:block">
          <ul className="sticky top-20 space-y-1 text-sm">
            {SECTIONS.map((s) => <li key={s.id}><a href={`#${s.id}`} className="block rounded-lg px-3 py-1.5 text-ink-soft hover:bg-white hover:text-ink">{s.title}</a></li>)}
          </ul>
        </nav>
        <div className="space-y-4">
          {SECTIONS.map((s) => (
            <section key={s.id} id={s.id} className="card scroll-mt-20 p-5">
              <h2 className="mb-2 flex items-center gap-2 text-base font-bold text-ink"><s.icon className="size-5 text-brand-500" /> {s.title}</h2>
              <div className="space-y-1 text-sm leading-relaxed text-ink-soft [&_li]:ml-4 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ul]:list-disc [&_ul]:space-y-1">{s.body}</div>
            </section>
          ))}
        </div>
      </div>
    </>
  )
}
