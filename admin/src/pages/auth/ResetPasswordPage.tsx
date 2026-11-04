import { zodResolver } from '@hookform/resolvers/zod'
import { CheckCircle2 } from 'lucide-react'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Link, useSearchParams } from 'react-router'
import { z } from 'zod'
import { useResetPassword } from '@/auth/useAuth'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/ui/Feedback'
import { Field, Input } from '@/components/ui/Field'
import { errorMessage, errorStatus } from '@/lib/api'
import { applyServerErrors } from '@/lib/form'
import { AuthShell } from './AuthShell'

const schema = z
  .object({
    password: z.string().min(8, 'Kata sandi minimal 8 karakter.')
      .regex(/[A-Za-z]/, 'Kata sandi harus mengandung huruf.')
      .regex(/\d/, 'Kata sandi harus mengandung angka.'),
    password_confirmation: z.string().min(1, 'Ulangi kata sandi baru.'),
  })
  .refine((v) => v.password === v.password_confirmation, { path: ['password_confirmation'], message: 'Konfirmasi kata sandi tidak cocok.' })
type Values = z.infer<typeof schema>

export function ResetPasswordPage() {
  const [params] = useSearchParams()
  const token = params.get('token') ?? ''
  const email = params.get('email') ?? ''
  const reset = useResetPassword()
  const [done, setDone] = useState<string | null>(null)
  const [formError, setFormError] = useState<string | null>(null)

  const { register, handleSubmit, setError, formState: { errors } } = useForm<Values>({
    resolver: zodResolver(schema),
    defaultValues: { password: '', password_confirmation: '' },
  })

  if (!token || !email) {
    return (
      <AuthShell title="Tautan tidak valid" subtitle="Tautan atur ulang kata sandi tidak lengkap.">
        <Alert>Buka kembali tautan dari email, atau minta tautan baru.</Alert>
        <Link to="/lupa-password" className="mt-6 inline-block text-sm font-semibold text-brand-600 hover:underline">Minta tautan baru</Link>
      </AuthShell>
    )
  }

  if (done) {
    return (
      <AuthShell title="Kata sandi diperbarui" subtitle={done}>
        <CheckCircle2 className="size-10 text-brand-500" />
        <Link to="/login" className="mt-6 inline-flex h-10 w-full items-center justify-center rounded-[10px] bg-brand-500 text-sm font-semibold text-white hover:bg-brand-600">
          Masuk sekarang
        </Link>
      </AuthShell>
    )
  }

  const onSubmit = handleSubmit((values) => {
    setFormError(null)
    reset.mutate({ token, email, ...values }, {
      onSuccess: setDone,
      onError: (error) => {
        const orphan = applyServerErrors(error, setError, ['password', 'password_confirmation'])
        if (errorStatus(error) !== 422 || orphan) setFormError(orphan ?? errorMessage(error))
      },
    })
  })

  return (
    <AuthShell title="Buat kata sandi baru" subtitle={`Untuk akun ${email}`}>
      <form onSubmit={onSubmit} noValidate className="space-y-4">
        {formError && (
          <Alert>
            {formError}{' '}
            <Link to="/lupa-password" className="font-semibold underline">Minta tautan baru</Link>
          </Alert>
        )}
        <Field label="Kata sandi baru" hint="Minimal 8 karakter, berisi huruf dan angka." error={errors.password?.message}>
          {(a) => <Input {...a} {...register('password')} type="password" autoComplete="new-password" invalid={!!errors.password} />}
        </Field>
        <Field label="Ulangi kata sandi baru" error={errors.password_confirmation?.message}>
          {(a) => <Input {...a} {...register('password_confirmation')} type="password" autoComplete="new-password" invalid={!!errors.password_confirmation} />}
        </Field>
        <Button type="submit" className="w-full" loading={reset.isPending}>Simpan kata sandi</Button>
      </form>
    </AuthShell>
  )
}
