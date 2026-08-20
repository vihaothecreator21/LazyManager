import { useState } from 'react'
import type { FormEvent } from 'react'
import type { Product, ProductPayload } from '../api/inventoryApi'

interface ProductFormProps {
  product?: Product
  submitText: string
  busyText: string
  onSubmit: (payload: ProductPayload) => Promise<void>
  onCancel: () => void
}

export function ProductForm({ product, submitText, busyText, onSubmit, onCancel }: ProductFormProps) {
  const [code, setCode] = useState(product?.product_code ?? '')
  const [name, setName] = useState(product?.name ?? '')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)

    try {
      await onSubmit({ product_code: code, name })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Lỗi không xác định.')
      setSaving(false)
    }
  }

  return (
    <form
      id={product ? undefined : 'form-create-product'}
      className={product ? 'inv-dialog-form' : 'inv-create-form'}
      onSubmit={(e) => void handleSubmit(e)}
      aria-label={product ? 'Sửa sản phẩm' : 'Tạo sản phẩm mới'}
    >
      <label htmlFor={product ? 'edit-product-code' : 'new-product-code'}>
        Mã sản phẩm
        <input
          id={product ? 'edit-product-code' : 'new-product-code'}
          value={code}
          onChange={(e) => setCode(e.target.value)}
          required
          disabled={saving}
          autoFocus={!product}
        />
      </label>
      <label htmlFor={product ? 'edit-product-name' : 'new-product-name'}>
        Tên sản phẩm
        <input
          id={product ? 'edit-product-name' : 'new-product-name'}
          value={name}
          onChange={(e) => setName(e.target.value)}
          required
          disabled={saving}
        />
      </label>
      {error ? <p className="form-error">{error}</p> : null}
      <div className={product ? 'inv-dialog-actions' : 'inv-create-actions'}>
        <button type="submit" disabled={saving}>
          {saving ? busyText : submitText}
        </button>
        <button type="button" className="btn-secondary" onClick={onCancel} disabled={saving}>
          Hủy
        </button>
      </div>
    </form>
  )
}
