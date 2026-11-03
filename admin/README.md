# RentalPro: Admin Web

React + TypeScript + Vite. Saat ini masih scaffold dasar (Phase 1).
UI admin (layout sidebar, login, reset password, halaman data) dibangun di phase berikutnya.

```bash
npm install
npm run dev     # http://localhost:5173
```

Admin Web memakai **Sanctum SPA cookie auth**:
1. `GET  {API}/sanctum/csrf-cookie` (sekali, sebelum login)
2. `POST {API}/api/v1/auth/login` dengan header `X-XSRF-TOKEN` (axios mengirimnya otomatis dari cookie `XSRF-TOKEN`) dan `withCredentials: true`
3. `GET  {API}/api/v1/auth/me` untuk role + permission

Halaman `/reset-password?token=...&email=...` harus tersedia di app ini: tautan email reset kata sandi mengarah ke sini.
