import { zodResolver } from '@hookform/resolvers/zod'
import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Check, KeyRound, Pencil, Plus, Power, UserCog } from 'lucide-react'
import { Fragment, useEffect, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { PageHeader } from '@/components/layout/PageHeader'
import { Avatar } from '@/components/ui/Avatar'
import { Badge, type Tone } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Alert, Skeleton } from '@/components/ui/Feedback'
import { Field, Input, Select } from '@/components/ui/Field'
import { FilterChips } from '@/components/ui/FilterChips'
import { Modal } from '@/components/ui/Modal'
import { Pagination } from '@/components/ui/Pagination'
import { SearchInput } from '@/components/ui/SearchInput'
import { EmptyState, ErrorState } from '@/components/ui/States'
import { useToast } from '@/components/ui/Toast'
import { api, errorMessage, errorStatus } from '@/lib/api'
import { cn } from '@/lib/cn'
import { applyServerErrors } from '@/lib/form'
import { dateTimeWib, initialsOf, phone } from '@/lib/format'
import { isValidIndonesianPhone } from '@/lib/phone'
import type { Paginated } from '@/lib/types'
import { useDebounce } from '@/lib/useDebounce'

type Role = 'owner' | 'admin' | 'staff'
interface AppUser { id: number; name: string; email: string; phone: string | null; is_active: boolean; role: Role; role_label: string; is_self: boolean; last_login_at: string | null }
const roleTone: Record<Role, Tone> = { owner: 'teal', admin: 'blue', staff: 'gray' }
const ROLE_HELP: Record<Role, string> = {
  owner: 'Semua akses termasuk pengaturan, pengguna, dan pengecualian blacklist.',
  admin: 'Operasional penuh dan laporan, tanpa pengaturan sistem & kelola pengguna.',
  staff: 'Sewa, serah terima, kembali, pembayaran masuk, dan pelanggan. Tanpa hapus & laporan.',
}

const GROUPS: Record<string, string> = {
  dashboard: 'Dashboard', rental: 'Transaksi', payment: 'Pembayaran', car: 'Armada', customer: 'Pelanggan', driver: 'Driver',
  maintenance: 'Perawatan', booking_request: 'Permintaan booking', report: 'Laporan', setting: 'Pengaturan', landing: 'Landing',
  user: 'Pengguna', activity: 'Log aktivitas',
}
const ACTIONS: Record<string, string> = {
  view: 'Lihat', create: 'Tambah', update: 'Ubah', delete: 'Hapus', cancel: 'Batalkan', handover: 'Serah terima', return: 'Kembali',
  discount: 'Beri diskon', waive_fee: 'Hapus denda', override_blacklist: 'Pengecualian blacklist', refund: 'Refund', publish: 'Tampilkan di landing',
  change_risk: 'Ubah status risiko', view_documents: 'Lihat dokumen KTP/SIM', convert: 'Jadikan sewa', operational: 'Laporan operasional',
  export: 'Ekspor', manage: 'Kelola',
}

const baseSchema = {
  name: z.string().trim().min(2, 'Nama minimal 2 karakter.').max(120),
  email: z.string().trim().min(1, 'Email wajib diisi.').email('Format email tidak valid.'),
  phone: z.string().trim().refine((v) => v === '' || isValidIndonesianPhone(v), 'Gunakan nomor HP Indonesia, mis. 0812xxxx.'),
  role: z.enum(['owner', 'admin', 'staff']),
}
const createSchema = z.object({ ...baseSchema, password: z.string().min(8, 'Minimal 8 karakter.').regex(/[A-Za-z]/, 'Harus mengandung huruf.').regex(/\d/, 'Harus mengandung angka.') })
const editSchema = z.object(baseSchema)
type FormValues = z.infer<typeof createSchema>

function UserForm({ open, user, onClose }: { open: boolean; user: AppUser | null; onClose: () => void }) {
  const qc = useQueryClient()
  const toast = useToast()
  const [formError, setFormError] = useState<string | null>(null)
  const { register, handleSubmit, reset, setError, watch, formState: { errors } } = useForm<FormValues>({ resolver: zodResolver((user ? editSchema : createSchema) as unknown as typeof createSchema) })
  useEffect(() => {
    if (open) { reset({ name: user?.name ?? '', email: user?.email ?? '', phone: user?.phone ?? '', role: user?.role ?? 'staff', password: '' }); setFormError(null) }
  }, [open, user, reset])

  const save = useMutation({
    mutationFn: async (v: FormValues) => (user
      ? await api.put<{ message: string }>(`/users/${user.id}`, { name: v.name, email: v.email, phone: v.phone || null, role: v.role })
      : await api.post<{ message: string }>('/users', { ...v, phone: v.phone || null })).data,
    onSuccess: (r) => { toast(r.message); qc.invalidateQueries({ queryKey: ['users'] }); qc.invalidateQueries({ queryKey: ['roles'] }); onClose() },
    onError: (e) => {
      const orphan = applyServerErrors(e, setError, ['name', 'email', 'phone', 'role', 'password'])
      if (errorStatus(e) !== 422 || orphan) setFormError(orphan ?? errorMessage(e))
    },
  })
  const role = watch('role') as Role | undefined

  return (
    <Modal open={open} onClose={onClose} dismissible={!save.isPending} title={user ? 'Ubah pengguna' : 'Tambah pengguna'} description="Pengguna masuk dengan email & kata sandi; aksesnya ditentukan oleh role."
      footer={<><Button variant="secondary" onClick={onClose}>Batal</Button><Button type="submit" form="user-form" loading={save.isPending}>Simpan</Button></>}>
      <form id="user-form" noValidate onSubmit={handleSubmit((v) => save.mutate(v))} className="grid gap-4 sm:grid-cols-2">
        {formError && <div className="sm:col-span-2"><Alert>{formError}</Alert></div>}
        <Field label="Nama" required error={errors.name?.message}>{(a) => <Input {...a} {...register('name')} invalid={!!errors.name} />}</Field>
        <Field label="Email" required error={errors.email?.message}>{(a) => <Input {...a} type="email" {...register('email')} invalid={!!errors.email} />}</Field>
        <Field label="Nomor HP" error={errors.phone?.message}>{(a) => <Input {...a} type="tel" {...register('phone')} invalid={!!errors.phone} />}</Field>
        <Field label="Role" required error={errors.role?.message} hint={user?.is_self ? 'Role akun sendiri tidak bisa diubah.' : role ? ROLE_HELP[role] : undefined}>
          {(a) => <Select {...a} {...register('role')} disabled={user?.is_self}><option value="staff">Staf</option><option value="admin">Admin</option><option value="owner">Pemilik</option></Select>}
        </Field>
        {!user && (
          <Field label="Kata sandi awal" required className="sm:col-span-2" hint="Minimal 8 karakter, huruf & angka. Sampaikan ke pengguna; ia bisa menggantinya lewat Lupa kata sandi." error={errors.password?.message}>
            {(a) => <Input {...a} type="password" autoComplete="new-password" {...register('password')} invalid={!!errors.password} />}
          </Field>
        )}
      </form>
    </Modal>
  )
}

function RoleMatrix() {
  const q = useQuery({ queryKey: ['roles'], queryFn: async () => (await api.get<{ data: { roles: { name: Role; label: string; users_count: number; permissions: string[] }[]; permissions: string[] } }>('/roles')).data.data })
  if (q.isLoading) return <Skeleton className="h-64" />
  if (q.isError || !q.data) return <ErrorState error={q.error} onRetry={() => q.refetch()} />
  const grouped = q.data.permissions.reduce<Record<string, string[]>>((acc, p) => { const g = p.split('.')[0]; (acc[g] ??= []).push(p); return acc }, {})

  return (
    <section className="card overflow-hidden">
      <div className="border-b border-line px-5 py-3">
        <h2 className="text-sm font-bold text-ink">Hak akses per role</h2>
        <p className="text-xs text-ink-muted">Ditentukan sistem; diperiksa di server untuk setiap aksi.</p>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[560px] text-sm">
          <thead>
            <tr className="border-b border-line text-left text-2xs font-bold tracking-[0.12em] text-ink-muted uppercase">
              <th className="px-5 py-3">Izin</th>
              {q.data.roles.map((r) => <th key={r.name} className="px-3 py-3 text-center">{r.label}<span className="block text-2xs font-medium normal-case tracking-normal">{r.users_count} pengguna</span></th>)}
            </tr>
          </thead>
          <tbody>
            {Object.entries(grouped).map(([group, perms]) => (
              <Fragment key={group}>
                <tr className="bg-canvas/70"><td colSpan={4} className="px-5 py-1.5 text-2xs font-bold tracking-wide text-ink-soft uppercase">{GROUPS[group] ?? group}</td></tr>
                {perms.map((p) => (
                  <tr key={p} className="border-b border-line/60">
                    <td className="px-5 py-2 text-ink-soft">{ACTIONS[p.split('.').slice(1).join('.')] ?? p}</td>
                    {q.data.roles.map((r) => (
                      <td key={r.name} className="px-3 py-2 text-center">
                        {r.permissions.includes(p) ? <Check className="mx-auto size-4 text-brand-600" aria-label="Ya" /> : <span className="text-ink-muted/50" aria-label="Tidak">—</span>}
                      </td>
                    ))}
                  </tr>
                ))}
              </Fragment>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  )
}

export function UsersPage() {
  const qc = useQueryClient()
  const toast = useToast()
  const [role, setRole] = useState<'' | Role>('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const q = useDebounce(search.trim())
  const [form, setForm] = useState<{ open: boolean; user: AppUser | null }>({ open: false, user: null })
  const [confirm, setConfirm] = useState<{ kind: 'status' | 'reset'; user: AppUser } | null>(null)

  const users = useQuery({
    queryKey: ['users', { role, q, page }],
    queryFn: async () => (await api.get<Paginated<AppUser>>('/users', { params: { role: role || undefined, search: q || undefined, page } })).data,
    placeholderData: keepPreviousData,
  })

  const act = useMutation({
    mutationFn: async ({ kind, user }: { kind: 'status' | 'reset'; user: AppUser }) => (kind === 'status'
      ? await api.patch<{ message: string }>(`/users/${user.id}/status`, { is_active: !user.is_active })
      : await api.post<{ message: string }>(`/users/${user.id}/force-password-reset`)).data,
    onSuccess: (r) => { toast(r.message); qc.invalidateQueries({ queryKey: ['users'] }); qc.invalidateQueries({ queryKey: ['roles'] }); setConfirm(null) },
    onError: (e) => { toast(errorMessage(e), 'error'); setConfirm(null) },
  })

  const rows = users.data?.data ?? []

  return (
    <>
      <PageHeader eyebrow="Sistem" title="Pengguna & Role" subtitle="Kelola akun tim, role, dan akses. Pengguna tidak dihapus agar jejak audit tetap utuh." action={<Button onClick={() => setForm({ open: true, user: null })}><Plus className="size-4" /> Tambah Pengguna</Button>} />

      <div className="mb-5 flex flex-col gap-3">
        <SearchInput label="Cari pengguna" placeholder="Cari nama atau email…" value={search} onChange={(v) => { setSearch(v); setPage(1) }} />
        <FilterChips<'' | Role> label="Filter role" value={role} onChange={(v) => { setRole(v); setPage(1) }} options={[{ value: '', label: 'Semua' }, { value: 'owner', label: 'Pemilik' }, { value: 'admin', label: 'Admin' }, { value: 'staff', label: 'Staf' }]} />
      </div>

      <div className="card mb-6 overflow-hidden">
        {users.isLoading ? <div className="space-y-3 p-5"><Skeleton className="h-10" /><Skeleton className="h-10" /></div>
          : users.isError ? <ErrorState error={users.error} onRetry={() => users.refetch()} />
            : rows.length === 0 ? <EmptyState icon={<UserCog className="size-7" />} title="Tidak ada pengguna" description="Ubah filter atau tambah pengguna baru." />
              : (
                <ul className={cn('divide-y divide-line', users.isPlaceholderData && 'opacity-60')}>
                  {rows.map((u) => (
                    <li key={u.id} className={cn('flex flex-wrap items-center gap-3 px-5 py-3', !u.is_active && 'bg-canvas/60')}>
                      <Avatar initials={initialsOf(u.name)} className="size-9" />
                      <div className="min-w-0 flex-1">
                        <p className="flex flex-wrap items-center gap-2 font-semibold text-ink">
                          {u.name}{u.is_self && <span className="text-2xs font-normal text-ink-muted">(Anda)</span>}
                          <Badge tone={roleTone[u.role]} dot={false}>{u.role_label}</Badge>
                          {!u.is_active && <Badge tone="red">Nonaktif</Badge>}
                        </p>
                        <p className="truncate text-xs text-ink-muted">{u.email}{u.phone && ` · ${phone(u.phone)}`} · login terakhir {u.last_login_at ? dateTimeWib(u.last_login_at) : 'belum pernah'}</p>
                      </div>
                      <div className="flex flex-wrap gap-1.5">
                        <Button size="sm" variant="secondary" onClick={() => setForm({ open: true, user: u })} aria-label={`Ubah ${u.name}`}><Pencil className="size-3.5" /> Ubah</Button>
                        {!u.is_self && u.is_active && <Button size="sm" variant="ghost" onClick={() => setConfirm({ kind: 'reset', user: u })}><KeyRound className="size-3.5" /> Reset sandi</Button>}
                        {!u.is_self && <Button size="sm" variant="ghost" className={u.is_active ? 'text-red-600' : 'text-brand-700'} onClick={() => setConfirm({ kind: 'status', user: u })}><Power className="size-3.5" /> {u.is_active ? 'Nonaktifkan' : 'Aktifkan'}</Button>}
                      </div>
                    </li>
                  ))}
                </ul>
              )}
        {users.data && rows.length > 0 && <div className="border-t border-line px-4 pb-4"><Pagination meta={users.data.meta} onPage={setPage} /></div>}
      </div>

      <RoleMatrix />

      <UserForm open={form.open} user={form.user} onClose={() => setForm({ open: false, user: null })} />
      <ConfirmDialog
        open={!!confirm}
        onClose={() => setConfirm(null)}
        loading={act.isPending}
        title={confirm?.kind === 'reset' ? 'Paksa reset kata sandi?' : confirm?.user.is_active ? 'Nonaktifkan pengguna?' : 'Aktifkan pengguna?'}
        confirmLabel={confirm?.kind === 'reset' ? 'Ya, reset' : confirm?.user.is_active ? 'Ya, nonaktifkan' : 'Ya, aktifkan'}
        description={confirm?.kind === 'reset'
          ? <>Kata sandi <strong>{confirm.user.name}</strong> langsung tidak berlaku, ia keluar dari semua perangkat, dan menerima email berisi tautan untuk membuat kata sandi baru.</>
          : confirm?.user.is_active
            ? <><strong>{confirm.user.name}</strong> tidak bisa masuk lagi dan langsung keluar dari semua perangkat. Data dan riwayatnya tetap tersimpan.</>
            : <><strong>{confirm?.user.name}</strong> bisa masuk kembali dengan kata sandinya.</>}
        onConfirm={() => confirm && act.mutate(confirm)}
      />
    </>
  )
}
