import { useState } from 'react'
import { Link } from 'react-router-dom'
import { PageHeader } from '../../../components/PageHeader'
import {
  confirmDailySale,
  createDailySale,
  type DailySale,
} from '../api/dailySalesApi'
import { DailySalePreviewTable } from '../components/DailySalePreviewTable'

export function NewDailySalePage() {
  const [salesDate, setSalesDate] = useState(() => {
    const d = new Date()
    d.setDate(d.getDate() - 1)
    const yyyy = d.getFullYear()
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    return `${yyyy}-${mm}-${dd}`
  })
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<DailySale | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const validLineCount = preview?.lines.filter((line) => !line.error_message).length ?? 0
  const errorLineCount = preview?.lines.filter((line) => line.error_message).length ?? 0
  const canConfirm =
    preview !== null && !preview.has_errors && preview.status === 'DRAFT' && !loading

  async function handlePreviewSubmit(event: React.FormEvent) {
    event.preventDefault()

    if (!file) {
      setError('Chưa có file bán hàng.')
      return
    }

    setLoading(true)
    setError(null)
    setMessage(null)

    try {
      const result = await createDailySale({ salesDate, file })
      setPreview(result)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tải được preview phiếu bán.')
    } finally {
      setLoading(false)
    }
  }

  async function handleConfirm() {
    if (!preview) {
      return
    }

    setLoading(true)
    setError(null)
    setMessage(null)

    try {
      const confirmed = await confirmDailySale(preview.id)
      setPreview(confirmed)
      setMessage('Đã xác nhận và trừ tồn.')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tải được preview phiếu bán.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="shell inv-shell">
      <PageHeader
        eyebrow="Daily sales"
        title="Phiếu bán hằng ngày"
        summary="Import CSV bán hàng, xác nhận một lần để trừ tồn bằng ledger SALE."
        actions={
          <Link className="link-button" to="/daily-sales">Danh sách</Link>
        }
      />
      <header className="inv-page-header">
        <p className="inv-import-help">File phải có cột sku_code và quantity_sold.</p>
      </header>

      <section className="inv-panel inv-import-panel" aria-label="Tải file doanh thu bán hàng">
        <form className="inv-import-form" onSubmit={handlePreviewSubmit}>
          <div className="inv-form-group" style={{ marginBottom: '1rem', display: 'flex', gap: '1rem', alignItems: 'center' }}>
            <label htmlFor="sales-date" style={{ fontWeight: 'normal', margin: 0 }}>
              Ngày bán
              <input
                id="sales-date"
                type="date"
                value={salesDate}
                max={new Date().toISOString().split('T')[0]}
                onChange={(event) => {
                  setSalesDate(event.target.value)
                  setError(null)
                  setMessage(null)
                }}
                style={{ marginLeft: '0.5rem', padding: '0.25rem 0.5rem', borderRadius: '4px', border: '1px solid #ccc' }}
              />
            </label>
          </div>
          <label htmlFor="daily-sale-file">
            Chọn file CSV
            <input
              id="daily-sale-file"
              type="file"
              accept=".csv,text/csv"
              onChange={(event) => {
                setFile(event.target.files?.[0] ?? null)
                setError(null)
                setMessage(null)
              }}
            />
          </label>
          <div className="inv-import-actions">
            <button type="submit" disabled={loading}>
              {loading ? 'Đang tải...' : 'Xem trước'}
            </button>
            <button type="button" disabled={!canConfirm} onClick={() => void handleConfirm()}>
              Xác nhận trừ tồn
            </button>
          </div>
        </form>

        {!preview ? <p className="inv-empty">Chưa có file bán hàng.</p> : null}
        {error ? <p className="form-error">{error}</p> : null}
        {message ? <p className="inv-import-success">{message}</p> : null}
        {preview?.has_errors ? (
          <p className="inv-import-warning">Phiếu còn lỗi, vui lòng sửa CSV rồi tải lại.</p>
        ) : null}
      </section>

      {preview ? (
        <section className="inv-panel" aria-label="Preview phiếu bán">
          <div className="inv-import-summary">
            <strong>{preview.file_name}</strong>
            <span>Ngày bán: {preview.sales_date}</span>
            <span>
              Trạng thái:{' '}
              {preview.status === 'CONFIRMED'
                ? 'Đã xác nhận'
                : preview.status === 'CANCELLED'
                  ? 'Đã hủy'
                  : 'Nháp'}
            </span>
            <span>{validLineCount.toString()} dòng hợp lệ</span>
            <span>{errorLineCount.toString()} dòng lỗi</span>
          </div>
          <DailySalePreviewTable lines={preview.lines} />
        </section>
      ) : null}
    </main>
  )
}
