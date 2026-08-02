import { useEffect, useRef, useState } from 'react'
import type { FormEvent } from 'react'
import type { ProductSku, SkuPayload } from '../api/inventoryApi'

interface SkuFormProps {
  sku?: ProductSku
  inline?: boolean
  onSubmit: (payload: SkuPayload) => Promise<void>
  onCancel: () => void
}

export function SkuForm({ sku, inline = false, onSubmit, onCancel }: SkuFormProps) {
  const [code, setCode] = useState(sku?.sku_code ?? '')
  const [size, setSize] = useState(sku?.size ?? '')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const codeRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    if (inline) codeRef.current?.focus()
  }, [inline])

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)

    try {
      await onSubmit({ sku_code: code, size })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Lỗi không xác định.')
      setSaving(false)
    }
  }

  if (inline) {
    return (
      <form onSubmit={(e) => void handleSubmit(e)} className="inv-add-sku-form">
        <input
          ref={codeRef}
          placeholder="Mã SKU"
          value={code}
          onChange={(e) => setCode(e.target.value)}
          required
          disabled={saving}
          aria-label="Mã SKU mới"
          style={{ minWidth: '120px' }}
        />
        <input
          placeholder="Kích thước"
          value={size}
          onChange={(e) => setSize(e.target.value)}
          required
          disabled={saving}
          aria-label="Kích thước"
          style={{ minWidth: '80px' }}
        />
        {error ? <span className="inv-inline-error">{error}</span> : null}
        <button type="submit" disabled={saving}>
          {saving ? '...' : 'Lưu'}
        </button>
        <button type="button" className="btn-secondary" onClick={onCancel} disabled={saving}>
          Hủy
        </button>
      </form>
    )
  }

  return (
    <form onSubmit={(e) => void handleSubmit(e)} className="inv-dialog-form">
      <label htmlFor="edit-sku-code">
        Mã SKU
        <input
          id="edit-sku-code"
          value={code}
          onChange={(e) => setCode(e.target.value)}
          required
          disabled={saving}
        />
      </label>
      <label htmlFor="edit-sku-size">
        Kích thước
        <input
          id="edit-sku-size"
          value={size}
          onChange={(e) => setSize(e.target.value)}
          required
          disabled={saving}
        />
      </label>
      {error ? <p className="form-error">{error}</p> : null}
      <div className="inv-dialog-actions">
        <button type="submit" disabled={saving}>
          {saving ? 'Đang lưu...' : 'Lưu'}
        </button>
        <button type="button" className="btn-secondary" onClick={onCancel} disabled={saving}>
          Hủy
        </button>
      </div>
    </form>
  )
}
