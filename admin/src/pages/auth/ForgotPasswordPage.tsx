import { zodResolver } from '@hookform/resolvers/zod'
import { ArrowLeft, MailCheck } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link } from 'react-router'
import { z } from 'zod'
import { useForgotPassword } from '@/auth/useAuth'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, Input } from '@/components/ui/Field'
import { errorMessage } from '@/lib/api'
import { AuthShell } from './AuthShell'

const schema = z.object({ email: z.string().trim().min(1, 'Email wajib diisi.').email('Format email tidak valid.') })
type Values = z.infer<typeof schema>

export function ForgotPasswordPage() {
  const forgot = useForgotPassword()
  const [sentTo, setSentTo] = useState<string | null>(null)
  const { register, handleSubmit, formState: { errors } } = useForm<Values>({ resolver: zodResolver(schema), defaultValues: { email: '' } })

  const onSubmit = handleSubmit(({ email }) => forgot.mutate(email, { onSuccess: () => setSentTo(email) }))

  if (sentTo) {
    return (
      <AuthShell title="Periksa email Anda" subtitle="Tautan atur ulang kata sandi sedang dikirim.">
        <div className="card p-5">
          <MailCheck className="size-8 text-brand-500" />
          <p className="mt-3 text-sm text-ink-soft">
            Jika <strong className="text-ink">{sentTo}</strong> terdaftar, Anda akan menerima email berisi tautan untuk mengatur ulang kata sandi.
            Tautan berlaku 60 menit. Periksa juga folder Spam.
          </p>
        </div>
        <div className="mt-6 flex flex-col gap-3">
          <Button variant="secondary" onClick={() => setSentTo(null)}>Kirim ulang ke email lain</Button>
          <Link to="/login" className="inline-flex items-center justify-center gap-2 text-sm font-semibold text-brand-600 hover:underline">
            <ArrowLeft className="size-4" /> Kembali ke halaman masuk
          </Link>
        </div>
      </AuthShell>
    )
  }

  return (
    <AuthShell title="Lupa kata sandi" subtitle="Masukkan email akun Anda. Kami akan mengirim tautan untuk membuat kata sandi baru.">
      <form onSubmit={onSubmit} noValidate className="space-y-4">
        {forgot.isError && <Alert>{errorMessage(forgot.error)}</Alert>}
        <Field label="Email" error={errors.email?.message}>
          {(a) => <Input {...a} {...register('email')} type="email" autoComplete="email" placeholder="nama@usaha.com" invalid={!!errors.email} />}
        </Field>
        <Button type="submit" className="w-full" loading={forgot.isPending}>Kirim tautan atur ulang</Button>
        <Link to="/login" className="flex items-center justify-center gap-2 pt-2 text-sm font-semibold text-brand-600 hover:underline">
          <ArrowLeft className="size-4" /> Kembali ke halaman masuk
        </Link>
      </form>
    </AuthShell>
  )
}
