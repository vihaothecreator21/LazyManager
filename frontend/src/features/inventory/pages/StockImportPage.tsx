import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import {
  confirmStockImport,
  uploadStockImport,
  type StockImport,
} from '../api/stockImportApi'
import { StockImportPreviewTable } from '../components/StockImportPreviewTable'

export function StockImportPage() {
  const { session, logout } = useAuth()
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<StockImport | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  const validLineCount = preview?.lines.filter((line) => !line.error_message).length ?? 0
  const errorLineCount = preview?.lines.filter((line) => line.error_message).length ?? 0
  const canConfirm =
    preview !== null && !preview.has_errors && preview.status !== 'CONFIRMED' && !loading

  async function handlePreviewSubmit(event: React.FormEvent) {
    event.preventDefault()

    if (!file) {
      setError('Chưa có file nhập tồn.')
      return
    }

    setLoading(true)
    setError(null)
    setMessage(null)

    try {
      setPreview(await uploadStockImport(file))
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tải được preview nhập tồn.')
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
      const confirmed = await confirmStockImport(preview.id)
      setPreview(confirmed)
      setMessage('Đã đồng bộ tồn kho.')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tải được preview nhập tồn.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <main className="shell inv-shell">
      <nav className="inv-topnav" aria-label="Điều hướng chính">
        <Link className="inv-brand" to="/">LazyManager</Link>
        <div className="inv-topnav-links">
          <Link to="/schedule">Lịch làm việc</Link>
          <Link to="/products">Sản phẩm</Link>
          <Link to="/inventory">Tồn kho</Link>
          <Link to="/inventory/import" aria-current="page">Nhập tồn kho</Link>
          <Link to="/employees">Nhân viên</Link>
          {session ? (
            <button type="button" className="nav-logout" onClick={() => void logout()}>
              Đăng xuất
            </button>
          ) : null}
        </div>
      </nav>

      <header className="inv-page-header">
        <div className="inv-page-title-row">
          <h1 className="inv-page-title">Nhập tồn kho</h1>
          <Link className="link-button" to="/inventory">Tồn kho</Link>
        </div>
        <p className="inv-import-help">File phải có cột sku_code và quantity.</p>
      </header>

      <section className="inv-panel inv-import-panel" aria-label="Tải file nhập tồn">
        <form className="inv-import-form" onSubmit={handlePreviewSubmit}>
          <label htmlFor="stock-import-file">
            Chọn file CSV
            <input
              id="stock-import-file"
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
              Xác nhận đồng bộ
            </button>
          </div>
        </form>

        {!preview ? <p className="inv-empty">Chưa có file nhập tồn.</p> : null}
        {error ? <p className="form-error">{error}</p> : null}
        {message ? <p className="inv-import-success">{message}</p> : null}
        {preview?.has_errors ? (
          <p className="inv-import-warning">File còn lỗi, vui lòng sửa CSV rồi tải lại.</p>
        ) : null}
      </section>

      {preview ? (
        <section className="inv-panel" aria-label="Preview nhập tồn">
          <div className="inv-import-summary">
            <strong>{preview.file_name}</strong>
            <span>{preview.status === 'CONFIRMED' ? 'Đã xác nhận' : 'Đang xem trước'}</span>
            <span>{validLineCount.toString()} dòng hợp lệ</span>
            <span>{errorLineCount.toString()} dòng lỗi</span>
          </div>
          <StockImportPreviewTable lines={preview.lines} />
        </section>
      ) : null}
    </main>
  )
}
