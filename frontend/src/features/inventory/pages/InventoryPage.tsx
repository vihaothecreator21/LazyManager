import { useEffect, useState } from 'react'
import { PageHeader } from '../../../components/PageHeader'
import { InventoryTable } from '../components/InventoryTable'
import { TransactionHistory } from '../components/TransactionHistory'
import { listInventory, type InventoryBalance } from '../api/inventoryApi'

export function InventoryPage() {
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
      <PageHeader
        eyebrow="Inventory"
        title="Tồn kho"
        summary="Xem balance hiện tại và lịch sử giao dịch theo từng SKU."
      />

      <header className="inv-page-header">
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
