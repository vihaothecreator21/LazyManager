import type { StockCountLine } from '../api/stockCountsApi'

type DraftLine = {
  actualQuantity: string
  note: string
}

type Props = {
  lines: StockCountLine[]
  drafts: Record<number, DraftLine>
  onChange: (lineId: number, patch: Partial<DraftLine>) => void
}

function varianceClass(variance: number | null): string {
  if (variance === null || variance === 0) return 'is-even'
  return variance > 0 ? 'is-positive' : 'is-negative'
}

function varianceLabel(variance: number | null): string {
  if (variance === null) return '-'
  return variance > 0 ? `+${variance.toString()}` : variance.toString()
}

export function StockCountLinesTable({ lines, drafts, onChange }: Props) {
  return (
    <div className="inv-table-wrap">
      <table className="inv-table stock-count-lines-table" aria-label="Dòng kiểm kho">
        <thead>
          <tr>
            <th>SKU</th>
            <th>Sản phẩm</th>
            <th>Size</th>
            <th>Dự kiến</th>
            <th>Số thực tế</th>
            <th>Chênh lệch</th>
            <th>Ghi chú</th>
          </tr>
        </thead>
        <tbody>
          {lines.map((line) => {
            const draft = drafts[line.id] ?? {
              actualQuantity: line.actual_quantity?.toString() ?? '',
              note: line.note ?? '',
            }
            const parsedActual = draft.actualQuantity === '' ? null : Number.parseInt(draft.actualQuantity, 10)
            const liveVariance = Number.isInteger(parsedActual)
              ? (parsedActual as number) - line.expected_quantity
              : line.variance

            return (
              <tr key={line.id}>
                <td className="inv-cell-code">{line.sku_code}</td>
                <td className="stock-count-product-cell">
                  <strong>{line.product_name}</strong>
                  <span>{line.product_code}</span>
                </td>
                <td>{line.size}</td>
                <td className="inv-cell-qty">{line.expected_quantity}</td>
                <td>
                  <input
                    aria-label={`Số thực tế ${line.sku_code}`}
                    className="stock-count-number-input"
                    min="0"
                    type="number"
                    value={draft.actualQuantity}
                    onChange={(event) => onChange(line.id, { actualQuantity: event.target.value })}
                  />
                </td>
                <td>
                  <span className={`stock-count-variance ${varianceClass(liveVariance)}`}>
                    {varianceLabel(liveVariance)}
                  </span>
                </td>
                <td>
                  <input
                    aria-label={`Ghi chú ${line.sku_code}`}
                    className="stock-count-note-input"
                    type="text"
                    value={draft.note}
                    onChange={(event) => onChange(line.id, { note: event.target.value })}
                  />
                </td>
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}
