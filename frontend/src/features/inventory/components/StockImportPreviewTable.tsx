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
            <th>Tên sản phẩm</th>
            <th>Biến thể</th>
            <th>Mã SKU</th>
            <th>Số lượng</th>
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
              <td>{line.raw_product_name || 'Chưa có'}</td>
              <td>{line.raw_variant || 'Chưa có'}</td>
              <td className="inv-cell-code">{line.sku_code || 'Chưa có'}</td>
              <td className="inv-cell-qty">{line.quantity ?? '-'}</td>
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
