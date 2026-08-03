import type { StockImportLine } from '../api/stockImportApi'

type StockImportPreviewTableProps = {
  lines: StockImportLine[]
}

export function StockImportPreviewTable({ lines }: StockImportPreviewTableProps) {
  if (lines.length === 0) {
    return <p className="inv-empty">Chưa có dữ liệu preview.</p>
  }

  return (
    <div className="inv-table-wrap">
      <table className="inv-table inv-import-table">
        <thead>
          <tr>
            <th>Dòng</th>
            <th>Mã SKU</th>
            <th>SKU gốc</th>
            <th>Số lượng gốc</th>
            <th>Tồn trước</th>
            <th>Số lượng mới</th>
            <th>Tồn sau</th>
            <th>Lỗi</th>
          </tr>
        </thead>
        <tbody>
          {lines.map((line) => (
            <tr
              className={line.error_message ? 'inv-import-row-error' : 'inv-import-row-ok'}
              key={line.id}
            >
              <td>{line.row_number}</td>
              <td className="inv-cell-code">{line.sku_code || 'Chưa có'}</td>
              <td>{line.raw_sku_code || 'Trống'}</td>
              <td>{line.raw_quantity || 'Trống'}</td>
              <td className="inv-cell-qty">{line.quantity_before ?? '-'}</td>
              <td className="inv-cell-qty">{line.quantity ?? '-'}</td>
              <td className="inv-cell-qty">{line.quantity_after ?? '-'}</td>
              <td>
                <span className={line.error_message ? 'inv-import-error' : 'inv-import-ok'}>
                  {line.error_message ?? 'Hợp lệ'}
                </span>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
