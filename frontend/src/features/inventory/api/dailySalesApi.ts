import { apiClient } from '../../../lib/apiClient'

export type DailySaleStatus = 'DRAFT' | 'CONFIRMED' | 'CANCELLED'

export type DailySaleLine = {
  id: number
  row_number: number
  raw_product_name: string | null
  raw_variant: string | null
  raw_sku_code: string | null
  raw_quantity_sold: string | null
  sku_code: string
  sku_id: number | null
  quantity_sold: number | null
  preview_quantity_before: number | null
  preview_quantity_after: number | null
  error_message: string | null
}

export type DailySale = {
  id: number
  sales_date: string
  file_name: string
  file_hash: string | null
  status: DailySaleStatus
  has_errors: boolean
  confirmed_at: string | null
  cancelled_at: string | null
  cancel_reason: string | null
  created_by: number | null
  confirmed_by: number | null
  cancelled_by: number | null
  created_at: string | null
  lines: DailySaleLine[]
}

export type DailySaleSummary = Omit<
  DailySale,
  'file_hash' | 'cancel_reason' | 'created_by' | 'confirmed_by' | 'cancelled_by' | 'lines'
> & {
  lines_count: number
}

export type PaginatedDailySales = {
  data: DailySaleSummary[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

type DailySalesIndexResponse = {
  daily_sales: DailySaleSummary[]
  meta: PaginatedDailySales['meta']
}

export async function createDailySale(input: {
  salesDate: string
  file: File
}): Promise<DailySale> {
  const formData = new FormData()
  formData.append('sales_date', input.salesDate)
  formData.append('file', input.file)

  const response = await apiClient<{ daily_sale: DailySale }>(
    '/api/inventory/v1/daily-sales',
    {
      method: 'POST',
      body: formData,
    },
  )

  return response.daily_sale
}

export async function listDailySales(params?: {
  page?: number
  status?: string
  date_from?: string
  date_to?: string
}): Promise<PaginatedDailySales> {
  const queryParts: string[] = []
  if (params?.page) queryParts.push(`page=${params.page.toString()}`)
  if (params?.status) queryParts.push(`status=${params.status}`)
  if (params?.date_from) queryParts.push(`date_from=${params.date_from}`)
  if (params?.date_to) queryParts.push(`date_to=${params.date_to}`)

  const queryString = queryParts.length > 0 ? `?${queryParts.join('&')}` : ''
  const response = await apiClient<DailySalesIndexResponse>(
    `/api/inventory/v1/daily-sales${queryString}`,
  )

  return {
    data: response.daily_sales,
    meta: response.meta,
  }
}

export async function getDailySale(id: number): Promise<DailySale> {
  const response = await apiClient<{ daily_sale: DailySale }>(
    `/api/inventory/v1/daily-sales/${id.toString()}`,
  )

  return response.daily_sale
}

export async function confirmDailySale(id: number): Promise<DailySale> {
  const response = await apiClient<{ daily_sale: DailySale }>(
    `/api/inventory/v1/daily-sales/${id.toString()}/confirm`,
    {
      method: 'POST',
    },
  )

  return response.daily_sale
}

export async function cancelDailySale(id: number, reason: string): Promise<DailySale> {
  const response = await apiClient<{ daily_sale: DailySale }>(
    `/api/inventory/v1/daily-sales/${id.toString()}/cancel`,
    {
      method: 'POST',
      body: JSON.stringify({ reason }),
    },
  )

  return response.daily_sale
}
