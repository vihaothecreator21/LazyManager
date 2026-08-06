import { useEffect, useRef, useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import { listInventory, type InventoryBalance } from '../api/inventoryApi'
import {
  createBorrowRecord,
  listBorrowRecords,
  returnBorrowRecord,
  type BorrowRecord,
  type BorrowRecordStatus,
} from '../api/borrowRecordsApi'

type StatusFilter = 'ALL' | BorrowRecordStatus

const statusOptions: Array<{ value: StatusFilter; label: string }> = [
  { value: 'ALL', label: 'Tất cả' },
  { value: 'BORROWED', label: 'Đang mượn' },
  { value: 'RETURNED', label: 'Đã trả' },
]

type BorrowForm = {
  skuId: number | null
  skuSearch: string
  quantity: string
  borrowerName: string
  borrowLocation: string
  note: string
}

const emptyBorrowForm: BorrowForm = {
  skuId: null,
  skuSearch: '',
  quantity: '1',
  borrowerName: '',
  borrowLocation: '',
  note: '',
}

function formatDate(value: string | null): string {
  if (!value) return '-'

  return new Intl.DateTimeFormat('vi-VN', {
    dateStyle: 'short',
    timeStyle: 'short',
  }).format(new Date(value))
}

function statusLabel(status: BorrowRecordStatus): string {
  return status === 'BORROWED' ? 'Đang mượn' : 'Đã trả'
}

function BorrowedSkeletonRows() {
  return (
    <>
      {Array.from({ length: 4 }, (_, index) => (
        <tr className="inv-skeleton-row" aria-hidden="true" key={index}>
          <td><span className="inv-skel" style={{ width: '150px' }} /></td>
          <td><span className="inv-skel" style={{ width: '58px' }} /></td>
          <td><span className="inv-skel" style={{ width: '130px' }} /></td>
          <td><span className="inv-skel" style={{ width: '160px' }} /></td>
          <td><span className="inv-skel" style={{ width: '112px' }} /></td>
          <td><span className="inv-skel" style={{ width: '82px' }} /></td>
          <td />
        </tr>
      ))}
    </>
  )
}

export function BorrowedPage() {
  const { session, logout } = useAuth()
  const [records, setRecords] = useState<BorrowRecord[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [status, setStatus] = useState<StatusFilter>('ALL')
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')

  const [form, setForm] = useState<BorrowForm>(emptyBorrowForm)
  const [selectedSku, setSelectedSku] = useState<InventoryBalance | null>(null)
  const [skuOptions, setSkuOptions] = useState<InventoryBalance[]>([])
  const [skuSearchLoading, setSkuSearchLoading] = useState(false)
  const [createError, setCreateError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const skuSearchRun = useRef(0)

  const [returningId, setReturningId] = useState<number | null>(null)
  const [returnNote, setReturnNote] = useState('')
  const [returnError, setReturnError] = useState<string | null>(null)
  const [returning, setReturning] = useState(false)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)

    listBorrowRecords({
      status: status === 'ALL' ? undefined : status,
      search: search || undefined,
    })
      .then((data) => {
        if (!cancelled) {
          setRecords(data)
          setLoading(false)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'Không tải được danh sách hàng mượn.')
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [search, status])

  useEffect(() => {
    const term = form.skuSearch.trim()
    const runId = skuSearchRun.current + 1
    skuSearchRun.current = runId

    if (term.length < 2 || selectedSku?.sku_code === form.skuSearch) {
      setSkuOptions([])
      setSkuSearchLoading(false)
      return
    }

    setSkuSearchLoading(true)
    const timer = window.setTimeout(() => {
      listInventory(term)
        .then((data) => {
          if (skuSearchRun.current === runId) {
            setSkuOptions(data.inventory.filter((item) => item.sku_active && item.product_active))
          }
        })
        .catch(() => {
          if (skuSearchRun.current === runId) {
            setSkuOptions([])
          }
        })
        .finally(() => {
          if (skuSearchRun.current === runId) {
            setSkuSearchLoading(false)
          }
        })
    }, 300)

    return () => window.clearTimeout(timer)
  }, [form.skuSearch, selectedSku?.sku_code])

  function handleSearchSubmit(event: FormEvent) {
    event.preventDefault()
    setSearch(searchInput.trim())
  }

  function updateForm(patch: Partial<BorrowForm>) {
    setForm((current) => ({ ...current, ...patch }))
  }

  function chooseSku(balance: InventoryBalance) {
    setSelectedSku(balance)
    setSkuOptions([])
    updateForm({ skuId: balance.sku_id, skuSearch: balance.sku_code })
  }

  async function handleCreateSubmit(event: FormEvent) {
    event.preventDefault()
    setCreateError(null)

    if (!form.skuId) {
      setCreateError('Vui lòng chọn SKU.')
      return
    }

    const quantity = Number.parseInt(form.quantity, 10)
    if (!Number.isInteger(quantity) || quantity <= 0) {
      setCreateError('Số lượng phải lớn hơn 0.')
      return
    }

    setCreating(true)
    try {
      const created = await createBorrowRecord({
        sku_id: form.skuId,
        quantity,
        borrower_name: form.borrowerName.trim(),
        borrow_location: form.borrowLocation.trim(),
        note: form.note.trim() || undefined,
      })
      setRecords((current) => [created, ...current])
      setForm(emptyBorrowForm)
      setSelectedSku(null)
      setSkuOptions([])
    } catch (err) {
      setCreateError(err instanceof Error ? err.message : 'Không tạo được phiếu mượn.')
    } finally {
      setCreating(false)
    }
  }

  async function handleReturnSubmit(event: FormEvent, record: BorrowRecord) {
    event.preventDefault()
    setReturnError(null)
    setReturning(true)

    try {
      const updated = await returnBorrowRecord(record.id, {
        return_note: returnNote.trim() || undefined,
      })
      setRecords((current) => current.map((item) => (item.id === updated.id ? updated : item)))
      setReturningId(null)
      setReturnNote('')
    } catch (err) {
      setReturnError(err instanceof Error ? err.message : 'Không trả được phiếu mượn.')
    } finally {
      setReturning(false)
    }
  }

  return (
    <main className="shell inv-shell borrowed-shell">
      <nav className="inv-topnav" aria-label="Điều hướng chính">
        <Link className="inv-brand" to="/">LazyManager</Link>
        <div className="inv-topnav-links">
          <Link to="/schedule">Lịch làm việc</Link>
          <Link to="/products">Sản phẩm</Link>
          <Link to="/inventory">Tồn kho</Link>
          <Link to="/inventory/import">Nhập tồn kho</Link>
          <Link to="/daily-sales">Phiếu bán</Link>
          <Link to="/borrowed" aria-current="page">Hàng mượn</Link>
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
          <h1 className="inv-page-title">Hàng mượn</h1>
        </div>

        <form className="inv-search-form" onSubmit={handleSearchSubmit} role="search" aria-label="Tìm kiếm hàng mượn">
          <label htmlFor="borrow-search" className="inv-search-label">
            Tìm kiếm
          </label>
          <div className="inv-search-row">
            <input
              id="borrow-search"
              type="search"
              placeholder="Tìm theo SKU, sản phẩm, người mượn hoặc nơi mượn"
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              aria-label="Tìm theo SKU, sản phẩm, người mượn hoặc nơi mượn"
            />
            <button type="submit">Tìm</button>
            {search ? (
              <button
                type="button"
                className="btn-secondary"
                onClick={() => {
                  setSearch('')
                  setSearchInput('')
                }}
              >
                Xóa bộ lọc
              </button>
            ) : null}
          </div>
        </form>
      </header>

      <section className="borrowed-layout">
        <aside className="inv-panel borrowed-create-panel" aria-labelledby="borrow-create-title">
          <h2 id="borrow-create-title">Ghi nhận mượn hàng</h2>
          <form className="borrowed-form" onSubmit={(event) => void handleCreateSubmit(event)}>
            {createError ? <p className="form-error">{createError}</p> : null}

            <label className="borrowed-sku-field" htmlFor="borrow-sku-search">
              Tìm sản phẩm / SKU
              <input
                id="borrow-sku-search"
                type="search"
                value={form.skuSearch}
                onChange={(event) => {
                  setSelectedSku(null)
                  updateForm({ skuId: null, skuSearch: event.target.value })
                }}
                placeholder="Nhập mã SKU hoặc tên sản phẩm"
                autoComplete="off"
              />
              {skuSearchLoading ? <span className="borrowed-field-note">Đang tìm...</span> : null}
              {skuOptions.length > 0 ? (
                <div className="borrowed-sku-options" role="listbox" aria-label="Kết quả tìm SKU">
                  {skuOptions.map((option) => (
                    <button
                      type="button"
                      className="borrowed-sku-option"
                      key={option.sku_id}
                      onClick={() => chooseSku(option)}
                    >
                      <strong>{option.sku_code}</strong>
                      <span>{option.product_name} · {option.size} · Tồn {option.quantity.toString()}</span>
                    </button>
                  ))}
                </div>
              ) : null}
            </label>

            {selectedSku ? (
              <div className="borrowed-selected-sku" role="status">
                <strong>{selectedSku.sku_code}</strong>
                <span>{selectedSku.product_name} · {selectedSku.size} · Tồn {selectedSku.quantity.toString()}</span>
              </div>
            ) : null}

            <label htmlFor="borrow-quantity">
              Số lượng
              <input
                id="borrow-quantity"
                type="number"
                min="1"
                value={form.quantity}
                onChange={(event) => updateForm({ quantity: event.target.value })}
              />
            </label>

            <label htmlFor="borrower-name">
              Người mượn
              <input
                id="borrower-name"
                type="text"
                value={form.borrowerName}
                onChange={(event) => updateForm({ borrowerName: event.target.value })}
                required
              />
            </label>

            <label htmlFor="borrow-location">
              Nơi mượn
              <input
                id="borrow-location"
                type="text"
                value={form.borrowLocation}
                onChange={(event) => updateForm({ borrowLocation: event.target.value })}
                required
              />
            </label>

            <label htmlFor="borrow-note">
              Ghi chú
              <textarea
                id="borrow-note"
                value={form.note}
                onChange={(event) => updateForm({ note: event.target.value })}
                rows={3}
              />
            </label>

            <button type="submit" disabled={creating}>
              {creating ? 'Đang tạo...' : 'Tạo phiếu mượn'}
            </button>
          </form>
        </aside>

        <section className="inv-panel borrowed-list-panel" aria-label="Danh sách hàng mượn">
          <div className="borrowed-toolbar" aria-label="Lọc trạng thái phiếu mượn">
            {statusOptions.map((option) => (
              <button
                type="button"
                className={status === option.value ? 'borrowed-filter-active' : 'btn-secondary'}
                key={option.value}
                onClick={() => setStatus(option.value)}
                aria-pressed={status === option.value}
              >
                {option.label}
              </button>
            ))}
          </div>

          {error ? (
            <div className="inv-error-state" role="alert">
              <p>Không tải được danh sách hàng mượn.</p>
              <p className="inv-error-detail">{error}</p>
            </div>
          ) : null}

          <div className="inv-table-wrap">
            <table className="inv-table" aria-label="Danh sách phiếu mượn" aria-busy={loading}>
              <thead>
                <tr>
                  <th>Sản phẩm / SKU</th>
                  <th>Số lượng</th>
                  <th>Người mượn</th>
                  <th>Nơi mượn</th>
                  <th>Ngày mượn</th>
                  <th>Trạng thái</th>
                  <th>Hành động</th>
                </tr>
              </thead>
              <tbody>
                {loading ? <BorrowedSkeletonRows /> : null}
                {!loading && records.map((record) => (
                  <tr key={record.id}>
                    <td>
                      <div className="borrowed-product-cell">
                        <strong>{record.product_name}</strong>
                        <span>{record.sku_code}</span>
                      </div>
                    </td>
                    <td className="inv-cell-qty">{record.quantity}</td>
                    <td>{record.borrower_name}</td>
                    <td>{record.borrow_location}</td>
                    <td>{formatDate(record.borrowed_at)}</td>
                    <td>
                      <span className={`inv-badge ${record.status === 'BORROWED' ? 'inv-badge-active' : 'inv-badge-inactive'}`}>
                        {statusLabel(record.status)}
                      </span>
                    </td>
                    <td className="table-actions">
                      {record.status === 'BORROWED' ? (
                        <button
                          type="button"
                          className="btn-secondary"
                          onClick={() => {
                            setReturningId(record.id)
                            setReturnNote('')
                            setReturnError(null)
                          }}
                          aria-label={`Trả hàng ${record.sku_code}`}
                        >
                          Trả hàng
                        </button>
                      ) : (
                        <span className="inv-cell-muted">-</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {!loading && records.length === 0 && !error ? (
            <p className="empty-state inv-empty">Chưa có phiếu mượn.</p>
          ) : null}
        </section>
      </section>

      {returningId !== null ? (
        <section className="inv-panel borrowed-return-panel" aria-label="Xác nhận trả hàng">
          <form
            className="borrowed-form"
            onSubmit={(event) => {
              const record = records.find((item) => item.id === returningId)
              if (record) {
                void handleReturnSubmit(event, record)
              }
            }}
          >
            <div className="borrowed-return-heading">
              <h2>Xác nhận trả hàng</h2>
              <button type="button" className="btn-secondary" onClick={() => setReturningId(null)}>
                Bỏ qua
              </button>
            </div>
            {returnError ? <p className="form-error">{returnError}</p> : null}
            <label htmlFor="return-note">
              Ghi chú trả hàng
              <textarea
                id="return-note"
                value={returnNote}
                onChange={(event) => setReturnNote(event.target.value)}
                rows={3}
              />
            </label>
            <button type="submit" disabled={returning}>
              {returning ? 'Đang xác nhận...' : 'Xác nhận trả hàng'}
            </button>
          </form>
        </section>
      ) : null}
    </main>
  )
}
