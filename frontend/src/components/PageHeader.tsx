import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

type PageHeaderProps = {
  eyebrow: string
  title: string
  summary: string
  actions?: ReactNode
}

export function PageHeader({ actions, eyebrow, summary, title }: PageHeaderProps) {
  return (
    <section className="page-header" aria-labelledby="page-title">
      <div className="page-header-copy">
        <Link className="page-back-link" to="/">
          Trang chủ
        </Link>
        <p className="eyebrow">{eyebrow}</p>
        <h1 id="page-title">{title}</h1>
        <p className="summary">{summary}</p>
      </div>
      {actions ? <div className="page-header-actions">{actions}</div> : null}
    </section>
  )
}
