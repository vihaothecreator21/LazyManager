import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { login } from '../api/authApi'
import { loginSchema, type LoginFormData } from '../schemas/loginSchema'
import type { LoginApiError, LoginResponse } from '../types'

type LoginFormProps = {
  onLoginSuccess: (response: LoginResponse) => void
}

export function LoginForm({ onLoginSuccess }: LoginFormProps) {
  const [apiError, setApiError] = useState<string | null>(null)
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: 'manager@example.com',
      password: '',
    },
  })

  async function onSubmit(data: LoginFormData) {
    setApiError(null)

    try {
      const response = await login(data)
      onLoginSuccess(response)
    } catch (error) {
      const loginError = error as LoginApiError
      setApiError(loginError.message)
    }
  }

  return (
    <form className="login-form" onSubmit={handleSubmit(onSubmit)}>
      <label>
        <span>Email</span>
        <input
          type="email"
          autoComplete="email"
          aria-invalid={errors.email ? 'true' : 'false'}
          {...register('email')}
        />
        {errors.email ? <small>{errors.email.message}</small> : null}
      </label>

      <label>
        <span>Mật khẩu</span>
        <input
          type="password"
          autoComplete="current-password"
          aria-invalid={errors.password ? 'true' : 'false'}
          {...register('password')}
        />
        {errors.password ? <small>{errors.password.message}</small> : null}
      </label>

      {apiError ? (
        <div className="form-error" role="alert">
          {apiError}
        </div>
      ) : null}

      <button type="submit" disabled={isSubmitting}>
        {isSubmitting ? 'Đang đăng nhập' : 'Đăng nhập'}
      </button>
    </form>
  )
}
