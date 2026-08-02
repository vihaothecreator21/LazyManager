import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'
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

// ─── Helpers ──────────────────────────────────────────────────────────────────

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

// ─── Inline Edit Dialog ───────────────────────────────────────────────────────

interface EditProductDialogProps {
  product: Product
  onSave: (payload: Partial<ProductPayload>) => Promise<void>
  onClose: () => void
}

function EditProductDialog({ product, onSave, onClose }: EditProductDialogProps) {
  const [code, setCode] = useState(product.product_code)
  const [name, setName] = useState(product.name)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await onSave({ product_code: code, name })
      onClose()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Lỗi không xác định.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="inv-dialog-backdrop" role="dialog" aria-modal="true" aria-label="Sửa sản phẩm">
      <div className="inv-dialog">
        <h2 className="inv-dialog-title">Sửa sản phẩm</h2>
        <form onSubmit={(e) => void handleSubmit(e)} className="inv-dialog-form">
          <label htmlFor="edit-product-code">
            Mã sản phẩm
            <input
              id="edit-product-code"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              required
              disabled={saving}
            />
          </label>
          <label htmlFor="edit-product-name">
            Tên sản phẩm
            <input
              id="edit-product-name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              required
              disabled={saving}
            />
          </label>
          {error ? <p className="form-error">{error}</p> : null}
          <div className="inv-dialog-actions">
            <button type="submit" disabled={saving}>
              {saving ? 'Đang lưu...' : 'Lưu'}
            </button>
            <button type="button" className="btn-secondary" onClick={onClose} disabled={saving}>
              Hủy
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─── Edit SKU Dialog ──────────────────────────────────────────────────────────

interface EditSkuDialogProps {
  sku: ProductSku
  onSave: (payload: Partial<SkuPayload>) => Promise<void>
  onClose: () => void
}

function EditSkuDialog({ sku, onSave, onClose }: EditSkuDialogProps) {
  const [code, setCode] = useState(sku.sku_code)
  const [size, setSize] = useState(sku.size)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      await onSave({ sku_code: code, size })
      onClose()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Lỗi không xác định.')
    } finally {
      setSaving(false)
    }
  }

  return (
    <div className="inv-dialog-backdrop" role="dialog" aria-modal="true" aria-label="Sửa SKU">
      <div className="inv-dialog">
        <h2 className="inv-dialog-title">Sửa SKU</h2>
        <form onSubmit={(e) => void handleSubmit(e)} className="inv-dialog-form">
          <label htmlFor="edit-sku-code">
            Mã SKU
            <input
              id="edit-sku-code"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              required
              disabled={saving}
            />
          </label>
          <label htmlFor="edit-sku-size">
            Kích thước
            <input
              id="edit-sku-size"
              value={size}
              onChange={(e) => setSize(e.target.value)}
              required
              disabled={saving}
            />
          </label>
          {error ? <p className="form-error">{error}</p> : null}
          <div className="inv-dialog-actions">
            <button type="submit" disabled={saving}>
              {saving ? 'Đang lưu...' : 'Lưu'}
            </button>
            <button type="button" className="btn-secondary" onClick={onClose} disabled={saving}>
              Hủy
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

// ─── Add SKU Inline Form ──────────────────────────────────────────────────────

interface AddSkuRowProps {
  productId: number
  onAdded: (product: Product) => void
  onCancel: () => void
}

function AddSkuRow({ productId, onAdded, onCancel }: AddSkuRowProps) {
  const [code, setCode] = useState('')
  const [size, setSize] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const codeRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    codeRef.current?.focus()
  }, [])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError(null)
    try {
      const result = await createSku(productId, { sku_code: code, size })
      onAdded(result.product)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Lỗi không xác định.')
      setSaving(false)
    }
  }

  return (
    <tr className="inv-add-sku-row">
      <td colSpan={4}>
        <form onSubmit={(e) => void handleSubmit(e)} className="inv-add-sku-form">
          <input
            ref={codeRef}
            placeholder="Mã SKU"
            value={code}
            onChange={(e) => setCode(e.target.value)}
            required
            disabled={saving}
            aria-label="Mã SKU mới"
            style={{ minWidth: '120px' }}
          />
          <input
            placeholder="Kích thước"
            value={size}
            onChange={(e) => setSize(e.target.value)}
            required
            disabled={saving}
            aria-label="Kích thước"
            style={{ minWidth: '80px' }}
          />
          {error ? <span className="inv-inline-error">{error}</span> : null}
          <button type="submit" disabled={saving}>
            {saving ? '...' : 'Lưu'}
          </button>
          <button type="button" className="btn-secondary" onClick={onCancel} disabled={saving}>
            Hủy
          </button>
        </form>
      </td>
      <td />
    </tr>
  )
}

// ─── SKU Row ──────────────────────────────────────────────────────────────────

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
            <button
              type="button"
              className="btn-secondary"
              onClick={() => onEdit(sku)}
              aria-label={`Sửa SKU ${sku.sku_code}`}
            >
              Chỉnh sửa
            </button>
            <button
              type="button"
              className="btn-danger"
              onClick={() => onDeactivate(sku)}
              aria-label={`Ngừng hoạt động SKU ${sku.sku_code}`}
            >
              Ngừng
            </button>
          </>
        ) : null}
      </td>
    </tr>
  )
}

// ─── Product Row ──────────────────────────────────────────────────────────────

interface ProductRowProps {
  product: Product
  onEdit: (product: Product) => void
  onDeactivate: (product: Product) => void
  onSkuAdded: (updated: Product) => void
  onSkuEdit: (sku: ProductSku, product: Product) => void
  onSkuDeactivate: (sku: ProductSku, product: Product) => void
}

function ProductRow({
  product,
  onEdit,
  onDeactivate,
  onSkuAdded,
  onSkuEdit,
  onSkuDeactivate,
}: ProductRowProps) {
  const [showAddSku, setShowAddSku] = useState(false)

  return (
    <>
      {/* Product header row */}
      <tr className={`inv-product-row${product.active ? '' : ' inv-row-inactive'}`}>
        <td className="inv-cell-code">{product.product_code}</td>
        <td className="inv-cell-name">{product.name}</td>
        <td>
          <span className={`inv-badge ${product.active ? 'inv-badge-active' : 'inv-badge-inactive'}`}>
            {product.active ? 'Hoạt động' : 'Ngừng'}
          </span>
        </td>
        <td className="inv-cell-qty inv-cell-muted">
          {product.skus.filter((s) => s.active).length} SKU
        </td>
        <td className="table-actions">
          {product.active ? (
            <>
              <button
                type="button"
                className="btn-secondary"
                onClick={() => onEdit(product)}
                aria-label={`Sửa sản phẩm ${product.product_code}`}
              >
                Chỉnh sửa
              </button>
              <button
                type="button"
                className="inv-btn-add-sku"
                onClick={() => setShowAddSku((v) => !v)}
                aria-expanded={showAddSku}
                aria-label={`Thêm SKU cho ${product.product_code}`}
              >
                Thêm SKU
              </button>
              <button
                type="button"
                className="btn-danger"
                onClick={() => onDeactivate(product)}
                aria-label={`Ngừng hoạt động sản phẩm ${product.product_code}`}
              >
                Ngừng
              </button>
            </>
          ) : null}
        </td>
      </tr>

      {/* SKU rows */}
      {product.skus.map((sku) => (
        <SkuRow
          key={sku.id}
          sku={sku}
          onEdit={(s) => onSkuEdit(s, product)}
          onDeactivate={(s) => onSkuDeactivate(s, product)}
        />
      ))}

      {/* Add SKU inline form */}
      {showAddSku && product.active ? (
        <AddSkuRow
          productId={product.id}
          onAdded={(updated) => {
            setShowAddSku(false)
            onSkuAdded(updated)
          }}
          onCancel={() => setShowAddSku(false)}
        />
      ) : null}

      {/* Spacer between products */}
      <tr className="inv-product-spacer" aria-hidden="true">
        <td colSpan={5} />
      </tr>
    </>
  )
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export function ProductsPage() {
  const { session, logout } = useAuth()
  const [products, setProducts] = useState<Product[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [searchInput, setSearchInput] = useState('')

  // Create product form
  const [showCreate, setShowCreate] = useState(false)
  const [newCode, setNewCode] = useState('')
  const [newName, setNewName] = useState('')
  const [creating, setCreating] = useState(false)
  const [createError, setCreateError] = useState<string | null>(null)

  // Edit dialogs
  const [editProduct, setEditProduct] = useState<Product | null>(null)
  const [editSku, setEditSku] = useState<{ sku: ProductSku; product: Product } | null>(null)

  // ─── Load products ────────────────────────────────────────────────────────

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

  // ─── Handlers ─────────────────────────────────────────────────────────────

  function handleSearchSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSearch(searchInput.trim())
  }

  function handleSearchClear() {
    setSearchInput('')
    setSearch('')
  }

  async function handleCreateProduct(e: React.FormEvent) {
    e.preventDefault()
    setCreating(true)
    setCreateError(null)
    try {
      const result = await createProduct({ product_code: newCode, name: newName })
      setProducts((prev) => [result.product, ...prev])
      setNewCode('')
      setNewName('')
      setShowCreate(false)
    } catch (err) {
      setCreateError(err instanceof Error ? err.message : 'Lỗi không xác định.')
    } finally {
      setCreating(false)
    }
  }

  async function handleUpdateProduct(id: number, payload: Partial<{ product_code: string; name: string }>) {
    const result = await updateProduct(id, payload)
    setProducts((prev) =>
      prev.map((p) => (p.id === result.product.id ? result.product : p)),
    )
  }

  async function handleDeactivateProduct(product: Product) {
    if (!window.confirm(`Ngừng hoạt động sản phẩm "${product.name}"? Toàn bộ SKU sẽ bị ngừng.`)) return
    const result = await deactivateProduct(product.id)
    setProducts((prev) =>
      prev.map((p) => (p.id === result.product.id ? result.product : p)),
    )
  }

  function handleSkuAdded(updated: Product) {
    setProducts((prev) =>
      prev.map((p) => (p.id === updated.id ? updated : p)),
    )
  }

  async function handleUpdateSku(skuId: number, payload: Partial<SkuPayload>) {
    const result = await updateSku(skuId, payload)
    setProducts((prev) =>
      prev.map((p) => (p.id === result.product.id ? result.product : p)),
    )
  }

  async function handleDeactivateSku(sku: ProductSku) {
    if (!window.confirm(`Ngừng hoạt động SKU "${sku.sku_code}"?`)) return
    const result = await deactivateSku(sku.id)
    setProducts((prev) =>
      prev.map((p) => (p.id === result.product.id ? result.product : p)),
    )
  }

  // ─── Render ───────────────────────────────────────────────────────────────

  return (
    <main className="shell inv-shell">
      {/* Navigation */}
      <nav className="inv-topnav" aria-label="Điều hướng chính">
        <Link className="inv-brand" to="/">LazyManager</Link>
        <div className="inv-topnav-links">
          <Link to="/schedule">Lịch làm việc</Link>
          <Link to="/products" aria-current="page">Sản phẩm</Link>
          <Link to="/inventory">Tồn kho</Link>
          <Link to="/employees">Nhân viên</Link>
          {session ? (
            <button type="button" className="nav-logout" onClick={() => void logout()}>
              Đăng xuất
            </button>
          ) : null}
        </div>
      </nav>

      {/* Page header */}
      <header className="inv-page-header">
        <div className="inv-page-title-row">
          <h1 className="inv-page-title">Sản phẩm</h1>
          <button
            id="btn-create-product"
            type="button"
            onClick={() => {
              setShowCreate((v) => !v)
              setCreateError(null)
            }}
            aria-expanded={showCreate}
          >
            {showCreate ? 'Hủy' : 'Tạo sản phẩm'}
          </button>
        </div>

        {/* Search */}
        <form
          className="inv-search-form"
          onSubmit={handleSearchSubmit}
          role="search"
          aria-label="Tìm kiếm sản phẩm"
        >
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

        {/* Create product inline form */}
        {showCreate ? (
          <form
            id="form-create-product"
            className="inv-create-form"
            onSubmit={(e) => void handleCreateProduct(e)}
            aria-label="Tạo sản phẩm mới"
          >
            <label htmlFor="new-product-code">
              Mã sản phẩm
              <input
                id="new-product-code"
                value={newCode}
                onChange={(e) => setNewCode(e.target.value)}
                required
                disabled={creating}
                autoFocus
              />
            </label>
            <label htmlFor="new-product-name">
              Tên sản phẩm
              <input
                id="new-product-name"
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
                required
                disabled={creating}
              />
            </label>
            {createError ? <p className="form-error">{createError}</p> : null}
            <div className="inv-create-actions">
              <button type="submit" disabled={creating}>
                {creating ? 'Đang tạo...' : 'Lưu'}
              </button>
              <button
                type="button"
                className="btn-secondary"
                onClick={() => { setShowCreate(false); setCreateError(null) }}
                disabled={creating}
              >
                Hủy
              </button>
            </div>
          </form>
        ) : null}
      </header>

      {/* Main table */}
      <section className="inv-panel" aria-label="Danh sách sản phẩm">
        {loading ? (
          <div className="inv-table-wrap">
            <table className="inv-table" aria-label="Đang tải..." aria-busy="true">
              <thead>
                <tr>
                  <th scope="col">Mã</th>
                  <th scope="col">Tên / Kích thước</th>
                  <th scope="col">Trạng thái</th>
                  <th scope="col">SKU / Tồn</th>
                  <th scope="col" aria-label="Hành động" />
                </tr>
              </thead>
              <tbody>
                <SkeletonRow />
                <SkeletonRow />
                <SkeletonRow />
              </tbody>
            </table>
          </div>
        ) : error ? (
          <div className="inv-error-state" role="alert">
            <p>Không tải được danh sách sản phẩm.</p>
            <p className="inv-error-detail">{error}</p>
          </div>
        ) : products.length === 0 ? (
          <div className="empty-state inv-empty" role="status">
            {search ? (
              <p>Không tìm thấy sản phẩm phù hợp với "{search}".</p>
            ) : (
              <p>Chưa có sản phẩm. Nhấn "Tạo sản phẩm" để thêm mới.</p>
            )}
          </div>
        ) : (
          <div className="inv-table-wrap">
            <table className="inv-table" aria-label="Danh sách sản phẩm">
              <thead>
                <tr>
                  <th scope="col">Mã</th>
                  <th scope="col">Tên / Kích thước</th>
                  <th scope="col">Trạng thái</th>
                  <th scope="col">SKU / Tồn</th>
                  <th scope="col" aria-label="Hành động" />
                </tr>
              </thead>
              <tbody>
                {products.map((product) => (
                  <ProductRow
                    key={product.id}
                    product={product}
                    onEdit={setEditProduct}
                    onDeactivate={(p) => void handleDeactivateProduct(p)}
                    onSkuAdded={handleSkuAdded}
                    onSkuEdit={(sku, p) => setEditSku({ sku, product: p })}
                    onSkuDeactivate={(sku) => void handleDeactivateSku(sku)}
                  />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {/* Edit dialogs */}
      {editProduct ? (
        <EditProductDialog
          product={editProduct}
          onSave={(payload) => handleUpdateProduct(editProduct.id, payload)}
          onClose={() => setEditProduct(null)}
        />
      ) : null}

      {editSku ? (
        <EditSkuDialog
          sku={editSku.sku}
          onSave={(payload) => handleUpdateSku(editSku.sku.id, payload)}
          onClose={() => setEditSku(null)}
        />
      ) : null}
    </main>
  )
}
