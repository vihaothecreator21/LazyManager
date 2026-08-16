import { useEffect, useState } from 'react'
import { PageHeader } from '../../../components/PageHeader'
import { ProductForm } from '../components/ProductForm'
import { ProductTable } from '../components/ProductTable'
import { SkuForm } from '../components/SkuForm'
import {
  createProduct,
  createSku,
  deactivateProduct,
  deactivateSku,
  listProducts,
  updateProduct,
  updateSku,
  type Product,
  type ProductPayload,
  type ProductSku,
  type SkuPayload,
} from '../api/inventoryApi'

export function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')
  const [showCreate, setShowCreate] = useState(false)
  const [editProduct, setEditProduct] = useState<Product | null>(null)
  const [editSku, setEditSku] = useState<{ sku: ProductSku; product: Product } | null>(null)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)

    listProducts(search || undefined)
      .then((data) => {
        if (!cancelled) {
          setProducts(data.products)
          setLoading(false)
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'Không tải được danh sách sản phẩm.')
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [search])

  function handleSearchSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSearch(searchInput.trim())
  }

  function handleSearchClear() {
    setSearchInput('')
    setSearch('')
  }

  async function handleCreateProduct(payload: ProductPayload) {
    const result = await createProduct(payload)
    setProducts((prev) => [result.product, ...prev])
    setShowCreate(false)
  }

  async function handleUpdateProduct(id: number, payload: ProductPayload) {
    const result = await updateProduct(id, payload)
    setProducts((prev) => prev.map((product) => (product.id === result.product.id ? result.product : product)))
    setEditProduct(null)
  }

  async function handleDeactivateProduct(product: Product) {
    if (!window.confirm(`Ngừng hoạt động sản phẩm "${product.name}"? Toàn bộ SKU sẽ bị ngừng.`)) return

    const result = await deactivateProduct(product.id)
    setProducts((prev) => prev.map((item) => (item.id === result.product.id ? result.product : item)))
  }

  async function handleCreateSku(productId: number, payload: SkuPayload) {
    const result = await createSku(productId, payload)
    setProducts((prev) => prev.map((product) => (product.id === result.product.id ? result.product : product)))
  }

  async function handleUpdateSku(skuId: number, payload: SkuPayload) {
    const result = await updateSku(skuId, payload)
    setProducts((prev) => prev.map((product) => (product.id === result.product.id ? result.product : product)))
    setEditSku(null)
  }

  async function handleDeactivateSku(sku: ProductSku) {
    if (!window.confirm(`Ngừng hoạt động SKU "${sku.sku_code}"?`)) return

    const result = await deactivateSku(sku.id)
    setProducts((prev) => prev.map((product) => (product.id === result.product.id ? result.product : product)))
  }

  return (
    <main className="shell inv-shell">
      <PageHeader
        eyebrow="Product/SKU"
        title="Sản phẩm"
        summary="Quản lý product code, SKU theo size và trạng thái hoạt động."
        actions={
          <button
            id="btn-create-product"
            type="button"
            onClick={() => setShowCreate((visible) => !visible)}
            aria-expanded={showCreate}
          >
            {showCreate ? 'Hủy' : 'Tạo sản phẩm'}
          </button>
        }
      />

      <header className="inv-page-header">
        <form className="inv-search-form" onSubmit={handleSearchSubmit} role="search" aria-label="Tìm kiếm sản phẩm">
          <label htmlFor="product-search" className="inv-search-label">
            Tìm kiếm
          </label>
          <div className="inv-search-row">
            <input
              id="product-search"
              type="search"
              placeholder="Tìm theo mã sản phẩm, mã SKU hoặc tên"
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              aria-label="Tìm theo mã sản phẩm, mã SKU hoặc tên"
            />
            <button type="submit">Tìm</button>
            {search ? (
              <button type="button" className="btn-secondary" onClick={handleSearchClear}>
                Xóa bộ lọc
              </button>
            ) : null}
          </div>
        </form>

        {showCreate ? (
          <ProductForm
            submitText="Lưu"
            busyText="Đang tạo..."
            onSubmit={handleCreateProduct}
            onCancel={() => setShowCreate(false)}
          />
        ) : null}
      </header>

      <section className="inv-panel" aria-label="Danh sách sản phẩm">
        <ProductTable
          products={products}
          loading={loading}
          error={error}
          search={search}
          onEditProduct={setEditProduct}
          onDeactivateProduct={(product) => void handleDeactivateProduct(product)}
          onCreateSku={handleCreateSku}
          onEditSku={(sku, product) => setEditSku({ sku, product })}
          onDeactivateSku={(sku) => void handleDeactivateSku(sku)}
        />
      </section>

      {editProduct ? (
        <div className="inv-dialog-backdrop" role="dialog" aria-modal="true" aria-label="Sửa sản phẩm">
          <div className="inv-dialog">
            <h2 className="inv-dialog-title">Sửa sản phẩm</h2>
            <ProductForm
              product={editProduct}
              submitText="Lưu"
              busyText="Đang lưu..."
              onSubmit={(payload) => handleUpdateProduct(editProduct.id, payload)}
              onCancel={() => setEditProduct(null)}
            />
          </div>
        </div>
      ) : null}

      {editSku ? (
        <div className="inv-dialog-backdrop" role="dialog" aria-modal="true" aria-label="Sửa SKU">
          <div className="inv-dialog">
            <h2 className="inv-dialog-title">Sửa SKU</h2>
            <SkuForm
              sku={editSku.sku}
              onSubmit={(payload) => handleUpdateSku(editSku.sku.id, payload)}
              onCancel={() => setEditSku(null)}
            />
          </div>
        </div>
      ) : null}
    </main>
  )
}
