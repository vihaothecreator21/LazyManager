import type { InventoryBalance } from '../api/inventoryApi'

interface InventoryTableProps {
  inventory: InventoryBalance[]
  loading: boolean
  error: string | null
  search: string
  selectedSkuId: number | null
  onHistoryClick: (balance: InventoryBalance) => void
}

function SkeletonRow() {
  return (
    <tr className="inv-skeleton-row" aria-hidden="true">
      <td><span className="inv-skel" style={{ width: '84px' }} /></td>
      <td><span className="inv-skel" style={{ width: '130px' }} /></td>
      <td><span className="inv-skel" style={{ width: '80px' }} /></td>
      <td><span className="inv-skel" style={{ width: '50px' }} /></td>
      <td><span className="inv-skel" style={{ width: '52px' }} /></td>
      <td />
      <td />
    </tr>
  )
}

function TableHead() {
  return (
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
  )
}

function InventoryRow({
  balance,
  isSelected,
  onHistoryClick,
}: {
  balance: InventoryBalance
  isSelected: boolean
  onHistoryClick: (balance: InventoryBalance) => void
}) {
  const active = balance.sku_active && balance.product_active

  return (
    <tr className={`inv-inv-row${active ? '' : ' inv-row-inactive'}${isSelected ? ' inv-inv-row-selected' : ''}`}>
      <td className="inv-cell-code">{balance.product_code}</td>
      <td className="inv-cell-name">{balance.product_name}</td>
      <td className="inv-cell-code">{balance.sku_code}</td>
      <td>{balance.size}</td>
      <td className="inv-cell-qty inv-qty-cell">{balance.quantity}</td>
      <td>
        <span className={`inv-badge ${active ? 'inv-badge-active' : 'inv-badge-inactive'}`}>
          {active ? 'Hoạt động' : 'Ngừng'}
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

export function InventoryTable({
  inventory,
  loading,
  error,
  search,
  selectedSkuId,
  onHistoryClick,
}: InventoryTableProps) {
  if (loading) {
    return (
      <div className="inv-table-wrap">
        <table className="inv-table" aria-label="Đang tải..." aria-busy="true">
          <TableHead />
          <tbody>
            <SkeletonRow />
            <SkeletonRow />
            <SkeletonRow />
            <SkeletonRow />
          </tbody>
        </table>
      </div>
    )
  }

  if (error) {
    return (
      <div className="inv-error-state" role="alert">
        <p>Không tải được tồn kho.</p>
        <p className="inv-error-detail">{error}</p>
      </div>
    )
  }

  if (inventory.length === 0) {
    return (
      <div className="empty-state inv-empty" role="status">
        {search ? (
          <p>Không tìm thấy tồn kho phù hợp với "{search}".</p>
        ) : (
          <p>Chưa có tồn kho. Vui lòng tạo sản phẩm và SKU trước.</p>
        )}
      </div>
    )
  }

  return (
    <div className="inv-table-wrap">
      <table className="inv-table" aria-label="Danh sách tồn kho">
        <TableHead />
        <tbody>
          {inventory.map((balance) => (
            <InventoryRow
              key={balance.sku_id}
              balance={balance}
              isSelected={selectedSkuId === balance.sku_id}
              onHistoryClick={onHistoryClick}
            />
          ))}
        </tbody>
      </table>
    </div>
  )
}
