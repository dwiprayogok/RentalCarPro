# RentalPro: Admin Web

React 19 + TypeScript + Vite, Tailwind CSS v4, React Router, TanStack Query, React Hook Form + Zod.

```bash
cp .env.example .env   # VITE_API_URL=http://localhost:8000
npm install
npm run dev            # http://localhost:5173
npm run build          # typecheck + build produksi
npm run lint
```

## Struktur

```
src/
  auth/           useMe/useLogin/useLogout, useCan(permission), RequireAuth, GuestOnly, RequirePermission
  components/
    layout/       AppLayout (sidebar + topbar + breadcrumb), PageHeader
    ui/           Button, Field/Input/Select/Switch, MoneyInput, Modal, Badge, FilterChips,
                  Pagination, Empty/Error/Forbidden state, Toast, ConfirmDialog
  features/
    cars/         Armada: grid kartu, filter status, modal tambah/edit, detail + galeri foto
    customers/    Pelanggan: tabel, filter risiko, modal tambah/edit + upload KTP/SIM, riwayat
  pages/auth/     Login, Lupa password, Reset password (target tautan email)
  lib/            api (axios + CSRF Sanctum), format (Rupiah/WIB), phone, permissions, types
  index.css       Design tokens (sidebar #14262D, aksen #0F7B63, latar #F4F7F6)
```

## Catatan

- Auth: Sanctum SPA cookie. `ensureCsrfCookie()` dipanggil sebelum login, dan respons 419 otomatis diulang sekali dengan cookie CSRF baru.
- Hak akses: menu, tombol, dan route disembunyikan atau diblokir berdasarkan `permissions` dari `/auth/me`. Server tetap menjadi penentu akhir (Policy).
- Validasi: Zod di klien meniru aturan server; error 422 dari server dipetakan ke field form lewat `applyServerErrors`.
- Filter dan halaman disimpan di URL (`?status=servis&page=2`), jadi bisa dibagikan dan tetap ada setelah refresh.
- Dokumen KTP/SIM ditampilkan memakai signed URL dari API yang berlaku 10 menit.
