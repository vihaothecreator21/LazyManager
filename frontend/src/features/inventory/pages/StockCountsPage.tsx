import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react'
import { PageHeader } from '../../../components/PageHeader'
import {
  createStockCount,
  getStockCount,
  listStockCounts,
  stockCountExportCsvUrl,
  updateStockCountLines,
  type StockCount,
} from '../api/stockCountsApi'
import { StockCountLinesTable } from '../components/StockCountLinesTable'

type DraftLine = {
  actualQuantity: string
  note: string
}

function todayInputValue(): string {
  return new Date().toISOString().slice(0, 10)
}

function statusLabel(status: StockCount['status']): string {
  return status === 'COUNTED' ? 'Đã nhập' : 'Đang đếm'
}

function formatDate(value: string | null): string {
  if (!value) return '-'
  return new Intl.DateTimeFormat('vi-VN', { dateStyle: 'short' }).format(new Date(value))
}

function buildDrafts(stockCount: StockCount | null): Record<number, DraftLine> {
  const lines = stockCount?.lines ?? []

  return Object.fromEntries(
    lines.map((line) => [
      line.id,
      {
        actualQuantity: line.actual_quantity?.toString() ?? '',
        note: line.note ?? '',
      },
    ]),
  )
}

export function StockCountsPage() {
  const [items, setItems] = useState<StockCount[]>([])
  const [selected, setSelected] = useState<StockCount | null>(null)
  const [drafts, setDrafts] = useState<Record<number, DraftLine>>({})
  const [name, setName] = useState('')
  const [countDate, setCountDate] = useState(todayInputValue)
  const [loading, setLoading] = useState(true)
  const [detailLoading, setDetailLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [creating, setCreating] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const loadDetail = useCallback(async (id: number) => {
    setDetailLoading(true)
    setError(null)
    try {
      const detail = await getStockCount(id)
      setSelected(detail)
      setDrafts(buildDrafts(detail))
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tải được chi tiết phiên kiểm kho.')
    } finally {
      setDetailLoading(false)
    }
  }, [])

  const loadList = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await listStockCounts()
      setItems(data)
      if (!selected && data.length > 0) {
        await loadDetail(data[0].id)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tải được danh sách phiên kiểm kho.')
    } finally {
      setLoading(false)
    }
  }, [loadDetail, selected])

  useEffect(() => {
    void loadList()
  }, [loadList])

  async function handleCreate(event: FormEvent) {
    event.preventDefault()
    setCreating(true)
    setError(null)
    setSuccess(null)

    try {
      const created = await createStockCount({
        name: name.trim() || undefined,
        count_date: countDate || undefined,
      })
      const detail = await getStockCount(created.id)
      setItems((current) => [created, ...current])
      setSelected(detail)
      setDrafts(buildDrafts(detail))
      setName('')
      setCountDate(todayInputValue())
      setSuccess('Đã tạo phiên kiểm kho.')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không tạo được phiên kiểm kho.')
    } finally {
      setCreating(false)
    }
  }

  function updateDraft(lineId: number, patch: Partial<DraftLine>) {
    setDrafts((current) => ({
      ...current,
      [lineId]: {
        actualQuantity: current[lineId]?.actualQuantity ?? '',
        note: current[lineId]?.note ?? '',
        ...patch,
      },
    }))
  }

  async function handleSave() {
    if (!selected?.lines) return

    const lines = selected.lines
      .map((line) => ({
        line_id: line.id,
        actual_quantity: Number.parseInt(drafts[line.id]?.actualQuantity ?? '', 10),
        note: drafts[line.id]?.note.trim() || null,
      }))
      .filter((line) => Number.isInteger(line.actual_quantity))

    if (lines.length === 0) {
      setError('Vui lòng nhập ít nhất một số thực tế.')
      return
    }

    setSaving(true)
    setError(null)
    setSuccess(null)
    try {
      const updated = await updateStockCountLines(selected.id, lines)
      setSelected(updated)
      setDrafts(buildDrafts(updated))
      setItems((current) => current.map((item) => (item.id === updated.id ? { ...item, ...updated, lines: undefined } : item)))
      setSuccess('Đã lưu số thực tế.')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không lưu được số thực tế.')
    } finally {
      setSaving(false)
    }
  }

  const totalVariance = useMemo(() => {
    return (selected?.lines ?? []).reduce((sum, line) => sum + (line.variance ?? 0), 0)
  }, [selected])

  return (
    <main className="shell inv-shell stock-count-shell">
      <PageHeader
        eyebrow="Stock count"
        title="Phiên kiểm kho"
        summary="Tạo snapshot tồn kho, nhập actual, xem variance và tải CSV."
      />

      <section className="stock-count-layout">
        <aside className="inv-panel stock-count-side" aria-label="Danh sách phiên kiểm kho">
          <form className="stock-count-create-form" onSubmit={(event) => void handleCreate(event)}>
            <label htmlFor="stock-count-name">
              Tên phiên
              <input
                id="stock-count-name"
                type="text"
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="Kiểm kho sáng thứ Hai"
              />
            </label>
            <label htmlFor="stock-count-date">
              Ngày kiểm
              <input
                id="stock-count-date"
                type="date"
                value={countDate}
                onChange={(event) => setCountDate(event.target.value)}
              />
            </label>
            <button type="submit" disabled={creating}>
              {creating ? 'Đang tạo...' : 'Tạo phiên'}
            </button>
          </form>

          {loading ? <p className="inv-empty">Đang tải phiên kiểm kho...</p> : null}
          {!loading && items.length === 0 ? <p className="inv-empty">Chưa có phiên kiểm kho.</p> : null}

          <div className="stock-count-session-list" aria-label="Phiên kiểm kho đã tạo">
            {items.map((item) => (
              <button
                type="button"
                className={selected?.id === item.id ? 'stock-count-session is-selected' : 'stock-count-session'}
                key={item.id}
                onClick={() => void loadDetail(item.id)}
              >
                <strong>{item.name || `Phiên #${item.id.toString()}`}</strong>
                <span>{formatDate(item.count_date)} · {statusLabel(item.status)}</span>
                <small>{item.counted_lines.toString()} / {item.total_lines.toString()} dòng</small>
              </button>
            ))}
          </div>
        </aside>

        <section className="inv-panel stock-count-detail" aria-label="Chi tiết phiên kiểm kho">
          {error ? <p className="form-error" role="alert">{error}</p> : null}
          {success ? <p className="stock-count-success" role="status">{success}</p> : null}

          {!selected && !detailLoading ? <p className="inv-empty">Chọn hoặc tạo một phiên kiểm kho.</p> : null}
          {detailLoading ? <p className="inv-empty">Đang tải chi tiết...</p> : null}

          {selected ? (
            <>
              <div className="stock-count-detail-header">
                <div>
                  <h2>{selected.name || `Phiên #${selected.id.toString()}`}</h2>
                  <p>{formatDate(selected.count_date)} · {statusLabel(selected.status)}</p>
                </div>
                <div className="stock-count-actions">
                  <a className="btn-secondary stock-count-export" href={stockCountExportCsvUrl(selected.id)}>
                    Tải CSV
                  </a>
                  <button type="button" onClick={() => void handleSave()} disabled={saving || !selected.lines?.length}>
                    {saving ? 'Đang lưu...' : 'Lưu số thực tế'}
                  </button>
                </div>
              </div>

              <dl className="stock-count-summary">
                <div>
                  <dt>Đã nhập</dt>
                  <dd>{selected.counted_lines.toString()} / {selected.total_lines.toString()}</dd>
                </div>
                <div>
                  <dt>Chênh lệch</dt>
                  <dd>{selected.variance_lines.toString()} dòng</dd>
                </div>
                <div>
                  <dt>Tổng lệch</dt>
                  <dd>{totalVariance > 0 ? `+${totalVariance.toString()}` : totalVariance.toString()}</dd>
                </div>
              </dl>

              {selected.lines?.length ? (
                <section aria-label="Số thực tế và Chênh lệch">
                  <StockCountLinesTable lines={selected.lines} drafts={drafts} onChange={updateDraft} />
                </section>
              ) : (
                <p className="inv-empty">Phiên này chưa có dòng kiểm kho.</p>
              )}
            </>
          ) : null}
        </section>
      </section>
    </main>
  )
}
