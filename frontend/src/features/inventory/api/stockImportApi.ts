import { apiClient } from '../../../lib/apiClient'

export type StockImportLine = {
  id: number
  row_number: number
  raw_sku_code: string | null
  raw_quantity: string | null
  sku_code: string
  sku_id: number | null
  quantity: number | null
  quantity_before: number | null
  quantity_after: number | null
  error_message: string | null
}

export type StockImport = {
  id: number
  file_name: string
  file_hash: string
  status: 'PREVIEWED' | 'CONFIRMED'
  has_errors: boolean
  confirmed_at: string | null
  created_by: number | null
  created_at: string | null
  lines: StockImportLine[]
}

export async function uploadStockImport(file: File): Promise<StockImport> {
  const formData = new FormData()
  formData.append('file', file)

  const response = await apiClient<{ stock_import: StockImport }>(
    '/api/inventory/v1/stock-imports',
    {
      method: 'POST',
      body: formData,
    },
  )

  return response.stock_import
}

export async function getStockImportPreview(id: number): Promise<StockImport> {
  const response = await apiClient<{ stock_import: StockImport }>(
    `/api/inventory/v1/stock-imports/${id.toString()}/preview`,
  )

  return response.stock_import
}

export async function confirmStockImport(id: number): Promise<StockImport> {
  const response = await apiClient<{ stock_import: StockImport }>(
    `/api/inventory/v1/stock-imports/${id.toString()}/confirm`,
    { method: 'POST' },
  )

  return response.stock_import
}
