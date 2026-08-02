import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
import { InventoryTable } from '../components/InventoryTable'
import { TransactionHistory } from '../components/TransactionHistory'
import { listInventory, type InventoryBalance } from '../api/inventoryApi'

export function InventoryPage() {
  const { session, logout } = useAuth()
  const [inventory, setInventory] = useState<InventoryBalance[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [selectedBalance, setSelectedBalance] = useState<InventoryBalance | null>(null)

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
    setSelectedBalance((prev) => (prev?.sku_id === balance.sku_id ? null : balance))
  }

  return (
    <main className="shell inv-shell">
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

      <header className="inv-page-header">
        <div className="inv-page-title-row">
          <h1 className="inv-page-title">Tồn kho</h1>
        </div>

        <form className="inv-search-form" onSubmit={handleSearchSubmit} role="search" aria-label="Tìm kiếm tồn kho">
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

      <div className={`inv-content-area${selectedBalance ? ' inv-content-with-panel' : ''}`}>
        <section className="inv-panel inv-inv-section" aria-label="Danh sách tồn kho">
          <InventoryTable
            inventory={inventory}
            loading={loading}
            error={error}
            search={search}
            selectedSkuId={selectedBalance?.sku_id ?? null}
            onHistoryClick={handleHistoryClick}
          />
        </section>

        {selectedBalance ? (
          <TransactionHistory balance={selectedBalance} onClose={() => setSelectedBalance(null)} />
        ) : null}
      </div>
    </main>
  )
}
