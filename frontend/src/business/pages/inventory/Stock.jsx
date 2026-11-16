import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiSearch, FiDownload, FiPlus, FiAlertTriangle } from 'react-icons/fi'
import { businessApi, errorMessage } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { Money, Stat } from '../../components/ui'
import { downloadCsv } from '../../components/Csv'
import { money } from '../../lib'

export function useStores() {
  const [list, setList] = useState([])
  useEffect(() => { businessApi.get('/lookup/stores').then((r) => setList(r.data.data)).catch(() => {}) }, [])
  return list
}

export default function Stock() {
  const { toast } = useAdmin()
  const stores = useStores()
  const [filters, setFilters] = useState({ q: '', store_id: '', low: '', in_stock: '' })
  const [q, setQ] = useState('')
  const [data, setData] = useState(null)

  const load = useCallback(() => {
    const params = Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== ''))
    businessApi.get('/stock', { params }).then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [filters, toast])
  useEffect(() => { load() }, [load])

  const storeName = (id) => stores.find((s) => s.id === id)?.name || `#${id}`

  return (
    <>
      <div className="a-page-head">
        <h1>Stock</h1>
        <div className="b-head-actions">
          <button className="a-btn a-btn-light" onClick={() => downloadCsv('/stock', filters, 'stock.csv', toast)}><FiDownload /> CSV</button>
          <Can perm="inventory.manage"><Link className="a-btn a-btn-light" to="/admin/r/items/new"><FiPlus /> Item</Link></Can>
          <Can perm="inventory.manage"><Link className="a-btn a-btn-primary" to="/admin/biz/stock-entries/new"><FiPlus /> Stock Entry</Link></Can>
        </div>
      </div>
      {data && (
        <div className="b-stats">
          <Stat label="Stock value (avg cost)" value={money(data.total_value, { symbol: true })} />
          <Stat label="Items" value={data.data.length} />
          <Stat label="Low stock" value={data.low_count} tone={data.low_count ? 'bad' : ''} />
        </div>
      )}
      <div className="a-panel">
        <div className="a-toolbar">
          <form className="a-search" onSubmit={(e) => { e.preventDefault(); setFilters({ ...filters, q }) }}><FiSearch /><input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Item name or SKU…" /></form>
          <select value={filters.store_id} onChange={(e) => setFilters({ ...filters, store_id: e.target.value })}>
            <option value="">All stores</option>{stores.map((s) => <option key={s.id} value={s.id}>{s.name}{s.branch ? ` (${s.branch.code})` : ''}</option>)}
          </select>
          <select value={filters.low ? 'low' : filters.in_stock ? 'in' : ''} onChange={(e) => setFilters({ ...filters, low: e.target.value === 'low' ? 1 : '', in_stock: e.target.value === 'in' ? 1 : '' })}>
            <option value="">All items</option><option value="in">In stock</option><option value="low">Low stock</option>
          </select>
        </div>
        {!data ? <Loader /> : (
          <div className="a-table-wrap">
            <table className="a-table">
              <thead><tr><th>Item</th><th>Category</th><th className="r">Qty</th><th>By store</th><th className="r">Avg cost</th><th className="r">Value</th></tr></thead>
              <tbody>
                {data.data.length ? data.data.map((i) => (
                  <tr key={i.id} className={i.low ? 'b-low-row' : ''}>
                    <td><Link to={`/admin/biz/stock/items/${i.id}`}><b>{i.name}</b></Link>{i.sku && <div className="a-muted b-sub">{i.sku}</div>}</td>
                    <td>{i.category || '—'}</td>
                    <td className="r b-nowrap">{i.low && <FiAlertTriangle className="b-overdue" title={`Low: min ${i.min_stock}`} />} <b>{i.qty}</b> {i.unit}</td>
                    <td className="b-sub">{i.stores.map((s) => <span key={s.store_id} className="b-chip">{storeName(s.store_id)}: {s.qty}</span>)}</td>
                    <td className="r">{money(i.avg_cost)}</td>
                    <td className="r"><Money value={i.value} /></td>
                  </tr>
                )) : <tr><td colSpan={6} className="a-empty">No items. Add items first (Inventory → Items).</td></tr>}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </>
  )
}
