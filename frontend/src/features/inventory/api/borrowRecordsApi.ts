import { apiClient } from '../../../lib/apiClient'

export type BorrowRecordStatus = 'BORROWED' | 'RETURNED'

export type BorrowRecord = {
  id: number
  status: BorrowRecordStatus
  sku_id: number
  sku_code: string
  product_id: number
  product_code: string
  product_name: string
  quantity: number
  borrower_name: string
  borrow_location: string
  note: string | null
  return_note: string | null
  created_by: number | null
  returned_by: number | null
  borrowed_at: string | null
  returned_at: string | null
  created_at: string | null
  updated_at: string | null
}

export type CreateBorrowRecordPayload = {
  sku_id: number
  quantity: number
  borrower_name: string
  borrow_location: string
  note?: string
}

export type ReturnBorrowRecordPayload = {
  return_note?: string
}

export async function listBorrowRecords(params?: {
  status?: string
  search?: string
}): Promise<BorrowRecord[]> {
  const queryParts: string[] = []
  if (params?.status) queryParts.push(`status=${encodeURIComponent(params.status)}`)
  if (params?.search) queryParts.push(`search=${encodeURIComponent(params.search)}`)
  const queryString = queryParts.length > 0 ? `?${queryParts.join('&')}` : ''
  const response = await apiClient<{ borrow_records: BorrowRecord[] }>(
    `/api/inventory/v1/borrow-records${queryString}`,
  )

  return response.borrow_records
}

export async function getBorrowRecord(id: number): Promise<BorrowRecord> {
  const response = await apiClient<{ borrow_record: BorrowRecord }>(
    `/api/inventory/v1/borrow-records/${id.toString()}`,
  )

  return response.borrow_record
}

export async function createBorrowRecord(
  payload: CreateBorrowRecordPayload,
): Promise<BorrowRecord> {
  const response = await apiClient<{ borrow_record: BorrowRecord }>(
    '/api/inventory/v1/borrow-records',
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  return response.borrow_record
}

export async function returnBorrowRecord(
  id: number,
  payload: ReturnBorrowRecordPayload,
): Promise<BorrowRecord> {
  const response = await apiClient<{ borrow_record: BorrowRecord }>(
    `/api/inventory/v1/borrow-records/${id.toString()}/return`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )

  return response.borrow_record
}
