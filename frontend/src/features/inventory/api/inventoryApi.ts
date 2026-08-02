import { apiClient } from '../../../lib/apiClient'

// ─── Types ────────────────────────────────────────────────────────────────────

export type ProductSku = {
  id: number
  sku_code: string
  size: string
  active: boolean
  quantity: number
}

export type Product = {
  id: number
  product_code: string
  name: string
  active: boolean
  skus: ProductSku[]
  created_at: string | null
  updated_at: string | null
}

export type ProductPayload = {
  product_code: string
  name: string
}

export type SkuPayload = {
  sku_code: string
  size: string
}

export type InventoryBalance = {
  sku_id: number
  sku_code: string
  size: string
  sku_active: boolean
  product_id: number
  product_code: string
  product_name: string
  product_active: boolean
  quantity: number
  updated_at: string | null
}

export type InventoryTransactionType =
  | 'IMPORT_SYNC'
  | 'SALE'
  | 'SALE_REVERSAL'
  | 'BORROW_OUT'
  | 'BORROW_RETURN'

export type InventoryTransaction = {
  id: number
  sku_id: number
  type: InventoryTransactionType
  quantity_change: number
  quantity_before: number
  quantity_after: number
  reference_type: string | null
  reference_id: string | null
  reason: string | null
  created_by: number | null
  created_at: string
}

// ─── Product API ──────────────────────────────────────────────────────────────

export function listProducts(search?: string): Promise<{ products: Product[] }> {
  const params = search ? `?search=${encodeURIComponent(search)}` : ''
  return apiClient<{ products: Product[] }>(
    `/api/inventory/v1/products${params}`,
  )
}

export function getProduct(id: number): Promise<{ product: Product }> {
  return apiClient<{ product: Product }>(`/api/inventory/v1/products/${id}`)
}

export function createProduct(
  payload: ProductPayload,
): Promise<{ product: Product }> {
  return apiClient<{ product: Product }>('/api/inventory/v1/products', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
}

export function updateProduct(
  id: number,
  payload: Partial<ProductPayload>,
): Promise<{ product: Product }> {
  return apiClient<{ product: Product }>(`/api/inventory/v1/products/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

export function deactivateProduct(id: number): Promise<{ product: Product }> {
  return apiClient<{ product: Product }>(`/api/inventory/v1/products/${id}`, {
    method: 'DELETE',
  })
}

// ─── SKU API ──────────────────────────────────────────────────────────────────

export function createSku(
  productId: number,
  payload: SkuPayload,
): Promise<{ product: Product }> {
  return apiClient<{ product: Product }>(
    `/api/inventory/v1/products/${productId}/skus`,
    {
      method: 'POST',
      body: JSON.stringify(payload),
    },
  )
}

export function updateSku(
  id: number,
  payload: Partial<SkuPayload>,
): Promise<{ product: Product }> {
  return apiClient<{ product: Product }>(`/api/inventory/v1/skus/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
}

export function deactivateSku(id: number): Promise<{ product: Product }> {
  return apiClient<{ product: Product }>(`/api/inventory/v1/skus/${id}`, {
    method: 'DELETE',
  })
}

// ─── Inventory API ────────────────────────────────────────────────────────────

export function listInventory(
  search?: string,
): Promise<{ inventory: InventoryBalance[] }> {
  const params = search ? `?search=${encodeURIComponent(search)}` : ''
  return apiClient<{ inventory: InventoryBalance[] }>(
    `/api/inventory/v1/inventory${params}`,
  )
}

export function listInventoryTransactions(
  skuId: number,
): Promise<{ transactions: InventoryTransaction[] }> {
  return apiClient<{ transactions: InventoryTransaction[] }>(
    `/api/inventory/v1/inventory/${skuId}/transactions`,
  )
}
