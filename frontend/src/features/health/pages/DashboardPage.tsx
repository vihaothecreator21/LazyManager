import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import heroImage from '../../../assets/hero.png'
import { useAuth } from '../../../lib/useAuth'

const dashboardSlides = [
  {
    eyebrow: 'Bảng lịch tuần',
    title: 'Kéo nhịp làm việc vào một màn hình duy nhất',
    body: 'Chọn ca sáng, ca chiều, OFF và xem kết quả ngay trên bảng lịch.',
  },
  {
    eyebrow: 'OFF tự động',
    title: 'Ai chưa có ca sẽ nằm đúng hàng OFF',
    body: 'Bảng tự gom nhân viên chưa được xếp ca theo từng ngày để quản lý dễ rà lại.',
  },
  {
    eyebrow: 'Quyền quản lý',
    title: 'Khu nhân viên chỉ mở khi quản lý đăng nhập',
    body: 'Dashboard vẫn mở nhanh, phần chỉnh sửa nhân viên được khóa bằng tài khoản quản lý.',
  },
]

const featureCards = [
  {
    title: 'Xếp ca nhanh',
    body: 'Các nút ca nằm theo từng nhân viên và từng ngày, không cần chuyển màn hình.',
  },
  {
    title: 'Bảng rõ ràng',
    body: 'Kết quả lịch giữ đúng bố cục sáng, chiều, OFF để dễ in hoặc kiểm tra.',
  },
  {
    title: 'Ít rủi ro',
    body: 'Trang nhân viên được giấu sau đăng nhập quản lý, dashboard vẫn vào được ngay.',
  },
]

export function DashboardPage() {
  const { session, logout } = useAuth()
  const [activeSlide, setActiveSlide] = useState(0)
  const pointerStart = useRef<number | null>(null)

  useEffect(() => {
    const timer = window.setInterval(() => {
      setActiveSlide((current) => (current + 1) % dashboardSlides.length)
    }, 4600)

    return () => window.clearInterval(timer)
  }, [])

  function showPreviousSlide() {
    setActiveSlide((current) =>
      current === 0 ? dashboardSlides.length - 1 : current - 1,
    )
  }

  function showNextSlide() {
    setActiveSlide((current) => (current + 1) % dashboardSlides.length)
  }

  function finishPointerSwipe(clientX: number) {
    if (pointerStart.current === null) {
      return
    }

    const distance = clientX - pointerStart.current
    pointerStart.current = null

    if (Math.abs(distance) < 42) {
      return
    }

    if (distance > 0) {
      showPreviousSlide()
    } else {
      showNextSlide()
    }
  }

  const slide = dashboardSlides[activeSlide]

  return (
    <main className="home-shell">
      <nav className="home-nav" aria-label="Điều hướng chính">
        <Link className="home-brand" to="/">
          LazyManager
        </Link>
        <div className="home-nav-links">
          <Link to="/schedule">Lịch làm việc</Link>
          <Link to="/products">Sản phẩm</Link>
          <Link to="/inventory">Tồn kho</Link>
          <Link to="/inventory/import">Nhập tồn kho</Link>
          <Link to="/daily-sales">Phiếu bán</Link>
          <Link to="/employees">Nhân viên</Link>
          {session ? (
            <button type="button" className="nav-logout" onClick={() => void logout()}>
              Đăng xuất
            </button>
          ) : null}
        </div>
      </nav>

      <section className="home-hero" aria-labelledby="home-title">
        <div className="hero-copy">
          <p className="home-kicker">Xếp lịch nhân viên</p>
          <h1 id="home-title">Một bảng lịch gọn, rõ, có nhịp.</h1>
          <p>
            LazyManager biến việc chia ca hằng tuần thành thao tác chọn nhanh:
            sáng, chiều, OFF và quản lý nhân viên ở đúng nơi cần bảo vệ.
          </p>
          <div className="hero-actions">
            <Link className="primary-cta" to="/schedule">
              Mở bảng lịch
              <span aria-hidden="true">›</span>
            </Link>
            <Link className="secondary-cta" to="/employees">
              Quản lý nhân viên
            </Link>
          </div>
        </div>

        <div className="hero-stage" aria-label="Minh họa bảng lịch">
          <div className="stage-orbit orbit-one" />
          <div className="stage-orbit orbit-two" />
          <div className="stage-card">
            <img src={heroImage} alt="Minh họa giao diện LazyManager" />
            <div className="stage-grid" aria-hidden="true">
              <span>Sáng</span>
              <span>Chiều</span>
              <span>OFF</span>
              <strong>7 ngày</strong>
            </div>
          </div>
        </div>
      </section>

      <section className="home-swiper" aria-labelledby="swiper-title">
        <div className="swiper-copy">
          <p className="home-kicker">Luồng chính</p>
          <h2 id="swiper-title">Swiper cho những việc quản lý cần thấy ngay</h2>
        </div>

        <div
          className="swiper-frame"
          onPointerDown={(event) => {
            pointerStart.current = event.clientX
          }}
          onPointerCancel={() => {
            pointerStart.current = null
          }}
          onPointerUp={(event) => finishPointerSwipe(event.clientX)}
        >
          <article className="swiper-slide" key={slide.title}>
            <p>{slide.eyebrow}</p>
            <h3>{slide.title}</h3>
            <span>{slide.body}</span>
          </article>

          <div className="swiper-controls" aria-label="Điều khiển swiper">
            <button type="button" onClick={showPreviousSlide} aria-label="Slide trước">
              ‹
            </button>
            <div className="swiper-dots">
              {dashboardSlides.map((item, index) => (
                <button
                  type="button"
                  className={index === activeSlide ? 'active' : ''}
                  key={item.title}
                  onClick={() => setActiveSlide(index)}
                  aria-label={`Mở slide ${String(index + 1)}`}
                />
              ))}
            </div>
            <button type="button" onClick={showNextSlide} aria-label="Slide sau">
              ›
            </button>
          </div>
        </div>
      </section>

      <section className="home-bento" aria-label="Điểm nổi bật">
        {featureCards.map((feature, index) => (
          <article className={`bento-card card-${String(index + 1)}`} key={feature.title}>
            <span>{String(index + 1).padStart(2, '0')}</span>
            <h3>{feature.title}</h3>
            <p>{feature.body}</p>
          </article>
        ))}
      </section>
    </main>
  )
}
