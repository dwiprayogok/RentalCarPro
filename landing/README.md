# RentalPro Landing

Landing page publik: Next.js 15 (App Router) + Tailwind CSS v4. Semua data diambil dari Public API Laravel (`/api/public/*`), tidak pernah dari `/api/v1`.

## Halaman

| URL | Isi | Render |
|---|---|---|
| `/` | Hero + CTA WhatsApp, cek tanggal cepat, armada unggulan, cara sewa, testimoni, syarat ringkas, FAQ, kontak | ISR |
| `/armada` | Daftar mobil, filter transmisi/kursi/urutan, cek ketersediaan per tanggal (dari browser) | ISR + client |
| `/armada/[slug]` | Galeri, spesifikasi, tarif, fitur, kalender 60 hari, cek ketersediaan + estimasi, form permintaan sewa | ISR (SSG saat build) |
| `/syarat-ketentuan` | Syarat penyewa, aturan keterlambatan & deposit | ISR |
| `/sitemap.xml`, `/robots.txt`, `/opengraph-image` | SEO | ISR |
| `POST /api/revalidate` | Webhook dari Laravel | Dinamis |

## Setup

```bash
cp .env.example .env.local
npm install
npm run dev        # http://localhost:3000
```

| Variabel | Fungsi |
|---|---|
| `SITE_URL` | URL publik situs (canonical, Open Graph, sitemap) |
| `NEXT_PUBLIC_API_URL` | Public API Laravel, mis. `http://localhost:8000/api/public` |
| `API_URL_INTERNAL` | Opsional: URL API khusus server (mis. jaringan internal Docker) |
| `REVALIDATE_SECRET` | Harus sama dengan `LANDING_REVALIDATE_SECRET` di `backend/.env` |
| `REVALIDATE_SECONDS` | Interval ISR cadangan bila webhook gagal (default 600) |

Di backend, pastikan `LANDING_URL` sudah terisi (otomatis diizinkan CORS untuk cek ketersediaan dan form dari browser).

## Cara data tetap segar

1. Semua fetch server memakai tag `public` (`src/lib/api.ts`).
2. Saat admin mengubah mobil, foto, pengaturan landing, hari libur, atau jadwal sewa, Laravel menjalankan job `RevalidateLanding`.
3. Job itu memanggil `POST /api/revalidate` dengan header `X-Revalidate-Secret`, lalu landing menjalankan `revalidateTag('public')`.
4. Kalau webhook gagal, halaman tetap diperbarui otomatis tiap `REVALIDATE_SECONDS`.

Tes manual webhook:

```bash
curl -X POST http://localhost:3000/api/revalidate -H "X-Revalidate-Secret: <rahasia>" -H "Content-Type: application/json" -d '{"tags":["public"]}'
```

Kalau API mati saat build, halaman tetap dibuat dengan konten cadangan dan terisi data asli pada revalidate berikutnya.

## SEO

- Metadata dan Open Graph per halaman (gambar OG dibuat otomatis, detail mobil memakai foto sampul).
- Structured data JSON-LD: `AutoRental` (LocalBusiness) di beranda, `Product` + `Car` dengan `Offer` harga per hari di detail mobil.
- `sitemap.xml` berisi semua mobil yang di-publish; `robots.txt` menutup `/api/`.

## Perintah

```bash
npm run dev         # pengembangan
npm run build       # build produksi
npm run start       # jalankan build (port 3000)
npm run lint        # ESLint
npm run typecheck   # TypeScript
```

Semua waktu di form dianggap WIB dan dikirim ke API dengan offset `+07:00`.
