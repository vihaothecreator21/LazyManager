import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import {
  listInventory,
  listInventoryTransactions,
  type InventoryBalance,
  type InventoryTransaction,
  type InventoryTransactionType,
} from '../api/inventoryApi'

// ─── Helpers ──────────────────────────────────────────────────────────────────

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function transactionLabel(type: InventoryTransactionType): string {
  const labels: Record<InventoryTransactionType, string> = {
    IMPORT_SYNC: 'Nhập hàng',
    SALE: 'Bán hàng',
    SALE_REVERSAL: 'Hoàn bán',
    BORROW_OUT: 'Cho mượn',
    BORROW_RETURN: 'Trả mượn',
  }
  return labels[type] ?? type
}

function transactionSign(change: number): string {
  return change > 0 ? `+${change}` : `${change}`
}

// ─── Skeleton rows ─────────────────────────────────────────────────────────────

function InvSkeletonRow() {
  return (
    <tr className="inv-skeleton-row" aria-hidden="true">
      <td><span className="inv-skel" style={{ width: '84px' }} /></td>
      <td><span className="inv-skel" style={{ width: '130px' }} /></td>
      <td><span className="inv-skel" style={{ width: '80px' }} /></td>
      <td><span className="inv-skel" style={{ width: '50px' }} /></td>
      <td><span className="inv-skel" style={{ width: '52px' }} /></td>
      <td />
    </tr>
  )
}

function TxSkeletonRow() {
  return (
    <tr className="inv-skeleton-row" aria-hidden="true">
      <td><span className="inv-skel" style={{ width: '72px' }} /></td>
      <td><span className="inv-skel" style={{ width: '40px' }} /></td>
      <td><span className="inv-skel" style={{ width: '32px' }} /></td>
      <td><span className="inv-skel" style={{ width: '32px' }} /></td>
      <td><span className="inv-skel" style={{ width: '32px' }} /></td>
      <td><span className="inv-skel" style={{ width: '110px' }} /></td>
    </tr>
  )
}

// ─── Transaction Panel ─────────────────────────────────────────────────────────

interface TransactionPanelProps {
  balance: InventoryBalance
  onClose: () => void
}

function TransactionPanel({ balance, onClose }: TransactionPanelProps) {
  const [transactions, setTransactions] = useState<InventoryTransaction[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)

    listInventoryTransactions(balance.sku_id)
      .then((data) => {
        if (!cancelled) {
          // Hiển thị mới nhất trước
          setTransactions([...data.transactions].reverse())
          setLoading(false)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'Không tải được lịch sử giao dịch.')
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [balance.sku_id])

  return (
    <aside className="inv-tx-panel" aria-label={`Lịch sử giao dịch SKU ${balance.sku_code}`}>
      <div className="inv-tx-panel-header">
        <div className="inv-tx-panel-info">
          <span className="inv-tx-panel-sku">{balance.sku_code}</span>
          <span className="inv-tx-panel-product">
            {balance.product_name} / {balance.size}
          </span>
        </div>
        <button
          type="button"
          className="inv-tx-close btn-secondary"
          onClick={onClose}
          aria-label="Đóng lịch sử giao dịch"
        >
          Đóng
        </button>
      </div>

      <div className="inv-tx-body">
        {loading ? (
          <table className="inv-table" aria-label="Đang tải lịch sử..." aria-busy="true">
            <thead>
              <tr>
                <th scope="col">Loại</th>
                <th scope="col">Thay đổi</th>
                <th scope="col">Trước</th>
                <th scope="col">Sau</th>
                <th scope="col">Ghi chú</th>
                <th scope="col">Thời gian</th>
              </tr>
            </thead>
            <tbody>
              <TxSkeletonRow />
              <TxSkeletonRow />
              <TxSkeletonRow />
            </tbody>
          </table>
        ) : error ? (
          <div className="inv-error-state" role="alert">
            <p>Không tải được lịch sử giao dịch.</p>
            <p className="inv-error-detail">{error}</p>
          </div>
        ) : transactions.length === 0 ? (
          <div className="empty-state inv-empty" role="status">
            <p>Chưa có giao dịch nào cho SKU này.</p>
          </div>
        ) : (
          <div className="inv-table-wrap">
            <table
              className="inv-table"
              aria-label={`Lịch sử giao dịch SKU ${balance.sku_code}`}
            >
              <thead>
                <tr>
                  <th scope="col">Loại</th>
                  <th scope="col" className="inv-cell-qty">Thay đổi</th>
                  <th scope="col" className="inv-cell-qty">Trước</th>
                  <th scope="col" className="inv-cell-qty">Sau</th>
                  <th scope="col">Ghi chú</th>
                  <th scope="col">Thời gian</th>
                </tr>
              </thead>
              <tbody>
                {transactions.map((tx) => (
                  <tr key={tx.id} className="inv-tx-row">
                    <td>
                      <span className={`inv-tx-badge inv-tx-${tx.type.toLowerCase().replace('_', '-')}`}>
                        {transactionLabel(tx.type)}
                      </span>
                    </td>
                    <td className={`inv-cell-qty inv-tx-change ${tx.quantity_change >= 0 ? 'inv-tx-positive' : 'inv-tx-negative'}`}>
                      {transactionSign(tx.quantity_change)}
                    </td>
                    <td className="inv-cell-qty">{tx.quantity_before}</td>
                    <td className="inv-cell-qty">{tx.quantity_after}</td>
                    <td className="inv-tx-reason">
                      {tx.reason ?? <span className="inv-cell-muted">-</span>}
                    </td>
                    <td className="inv-tx-time">{formatDate(tx.created_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </aside>
  )
}

// ─── Inventory Row ─────────────────────────────────────────────────────────────

interface InventoryRowProps {
  balance: InventoryBalance
  isSelected: boolean
  onHistoryClick: (balance: InventoryBalance) => void
}

function InventoryRow({ balance, isSelected, onHistoryClick }: InventoryRowProps) {
  return (
    <tr className={`inv-inv-row${!balance.sku_active || !balance.product_active ? ' inv-row-inactive' : ''}${isSelected ? ' inv-inv-row-selected' : ''}`}>
      <td className="inv-cell-code">{balance.product_code}</td>
      <td className="inv-cell-name">{balance.product_name}</td>
      <td className="inv-cell-code">{balance.sku_code}</td>
      <td>{balance.size}</td>
      <td className="inv-cell-qty inv-qty-cell">{balance.quantity}</td>
      <td>
        <span className={`inv-badge ${balance.sku_active && balance.product_active ? 'inv-badge-active' : 'inv-badge-inactive'}`}>
          {balance.sku_active && balance.product_active ? 'Hoạt động' : 'Ngừng'}
        </span>
      </td>
      <td className="table-actions">
        <button
          type="button"
          className={`btn-secondary${isSelected ? ' inv-history-active' : ''}`}
          onClick={() => onHistoryClick(balance)}
          aria-pressed={isSelected}
          aria-label={`Xem lịch sử giao dịch SKU ${balance.sku_code}`}
        >
          Lịch sử
        </button>
      </td>
    </tr>
  )
}

// ─── Main Page ─────────────────────────────────────────────────────────────────

export function InventoryPage() {
  const { session, logout } = useAuth()
  const [inventory, setInventory] = useState<InventoryBalance[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [selectedBalance, setSelectedBalance] = useState<InventoryBalance | null>(null)

  // ─── Load inventory ──────────────────────────────────────────────────────────

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)

    listInventory(search || undefined)
      .then((data) => {
        if (!cancelled) {
          setInventory(data.inventory)
          setLoading(false)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'Không tải được tồn kho.')
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [search])

  // ─── Handlers ────────────────────────────────────────────────────────────────

  function handleSearchSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSelectedBalance(null)
    setSearch(searchInput.trim())
  }

  function handleSearchClear() {
    setSearchInput('')
    setSearch('')
    setSelectedBalance(null)
  }

  function handleHistoryClick(balance: InventoryBalance) {
    setSelectedBalance((prev) =>
      prev?.sku_id === balance.sku_id ? null : balance,
    )
  }

  // ─── Render ──────────────────────────────────────────────────────────────────

  return (
    <main className="shell inv-shell">
      {/* Navigation */}
      <nav className="inv-topnav" aria-label="Điều hướng chính">
        <Link className="inv-brand" to="/">LazyManager</Link>
        <div className="inv-topnav-links">
          <Link to="/schedule">Lịch làm việc</Link>
          <Link to="/products">Sản phẩm</Link>
          <Link to="/inventory" aria-current="page">Tồn kho</Link>
          <Link to="/employees">Nhân viên</Link>
          {session ? (
            <button type="button" className="nav-logout" onClick={() => void logout()}>
              Đăng xuất
            </button>
          ) : null}
        </div>
      </nav>

      {/* Page header */}
      <header className="inv-page-header">
        <div className="inv-page-title-row">
          <h1 className="inv-page-title">Tồn kho</h1>
        </div>

        {/* Search */}
        <form
          className="inv-search-form"
          onSubmit={handleSearchSubmit}
          role="search"
          aria-label="Tìm kiếm tồn kho"
        >
          <label htmlFor="inventory-search" className="inv-search-label">
            Tìm kiếm
          </label>
          <div className="inv-search-row">
            <input
              id="inventory-search"
              type="search"
              placeholder="Tìm theo mã sản phẩm, mã SKU hoặc tên"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              aria-label="Tìm theo mã sản phẩm, mã SKU hoặc tên"
            />
            <button type="submit">Tìm</button>
            {search ? (
              <button type="button" className="btn-secondary" onClick={handleSearchClear}>
                Xóa bộ lọc
              </button>
            ) : null}
          </div>
        </form>
      </header>

      {/* Content area: table + optional transaction panel side by side */}
      <div className={`inv-content-area${selectedBalance ? ' inv-content-with-panel' : ''}`}>
        {/* Inventory table */}
        <section className="inv-panel inv-inv-section" aria-label="Danh sách tồn kho">
          {loading ? (
            <div className="inv-table-wrap">
              <table className="inv-table" aria-label="Đang tải..." aria-busy="true">
                <thead>
                  <tr>
                    <th scope="col">Mã SP</th>
                    <th scope="col">Tên sản phẩm</th>
                    <th scope="col">Mã SKU</th>
                    <th scope="col">Kích thước</th>
                    <th scope="col" className="inv-cell-qty">Số lượng</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col" aria-label="Hành động" />
                  </tr>
                </thead>
                <tbody>
                  <InvSkeletonRow />
                  <InvSkeletonRow />
                  <InvSkeletonRow />
                  <InvSkeletonRow />
                </tbody>
              </table>
            </div>
          ) : error ? (
            <div className="inv-error-state" role="alert">
              <p>Không tải được tồn kho.</p>
              <p className="inv-error-detail">{error}</p>
            </div>
          ) : inventory.length === 0 ? (
            <div className="empty-state inv-empty" role="status">
              {search ? (
                <p>Không tìm thấy tồn kho phù hợp với "{search}".</p>
              ) : (
                <p>Chưa có tồn kho. Vui lòng tạo sản phẩm và SKU trước.</p>
              )}
            </div>
          ) : (
            <div className="inv-table-wrap">
              <table className="inv-table" aria-label="Danh sách tồn kho">
                <thead>
                  <tr>
                    <th scope="col">Mã SP</th>
                    <th scope="col">Tên sản phẩm</th>
                    <th scope="col">Mã SKU</th>
                    <th scope="col">Kích thước</th>
                    <th scope="col" className="inv-cell-qty">Số lượng</th>
                    <th scope="col">Trạng thái</th>
                    <th scope="col" aria-label="Hành động" />
                  </tr>
                </thead>
                <tbody>
                  {inventory.map((balance) => (
                    <InventoryRow
                      key={balance.sku_id}
                      balance={balance}
                      isSelected={selectedBalance?.sku_id === balance.sku_id}
                      onHistoryClick={handleHistoryClick}
                    />
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        {/* Transaction panel - slides in when a SKU is selected */}
        {selectedBalance ? (
          <TransactionPanel
            balance={selectedBalance}
            onClose={() => setSelectedBalance(null)}
          />
        ) : null}
      </div>
    </main>
  )
}
