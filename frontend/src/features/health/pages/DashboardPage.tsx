import { Link } from 'react-router-dom'
import { useAuth } from '../../../lib/useAuth'

const operationGroups = [
  {
    name: 'Nhân sự',
    tone: 'people',
    summary: 'Tài khoản, phân quyền và lịch làm việc tuần.',
    items: [
      {
        title: 'Nhân viên',
        body: 'Tạo, sửa, khóa tài khoản và kiểm soát quyền nhân viên.',
        to: '/employees',
        meta: 'Quản lý truy cập',
      },
      {
        title: 'Lịch làm việc',
        body: 'Chọn ca sáng, chiều, OFF theo từng nhân viên.',
        to: '/schedule',
        meta: '2 người mỗi ca',
      },
    ],
  },
  {
    name: 'Sản phẩm và tồn kho',
    tone: 'inventory',
    summary: 'Mã sản phẩm, SKU, số lượng hiện tại và lịch sử giao dịch.',
    items: [
      {
        title: 'Sản phẩm',
        body: 'Quản lý mã sản phẩm, SKU theo kích thước và trạng thái hoạt động.',
        to: '/products',
        meta: 'Danh mục hàng',
      },
      {
        title: 'Tồn kho',
        body: 'Xem số lượng hiện tại và lịch sử phát sinh theo từng SKU.',
        to: '/inventory',
        meta: 'Sổ kho rõ',
      },
      {
        title: 'Nhập tồn kho',
        body: 'Tải CSV, xem trước dữ liệu, chặn lỗi rồi đồng bộ tồn kho.',
        to: '/inventory/import',
        meta: 'CSV nhập kho',
      },
    ],
  },
  {
    name: 'Luồng vận hành kho',
    tone: 'operations',
    summary: 'Bán hằng ngày, mượn trả và kiểm kho theo quy trình vận hành.',
    items: [
      {
        title: 'Phiếu bán',
        body: 'Xem danh sách, mở chi tiết và hủy phiếu đã xác nhận.',
        to: '/daily-sales',
        meta: 'Theo trạng thái',
      },
      {
        title: 'Tạo phiếu bán',
        body: 'Import CSV bán hàng, xác nhận để trừ tồn một lần.',
        to: '/daily-sales/new',
        meta: 'Ghi nhận bán',
      },
      {
        title: 'Mượn/trả',
        body: 'Ghi nhận hàng cho mượn, trả hàng và tránh trả hai lần.',
        to: '/borrowed',
        meta: 'Ghi nhận mượn',
      },
      {
        title: 'Kiểm kho',
        body: 'Tạo phiên, nhập số đếm thực tế, xem chênh lệch và tải CSV.',
        to: '/stock-counts',
        meta: 'Đối soát tồn',
      },
    ],
  },
]

export function DashboardPage() {
  const { session, logout } = useAuth()

  return (
    <main className="home-shell">
      <section className="home-command-hero" aria-labelledby="home-title">
        <div className="home-command-copy">
          <p className="home-kicker">LazyManager</p>
          <h1 id="home-title">Lazy Manager</h1>
        </div>

        <aside className="home-session-card" aria-label="Phiên đăng nhập">
          <span>Đang vận hành</span>
          <strong>{session?.user.email ?? 'Chưa có phiên'}</strong>
          <p>{session?.user.role ?? 'Đang xác minh'}</p>
          {session ? (
            <button type="button" className="btn-secondary" onClick={() => void logout()}>
              Đăng xuất
            </button>
          ) : null}
        </aside>
      </section>

      <section className="home-command-grid" aria-label="Tất cả chức năng">
        {operationGroups.map((group, groupIndex) => (
          <article
            className={`home-command-group home-command-group--${group.tone}`}
            key={group.name}
          >
            <div className="home-command-group-head">
              <span>{String(groupIndex + 1).padStart(2, '0')}</span>
              <div>
                <h2>{group.name}</h2>
                <p>{group.summary}</p>
              </div>
            </div>

            <div className="home-command-list">
              {group.items.map((item) => (
                <Link className="home-command-tile" key={item.to} to={item.to}>
                  <span>{item.meta}</span>
                  <strong>{item.title}</strong>
                  <p>{item.body}</p>
                </Link>
              ))}
            </div>
          </article>
        ))}
      </section>
    </main>
  )
}
