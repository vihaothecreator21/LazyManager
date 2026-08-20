type DemoMethod = 'GET' | 'POST' | 'PUT' | 'DELETE'

const now = '2026-08-20T09:00:00.000000Z'

const products = [
  {
    id: 1,
    product_code: 'AO-HEART',
    name: 'Áo Thun DirtyCoins Patch In Heart',
    active: true,
    created_at: now,
    updated_at: now,
    skus: [
      { id: 11, sku_code: 'AO-HEART-BLACK-M', size: 'M', active: true, quantity: 18 },
      { id: 12, sku_code: 'AO-HEART-BLACK-L', size: 'L', active: true, quantity: 12 },
    ],
  },
  {
    id: 2,
    product_code: 'QUAN-CARGO',
    name: 'Quần Cargo Lazy Fit',
    active: true,
    created_at: now,
    updated_at: now,
    skus: [
      { id: 21, sku_code: 'QUAN-CARGO-32', size: '32', active: true, quantity: 7 },
      { id: 22, sku_code: 'QUAN-CARGO-34', size: '34', active: true, quantity: 4 },
    ],
  },
]

const inventory = products.flatMap((product) =>
  product.skus.map((sku) => ({
    sku_id: sku.id,
    sku_code: sku.sku_code,
    size: sku.size,
    sku_active: sku.active,
    product_id: product.id,
    product_code: product.product_code,
    product_name: product.name,
    product_active: product.active,
    quantity: sku.quantity,
    updated_at: now,
  })),
)

const dailySale = {
  id: 1,
  sales_date: '2026-08-20',
  file_name: 'daily-sales-demo.csv',
  file_hash: 'demo',
  status: 'DRAFT',
  has_errors: false,
  confirmed_at: null,
  cancelled_at: null,
  cancel_reason: null,
  created_by: 1,
  confirmed_by: null,
  cancelled_by: null,
  created_at: now,
  lines: [
    {
      id: 1,
      row_number: 2,
      raw_product_name: 'Áo Thun DirtyCoins Patch In Heart',
      raw_variant: 'Black / M',
      raw_sku_code: null,
      raw_quantity_sold: 'x 1',
      sku_code: 'AO-HEART-BLACK-M',
      sku_id: 11,
      quantity_sold: 1,
      preview_quantity_before: 18,
      preview_quantity_after: 17,
      error_message: null,
    },
  ],
}

const stockImport = {
  id: 1,
  file_name: 'stock-import-demo.csv',
  file_hash: 'demo',
  status: 'PREVIEWED',
  has_errors: false,
  confirmed_at: null,
  created_by: 1,
  created_at: now,
  lines: [
    {
      id: 1,
      row_number: 2,
      raw_product_name: 'Áo Thun DirtyCoins Patch In Heart',
      raw_variant: 'Black / M',
      raw_sku_code: 'AO-HEART-BLACK-M',
      raw_quantity: '24',
      sku_code: 'AO-HEART-BLACK-M',
      sku_id: 11,
      quantity: 24,
      quantity_before: 18,
      quantity_after: 24,
      error_message: null,
    },
  ],
}

const stockCount = {
  id: 1,
  name: 'Kiểm kho demo',
  count_date: '2026-08-20',
  status: 'DRAFT',
  total_lines: 2,
  counted_lines: 1,
  variance_lines: 1,
  created_by: 1,
  created_at: now,
  updated_at: now,
  lines: [
    {
      id: 1,
      sku_id: 11,
      sku_code: 'AO-HEART-BLACK-M',
      product_id: 1,
      product_code: 'AO-HEART',
      product_name: 'Áo Thun DirtyCoins Patch In Heart',
      size: 'M',
      expected_quantity: 18,
      actual_quantity: 17,
      variance: -1,
      note: 'Lệch 1 áo khi kiểm demo',
    },
    {
      id: 2,
      sku_id: 12,
      sku_code: 'AO-HEART-BLACK-L',
      product_id: 1,
      product_code: 'AO-HEART',
      product_name: 'Áo Thun DirtyCoins Patch In Heart',
      size: 'L',
      expected_quantity: 12,
      actual_quantity: null,
      variance: null,
      note: null,
    },
  ],
}

const employees = [
  {
    id: 1,
    name: 'Quản lý Demo',
    email: 'demo@lazymanager.local',
    role: 'STORE_MANAGER',
    status: 'ACTIVE',
    created_at: now,
    updated_at: now,
  },
  {
    id: 2,
    name: 'Nhân viên Ca Chiều',
    email: 'staff@lazymanager.local',
    role: 'STAFF',
    status: 'ACTIVE',
    created_at: now,
    updated_at: now,
  },
]

export function handleDemoRequest<T>(
  url: string,
  options: RequestInit,
): Promise<T> | null {
  if (import.meta.env.VITE_DEMO_MODE !== 'true') return null

  const method = ((options.method ?? 'GET').toUpperCase() as DemoMethod)
  const path = url.split('?')[0]

  return Promise.resolve(routeDemoRequest(path, method) as T)
}

function routeDemoRequest(path: string, method: DemoMethod): unknown {
  if (path === '/api/people/v1/auth/me' || path === '/api/people/v1/auth/login') {
    return { user: { id: 1, email: 'demo@lazymanager.local', role: 'STORE_MANAGER' } }
  }
  if (path === '/api/people/v1/auth/refresh') return { user: { id: 1, email: 'demo@lazymanager.local', role: 'STORE_MANAGER' } }
  if (path === '/api/people/v1/auth/logout') return undefined

  if (path === '/api/people/v1/employees') return method === 'GET' ? { employees } : { employee: employees[0] }
  if (path.startsWith('/api/people/v1/employees/')) return method === 'DELETE' ? undefined : { employee: employees[0] }

  if (path.startsWith('/api/people/v1/schedules')) return scheduleResponse(path, method)

  if (path === '/api/inventory/v1/products') return method === 'GET' ? { products } : { product: products[0] }
  if (path.match(/^\/api\/inventory\/v1\/products\/\d+$/)) return { product: products[0] }
  if (path.match(/^\/api\/inventory\/v1\/products\/\d+\/skus$/)) return { product: products[0] }
  if (path.match(/^\/api\/inventory\/v1\/skus\/\d+$/)) return { product: products[0] }

  if (path === '/api/inventory/v1/inventory') return { inventory }
  if (path.match(/^\/api\/inventory\/v1\/inventory\/\d+\/transactions$/)) {
    return {
      transactions: [
        {
          id: 1,
          sku_id: 11,
          type: 'IMPORT_SYNC',
          quantity_change: 18,
          quantity_before: 0,
          quantity_after: 18,
          reference_type: 'demo',
          reference_id: '1',
          reason: 'Dữ liệu demo',
          created_by: 1,
          created_at: now,
        },
      ],
    }
  }

  if (path === '/api/inventory/v1/stock-imports') return { stock_import: stockImport }
  if (path.match(/^\/api\/inventory\/v1\/stock-imports\/\d+\/preview$/)) return { stock_import: stockImport }
  if (path.match(/^\/api\/inventory\/v1\/stock-imports\/\d+\/confirm$/)) {
    return { stock_import: { ...stockImport, status: 'CONFIRMED', confirmed_at: now } }
  }

  if (path === '/api/inventory/v1/daily-sales') {
    if (method === 'POST') return { daily_sale: dailySale }
    return { daily_sales: [dailySaleSummary()], meta: { current_page: 1, per_page: 15, total: 1, last_page: 1 } }
  }
  if (path.match(/^\/api\/inventory\/v1\/daily-sales\/\d+$/)) return { daily_sale: dailySale }
  if (path.match(/^\/api\/inventory\/v1\/daily-sales\/\d+\/confirm$/)) {
    return { daily_sale: { ...dailySale, status: 'CONFIRMED', confirmed_at: now } }
  }
  if (path.match(/^\/api\/inventory\/v1\/daily-sales\/\d+\/cancel$/)) {
    return { daily_sale: { ...dailySale, status: 'CANCELLED', cancelled_at: now, cancel_reason: 'Demo hủy phiếu' } }
  }

  if (path === '/api/inventory/v1/borrow-records') return method === 'GET' ? { borrow_records: borrowRecords() } : { borrow_record: borrowRecords()[0] }
  if (path.match(/^\/api\/inventory\/v1\/borrow-records\/\d+$/)) return { borrow_record: borrowRecords()[0] }
  if (path.match(/^\/api\/inventory\/v1\/borrow-records\/\d+\/return$/)) {
    return { borrow_record: { ...borrowRecords()[0], status: 'RETURNED', returned_at: now } }
  }

  if (path === '/api/inventory/v1/stock-counts') return method === 'GET' ? { stock_counts: [stockCount] } : { stock_count: stockCount }
  if (path.match(/^\/api\/inventory\/v1\/stock-counts\/\d+$/)) return { stock_count: stockCount }
  if (path.match(/^\/api\/inventory\/v1\/stock-counts\/\d+\/lines$/)) return { stock_count: { ...stockCount, status: 'COUNTED' } }

  return {}
}

function dailySaleSummary() {
  const { lines: _lines, file_hash: _fileHash, cancel_reason: _cancelReason, created_by: _createdBy, confirmed_by: _confirmedBy, cancelled_by: _cancelledBy, ...summary } = dailySale
  return { ...summary, lines_count: dailySale.lines.length }
}

function borrowRecords() {
  return [
    {
      id: 1,
      status: 'BORROWED',
      sku_id: 11,
      sku_code: 'AO-HEART-BLACK-M',
      product_id: 1,
      product_code: 'AO-HEART',
      product_name: 'Áo Thun DirtyCoins Patch In Heart',
      quantity: 2,
      borrower_name: 'Pop-up Nguyễn Trãi',
      borrow_location: 'Quầy trưng bày',
      note: 'Mượn demo',
      return_note: null,
      created_by: 1,
      returned_by: null,
      borrowed_at: now,
      returned_at: null,
      created_at: now,
      updated_at: now,
    },
  ]
}

function scheduleResponse(path: string, method: DemoMethod): unknown {
  if (method === 'DELETE') return undefined
  if (path.endsWith('/assignments')) {
    return { assignment: { id: 1, work_date: '2026-08-20', shift_type: 'MORNING', employee: employees[1], note: 'Demo' } }
  }
  if (path.endsWith('/day-offs')) {
    return { day_off: { id: 1, off_date: '2026-08-21', employee: employees[1], note: 'Demo' } }
  }
  return {
    title: 'Tuần demo',
    week_start: '2026-08-17',
    week_end: '2026-08-23',
    days: ['2026-08-17', '2026-08-18', '2026-08-19', '2026-08-20', '2026-08-21', '2026-08-22', '2026-08-23'].map((date, index) => ({
      date,
      weekday_label: `Thứ ${index + 2}`,
      morning: { label: 'Ca sáng', assignments: index === 3 ? [{ id: 1, work_date: date, shift_type: 'MORNING', employee: employees[0], note: null }] : [] },
      afternoon: { label: 'Ca chiều', assignments: index === 3 ? [{ id: 2, work_date: date, shift_type: 'AFTERNOON', employee: employees[1], note: null }] : [] },
      offs: [],
    })),
  }
}
