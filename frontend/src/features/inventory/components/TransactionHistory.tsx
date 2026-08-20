import { useEffect, useState } from 'react'
import {
  listInventoryTransactions,
  type InventoryBalance,
  type InventoryTransaction,
  type InventoryTransactionType,
} from '../api/inventoryApi'

interface TransactionHistoryProps {
  balance: InventoryBalance
  onClose: () => void
}

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

function SkeletonRow() {
  return (
    <tr className="inv-skeleton-row" aria-hidden="true">
      <td><span className="inv-skel" style={{ width: '72px' }} /></td>
      <td><span className="inv-skel" style={{ width: '40px' }} /></td>
      <td><span className="inv-skel" style={{ width: '32px' }} /></td>
      <td><span className="inv-skel" style={{ width: '32px' }} /></td>
      <td><span className="inv-skel" style={{ width: '120px' }} /></td>
      <td><span className="inv-skel" style={{ width: '110px' }} /></td>
    </tr>
  )
}

function TableHead() {
  return (
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
  )
}

export function TransactionHistory({ balance, onClose }: TransactionHistoryProps) {
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
        <button type="button" className="inv-tx-close btn-secondary" onClick={onClose} aria-label="Đóng lịch sử giao dịch">
          Đóng
        </button>
      </div>

      <div className="inv-tx-body">
        {loading ? (
          <table className="inv-table" aria-label="Đang tải lịch sử..." aria-busy="true">
            <TableHead />
            <tbody>
              <SkeletonRow />
              <SkeletonRow />
              <SkeletonRow />
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
            <table className="inv-table" aria-label={`Lịch sử giao dịch SKU ${balance.sku_code}`}>
              <TableHead />
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
