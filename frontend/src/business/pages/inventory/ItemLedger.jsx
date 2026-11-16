import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { FiArrowLeft } from 'react-icons/fi'
import { businessApi, errorMessage, formatDate } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { useAdmin } from '../../../admin/AdminApp'
import { label, money } from '../../lib'

export default function ItemLedger() {
  const { id } = useParams()
  const { toast } = useAdmin()
  const [data, setData] = useState(null)
  useEffect(() => { businessApi.get(`/stock/items/${id}`).then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error')) }, [id, toast])
  if (!data) return <Loader />
  const it = data.data
  return (
    <>
      <div className="a-page-head">
        <h1>{it.name} <span className="a-muted b-sub">{it.sku} · {it.unit} · avg {money(it.avg_cost)}</span></h1>
        <Link to="/admin/biz/stock" className="a-btn a-btn-light"><FiArrowLeft /> Stock</Link>
      </div>
      <div className="a-panel">
        <div className="a-panel-head"><h3>Movements (latest first)</h3></div>
        <div className="a-table-wrap">
          <table className="a-table">
            <thead><tr><th>Date</th><th>Type</th><th>Store</th><th>Project</th><th>Note</th><th className="r">Qty</th><th className="r">Unit cost</th><th className="r">Balance</th></tr></thead>
            <tbody>
              {data.lines.length ? data.lines.map((m) => (
                <tr key={m.id}>
                  <td className="b-nowrap">{formatDate(m.date)}</td><td>{label(m.type)}</td><td>{m.store?.name}</td>
                  <td>{m.project ? <Link to={`/admin/biz/projects/${m.work_project_id}`}>{m.project.code}</Link> : '—'}</td>
                  <td className="b-sub">{m.note}</td>
                  <td className={`r ${Number(m.qty) >= 0 ? 'b-in' : 'b-out'}`}>{Number(m.qty) > 0 ? '+' : ''}{Number(m.qty)}</td>
                  <td className="r">{money(m.unit_cost)}</td>
                  <td className="r"><b>{m.balance}</b></td>
                </tr>
              )) : <tr><td colSpan={8} className="a-empty">No movements yet</td></tr>}
            </tbody>
          </table>
        </div>
      </div>
    </>
  )
}
