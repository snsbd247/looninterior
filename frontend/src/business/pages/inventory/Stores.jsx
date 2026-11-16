import { useCallback, useEffect, useState } from 'react'
import { FiPlus, FiEdit2 } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { BranchPicker, Field, Modal, Status } from '../../components/ui'

export default function Stores() {
  const { toast } = useAdmin()
  const [rows, setRows] = useState(null)
  const [form, setForm] = useState(null)
  const [errors, setErrors] = useState({})
  const load = useCallback(() => { businessApi.get('/lookup/stores', { params: { inactive: 1 } }).then((r) => setRows(r.data.data)).catch((e) => toast(errorMessage(e), 'error')) }, [toast])
  useEffect(() => { load() }, [load])
  const save = async () => {
    try { await businessApi.post(form.id ? `/stores/${form.id}` : '/stores', { ...form, is_active: form.is_active ? 1 : 0 }); toast('Store saved'); setForm(null); load() } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  if (!rows) return <Loader />
  return (
    <>
      <div className="a-page-head"><h1>Stores / Warehouses</h1><Can perm="inventory.manage"><button className="a-btn a-btn-primary" onClick={() => { setErrors({}); setForm({ name: '', address: '', branch_id: '', is_active: true }) }}><FiPlus /> Store</button></Can></div>
      <div className="a-panel"><div className="a-table-wrap"><table className="a-table">
        <thead><tr><th>Store</th><th>Branch</th><th>Address</th><th>Status</th><th /></tr></thead>
        <tbody>{rows.map((s) => <tr key={s.id}><td><b>{s.name}</b></td><td>{s.branch?.code}</td><td>{s.address || '—'}</td><td><Status value={s.is_active ? 'active' : 'cancelled'} text={s.is_active ? 'Active' : 'Inactive'} /></td>
          <td className="a-actions"><Can perm="inventory.manage"><button className="a-icon-btn" onClick={() => { setErrors({}); setForm({ ...s }) }}><FiEdit2 /></button></Can></td></tr>)}</tbody>
      </table></div></div>
      {form && (
        <Modal title={form.id ? 'Edit store' : 'New store'} onClose={() => setForm(null)} footer={<><button className="a-btn a-btn-light" onClick={() => setForm(null)}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}>Save</button></>}>
          {!form.id && <BranchPicker value={form.branch_id} onChange={(v) => setForm({ ...form, branch_id: v })} error={errors.branch_id} />}
          <Field label="Name *" error={errors.name}><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. Tejgaon Warehouse" /></Field>
          <Field label="Address"><textarea rows={2} value={form.address || ''} onChange={(e) => setForm({ ...form, address: e.target.value })} /></Field>
          <label className="switch"><input type="checkbox" checked={!!form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} /><span className="slider" /><span className="switch-label">Active</span></label>
        </Modal>
      )}
    </>
  )
}
