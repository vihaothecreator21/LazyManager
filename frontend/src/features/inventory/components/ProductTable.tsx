import { useState } from 'react'
import { SkuForm } from './SkuForm'
import type { Product, ProductSku, SkuPayload } from '../api/inventoryApi'

interface ProductTableProps {
  products: Product[]
  loading: boolean
  error: string | null
  search: string
  onEditProduct: (product: Product) => void
  onDeactivateProduct: (product: Product) => void
  onCreateSku: (productId: number, payload: SkuPayload) => Promise<void>
  onEditSku: (sku: ProductSku, product: Product) => void
  onDeactivateSku: (sku: ProductSku) => void
}

function SkeletonRow() {
  return (
    <tr className="inv-skeleton-row" aria-hidden="true">
      <td><span className="inv-skel" style={{ width: '96px' }} /></td>
      <td><span className="inv-skel" style={{ width: '140px' }} /></td>
      <td><span className="inv-skel" style={{ width: '60px' }} /></td>
      <td><span className="inv-skel" style={{ width: '90px' }} /></td>
      <td />
    </tr>
  )
}

function TableHead() {
  return (
    <thead>
      <tr>
        <th scope="col">Mã</th>
        <th scope="col">Tên / Kích thước</th>
        <th scope="col">Trạng thái</th>
        <th scope="col">SKU / Tồn</th>
        <th scope="col" aria-label="Hành động" />
      </tr>
    </thead>
  )
}

interface SkuRowProps {
  sku: ProductSku
  onEdit: (sku: ProductSku) => void
  onDeactivate: (sku: ProductSku) => void
}

function SkuRow({ sku, onEdit, onDeactivate }: SkuRowProps) {
  return (
    <tr className={`inv-sku-row${sku.active ? '' : ' inv-row-inactive'}`}>
      <td className="inv-cell-code inv-cell-indent">{sku.sku_code}</td>
      <td className="inv-cell-name">{sku.size}</td>
      <td>
        <span className={`inv-badge ${sku.active ? 'inv-badge-active' : 'inv-badge-inactive'}`}>
          {sku.active ? 'Hoạt động' : 'Ngừng'}
        </span>
      </td>
      <td className="inv-cell-qty">{sku.quantity}</td>
      <td className="table-actions">
        {sku.active ? (
          <>
            <button type="button" className="btn-secondary" onClick={() => onEdit(sku)} aria-label={`Sửa SKU ${sku.sku_code}`}>
              Chỉnh sửa
            </button>
            <button type="button" className="btn-danger" onClick={() => onDeactivate(sku)} aria-label={`Ngừng hoạt động SKU ${sku.sku_code}`}>
              Ngừng
            </button>
          </>
        ) : null}
      </td>
    </tr>
  )
}

interface ProductRowProps {
  product: Product
  onEdit: (product: Product) => void
  onDeactivate: (product: Product) => void
  onCreateSku: (productId: number, payload: SkuPayload) => Promise<void>
  onSkuEdit: (sku: ProductSku, product: Product) => void
  onSkuDeactivate: (sku: ProductSku) => void
}

function ProductRow({ product, onEdit, onDeactivate, onCreateSku, onSkuEdit, onSkuDeactivate }: ProductRowProps) {
  const [showAddSku, setShowAddSku] = useState(false)

  return (
    <>
      <tr className={`inv-product-row${product.active ? '' : ' inv-row-inactive'}`}>
        <td className="inv-cell-code">{product.product_code}</td>
        <td className="inv-cell-name">{product.name}</td>
        <td>
          <span className={`inv-badge ${product.active ? 'inv-badge-active' : 'inv-badge-inactive'}`}>
            {product.active ? 'Hoạt động' : 'Ngừng'}
          </span>
        </td>
        <td className="inv-cell-qty inv-cell-muted">
          {product.skus.filter((sku) => sku.active).length} SKU
        </td>
        <td className="table-actions">
          {product.active ? (
            <>
              <button type="button" className="btn-secondary" onClick={() => onEdit(product)} aria-label={`Sửa sản phẩm ${product.product_code}`}>
                Chỉnh sửa
              </button>
              <button type="button" className="inv-btn-add-sku" onClick={() => setShowAddSku((visible) => !visible)} aria-expanded={showAddSku} aria-label={`Thêm SKU cho ${product.product_code}`}>
                Thêm SKU
              </button>
              <button type="button" className="btn-danger" onClick={() => onDeactivate(product)} aria-label={`Ngừng hoạt động sản phẩm ${product.product_code}`}>
                Ngừng
              </button>
            </>
          ) : null}
        </td>
      </tr>

      {product.skus.map((sku) => (
        <SkuRow
          key={sku.id}
          sku={sku}
          onEdit={(selectedSku) => onSkuEdit(selectedSku, product)}
          onDeactivate={onSkuDeactivate}
        />
      ))}

      {showAddSku && product.active ? (
        <tr className="inv-add-sku-row">
          <td colSpan={4}>
            <SkuForm
              inline
              onSubmit={async (payload) => {
                await onCreateSku(product.id, payload)
                setShowAddSku(false)
              }}
              onCancel={() => setShowAddSku(false)}
            />
          </td>
          <td />
        </tr>
      ) : null}

      <tr className="inv-product-spacer" aria-hidden="true">
        <td colSpan={5} />
      </tr>
    </>
  )
}

export function ProductTable({
  products,
  loading,
  error,
  search,
  onEditProduct,
  onDeactivateProduct,
  onCreateSku,
  onEditSku,
  onDeactivateSku,
}: ProductTableProps) {
  if (loading) {
    return (
      <div className="inv-table-wrap">
        <table className="inv-table" aria-label="Đang tải..." aria-busy="true">
          <TableHead />
          <tbody>
            <SkeletonRow />
            <SkeletonRow />
            <SkeletonRow />
          </tbody>
        </table>
      </div>
    )
  }

  if (error) {
    return (
      <div className="inv-error-state" role="alert">
        <p>Không tải được danh sách sản phẩm.</p>
        <p className="inv-error-detail">{error}</p>
      </div>
    )
  }

  if (products.length === 0) {
    return (
      <div className="empty-state inv-empty" role="status">
        {search ? (
          <p>Không tìm thấy sản phẩm phù hợp với "{search}".</p>
        ) : (
          <p>Chưa có sản phẩm. Nhấn "Tạo sản phẩm" để thêm mới.</p>
        )}
      </div>
    )
  }

  return (
    <div className="inv-table-wrap">
      <table className="inv-table" aria-label="Danh sách sản phẩm">
        <TableHead />
        <tbody>
          {products.map((product) => (
            <ProductRow
              key={product.id}
              product={product}
              onEdit={onEditProduct}
              onDeactivate={onDeactivateProduct}
              onCreateSku={onCreateSku}
              onSkuEdit={onEditSku}
              onSkuDeactivate={onDeactivateSku}
            />
          ))}
        </tbody>
      </table>
    </div>
  )
}
