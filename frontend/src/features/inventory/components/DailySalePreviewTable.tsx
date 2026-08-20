import type { DailySaleLine } from '../api/dailySalesApi'

type DailySalePreviewTableProps = {
  lines: DailySaleLine[]
}

export function DailySalePreviewTable({ lines }: DailySalePreviewTableProps) {
  if (lines.length === 0) {
    return <p className="inv-empty">Chưa có dữ liệu phiếu bán.</p>
  }

  return (
    <div className="inv-table-wrap">
      <table className="inv-table inv-import-table">
        <thead>
          <tr>
            <th>Dòng</th>
            <th>Tên sản phẩm</th>
            <th>Màu / Size</th>
            <th>Mã SKU</th>
            <th>SKU gốc</th>
            <th>Số lượng bán gốc</th>
            <th>Tồn lúc xem trước</th>
            <th>Số bán</th>
            <th>Tồn dự kiến</th>
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
              <td>{line.raw_product_name || '-'}</td>
              <td>{line.raw_variant || '-'}</td>
              <td className="inv-cell-code">{line.sku_code || 'Chưa có'}</td>
              <td>{line.raw_sku_code || 'Trống'}</td>
              <td>{line.raw_quantity_sold || 'Trống'}</td>
              <td className="inv-cell-qty">{line.preview_quantity_before ?? '-'}</td>
              <td className="inv-cell-qty">{line.quantity_sold ?? '-'}</td>
              <td className="inv-cell-qty">
                {line.preview_quantity_after !== null ? (
                  <span className={line.preview_quantity_after < 0 ? 'text-red-error font-medium' : ''}>
                    {line.preview_quantity_after}
                  </span>
                ) : (
                  '-'
                )}
              </td>
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
