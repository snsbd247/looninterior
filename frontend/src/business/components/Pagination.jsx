/** Pager for Laravel paginate() responses (same look as the website admin lists). */
export default function Pagination({ meta, page, setPage }) {
  if (!meta || meta.last_page <= 1) return null
  return (
    <div className="a-pagination">
      <span>Showing {meta.from}–{meta.to} of {meta.total}</span>
      <div>
        <button disabled={page <= 1} onClick={() => setPage(page - 1)}>‹ Prev</button>
        <span>Page {meta.current_page} / {meta.last_page}</span>
        <button disabled={page >= meta.last_page} onClick={() => setPage(page + 1)}>Next ›</button>
      </div>
    </div>
  )
}
