import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import {
  cancelDailySale,
  getDailySale,
  listDailySales,
  type DailySale,
  type DailySaleSummary,
  type PaginatedDailySales,
} from '../api/dailySalesApi'
import { DailySalePreviewTable } from '../components/DailySalePreviewTable'

export function DailySalesPage() {
  const { session, logout } = useAuth()
  const [data, setData] = useState<PaginatedDailySales | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [page, setPage] = useState(1)

  // Detail state
  const [selectedId, setSelectedId] = useState<number | null>(null)
  const [detail, setDetail] = useState<DailySale | null>(null)
  const [detailLoading, setDetailLoading] = useState(false)

  // Cancel action state
  const [showCancelId, setShowCancelId] = useState<number | null>(null)
  const [cancelReason, setCancelReason] = useState('')
  const [cancelError, setCancelError] = useState<string | null>(null)

  async function loadList(p: number) {
    setLoading(true)
    setError(null)
    try {
      const res = await listDailySales({ page: p })
      setData(res)
      setPage(p)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tải được danh sách phiếu bán.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    void loadList(1)
  }, [])

  async function handleSelectRow(id: number) {
    setSelectedId(id)
    setDetailLoading(true)
    setDetail(null)
    try {
      const res = await getDailySale(id)
      setDetail(res)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tải được chi tiết phiếu bán.')
    } finally {
      setDetailLoading(false)
    }
  }

  async function handleCancelSubmit(event: React.FormEvent, id: number) {
    event.preventDefault()
    if (!cancelReason.trim()) {
      setCancelError('Vui lòng nhập lý do hủy phiếu bán.')
      return
    }

    setCancelError(null)
    setLoading(true)
    try {
      const updated = await cancelDailySale(id, cancelReason)
      // Update detail if currently selected
      if (selectedId === id) {
        setDetail(updated)
      }
      // Reload list to update status
      await loadList(page)
      setShowCancelId(null)
      setCancelReason('')
    } catch (err) {
      setCancelError(err instanceof Error ? err.message : 'Không thể hủy phiếu bán.')
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
          <Link to="/daily-sales" aria-current="page">Phiếu bán</Link>
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
          <h1 className="inv-page-title">Phiếu bán</h1>
          <Link className="link-button" to="/daily-sales/new">Tạo phiếu bán</Link>
        </div>
      </header>

      {error ? <p className="form-error">{error}</p> : null}

      <section className="inv-panel" aria-label="Danh sách phiếu bán">
        {loading && !data ? <p className="inv-empty">Đang tải...</p> : null}

        {data && data.data.length === 0 ? (
          <p className="inv-empty">Chưa có phiếu bán hàng nào.</p>
        ) : null}

        {data && data.data.length > 0 ? (
          <div className="inv-table-wrap">
            <table className="inv-table">
              <thead>
                <tr>
                  <th>Ngày bán</th>
                  <th>Tên file</th>
                  <th>Số dòng</th>
                  <th>Trạng thái</th>
                  <th>Lỗi</th>
                  <th>Hành động</th>
                </tr>
              </thead>
              <tbody>
                {data.data.map((row: DailySaleSummary) => (
                  <tr
                    key={row.id}
                    className={selectedId === row.id ? 'inv-import-row-ok' : ''}
                    style={{ cursor: 'pointer' }}
                    onClick={() => void handleSelectRow(row.id)}
                  >
                    <td>{row.sales_date}</td>
                    <td>{row.file_name}</td>
                    <td>{row.lines_count}</td>
                    <td>
                      <span className={`inv-import-${row.status === 'CONFIRMED' ? 'ok' : row.status === 'CANCELLED' ? 'error' : 'warning'}`}>
                        {row.status === 'CONFIRMED'
                          ? 'Đã xác nhận'
                          : row.status === 'CANCELLED'
                            ? 'Đã hủy'
                            : 'Nháp'}
                      </span>
                    </td>
                    <td>
                      {row.has_errors ? (
                        <span className="inv-import-error">Có lỗi</span>
                      ) : (
                        <span className="inv-import-ok">Không</span>
                      )}
                    </td>
                    <td onClick={(e) => e.stopPropagation()}>
                      {row.status === 'CONFIRMED' ? (
                        <button
                          type="button"
                          className="link-button-danger"
                          style={{
                            padding: '2px 8px',
                            fontSize: '11px',
                            background: '#ffebeb',
                            color: '#d60000',
                            border: '1px solid #ffcccc',
                            borderRadius: '4px',
                          }}
                          onClick={() => {
                            setShowCancelId(row.id)
                            setCancelReason('')
                            setCancelError(null)
                          }}
                        >
                          Hủy phiếu
                        </button>
                      ) : (
                        '-'
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : null}

        {data && data.meta.last_page > 1 ? (
          <div className="inv-pagination" style={{ marginTop: '1rem', display: 'flex', gap: '0.5rem' }}>
            <button
              type="button"
              disabled={page <= 1 || loading}
              onClick={() => void loadList(page - 1)}
            >
              Trước
            </button>
            <span style={{ alignSelf: 'center' }}>
              Trang {page.toString()} / {data.meta.last_page.toString()}
            </span>
            <button
              type="button"
              disabled={page >= data.meta.last_page || loading}
              onClick={() => void loadList(page + 1)}
            >
              Sau
            </button>
          </div>
        ) : null}
      </section>

      {/* Cancel dialog/modal inline */}
      {showCancelId !== null ? (
        <div
          style={{
            position: 'fixed',
            top: 0,
            left: 0,
            right: 0,
            bottom: 0,
            background: 'rgba(0,0,0,0.4)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1000,
          }}
        >
          <div
            className="inv-panel"
            style={{
              width: '400px',
              padding: '1.5rem',
              background: '#fff',
              borderRadius: '8px',
              boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
            }}
          >
            <h3 style={{ marginTop: 0, marginBottom: '1rem', color: '#333' }}>Hủy phiếu bán</h3>
            <form onSubmit={(e) => void handleCancelSubmit(e, showCancelId)}>
              {cancelError ? <p className="form-error" style={{ marginBottom: '0.5rem' }}>{cancelError}</p> : null}
              <div className="inv-form-group" style={{ marginBottom: '1rem' }}>
                <label htmlFor="cancel-reason" style={{ display: 'block', marginBottom: '0.25rem' }}>
                  Lý do hủy
                </label>
                <input
                  id="cancel-reason"
                  type="text"
                  placeholder="Nhập lý do hủy..."
                  value={cancelReason}
                  onChange={(e) => setCancelReason(e.target.value)}
                  style={{
                    width: '100%',
                    padding: '0.5rem',
                    border: '1px solid #ccc',
                    borderRadius: '4px',
                    boxSizing: 'border-box',
                  }}
                />
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '0.5rem' }}>
                <button
                  type="button"
                  onClick={() => setShowCancelId(null)}
                  style={{
                    background: '#f0f0f0',
                    border: '1px solid #dcdcdc',
                    color: '#666',
                    padding: '0.5rem 1rem',
                    borderRadius: '4px',
                    cursor: 'pointer',
                  }}
                >
                  Bỏ qua
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  style={{
                    background: '#d60000',
                    border: '1px solid #a80000',
                    color: '#fff',
                    padding: '0.5rem 1rem',
                    borderRadius: '4px',
                    cursor: 'pointer',
                  }}
                >
                  Xác nhận hủy
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      {/* Detail preview panel */}
      {detailLoading ? (
        <section className="inv-panel" aria-label="Xem chi tiết phiếu bán">
          <p className="inv-empty">Đang tải chi tiết...</p>
        </section>
      ) : null}

      {detail ? (
        <section className="inv-panel" aria-label="Chi tiết phiếu bán">
          <div className="inv-import-summary" style={{ display: 'flex', flexDirection: 'column', gap: '4px', alignItems: 'flex-start' }}>
            <h2 style={{ fontSize: '1.25rem', margin: '0 0 8px 0' }}>Chi tiết phiếu</h2>
            <div><strong>File:</strong> {detail.file_name}</div>
            <div><strong>Ngày bán:</strong> {detail.sales_date}</div>
            <div>
              <strong>Trạng thái:</strong>{' '}
              <span className={`inv-import-${detail.status === 'CONFIRMED' ? 'ok' : detail.status === 'CANCELLED' ? 'error' : 'warning'}`}>
                {detail.status === 'CONFIRMED'
                  ? 'Đã xác nhận'
                  : detail.status === 'CANCELLED'
                    ? 'Đã hủy'
                    : 'Nháp'}
              </span>
            </div>
            {detail.status === 'CONFIRMED' && detail.confirmed_at ? (
              <div><strong>Ngày xác nhận:</strong> {detail.confirmed_at}</div>
            ) : null}
            {detail.status === 'CANCELLED' && detail.cancelled_at ? (
              <>
                <div><strong>Ngày hủy:</strong> {detail.cancelled_at}</div>
                <div><strong>Lý do hủy:</strong> {detail.cancel_reason}</div>
              </>
            ) : null}
          </div>
          <DailySalePreviewTable lines={detail.lines} />
        </section>
      ) : null}
    </main>
  )
}
