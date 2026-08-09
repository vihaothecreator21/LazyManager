import { apiClient } from '../../../lib/apiClient'

export type StockCountStatus = 'DRAFT' | 'COUNTED'

export type StockCountLine = {
  id: number
  sku_id: number
  sku_code: string
  product_id: number
  product_code: string
  product_name: string
  size: string
  expected_quantity: number
  actual_quantity: number | null
  variance: number | null
  note: string | null
}

export type StockCount = {
  id: number
  name: string | null
  count_date: string
  status: StockCountStatus
  total_lines: number
  counted_lines: number
  variance_lines: number
  created_by: number | null
  created_at: string | null
  updated_at: string | null
  lines?: StockCountLine[]
}

export type UpdateStockCountLinePayload = {
  line_id: number
  actual_quantity: number
  note?: string | null
}

export async function listStockCounts(): Promise<StockCount[]> {
  const response = await apiClient<{ stock_counts: StockCount[] }>(
    '/api/inventory/v1/stock-counts',
  )

  return response.stock_counts
}

export async function createStockCount(input: {
  name?: string
  count_date?: string
}): Promise<StockCount> {
  const response = await apiClient<{ stock_count: StockCount }>(
    '/api/inventory/v1/stock-counts',
    {
      method: 'POST',
      body: JSON.stringify(input),
    },
  )

  return response.stock_count
}

export async function getStockCount(id: number): Promise<StockCount> {
  const response = await apiClient<{ stock_count: StockCount }>(
    `/api/inventory/v1/stock-counts/${id.toString()}`,
  )

  return response.stock_count
}

export async function updateStockCountLines(
  id: number,
  lines: UpdateStockCountLinePayload[],
): Promise<StockCount> {
  const response = await apiClient<{ stock_count: StockCount }>(
    `/api/inventory/v1/stock-counts/${id.toString()}/lines`,
    {
      method: 'PUT',
      body: JSON.stringify({ lines }),
    },
  )

  return response.stock_count
}

export function stockCountExportCsvUrl(id: number): string {
  return `/api/inventory/v1/stock-counts/${id.toString()}/export-csv`
}
