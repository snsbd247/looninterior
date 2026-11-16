import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { FiPlus, FiEdit2, FiRepeat } from 'react-icons/fi'
import { businessApi, errorMessage, fieldErrors } from '../../../lib/api'
import Loader from '../../../components/Loader'
import { Can, useAdmin } from '../../../admin/AdminApp'
import { BranchPicker, DeleteButton, Field, Modal, Stat } from '../../components/ui'
import { label, money } from '../../lib'
import { TransferModal } from './Transactions'

const TYPE_ICON = { cash: '💵', bank: '🏦', mobile: '📱' }

function AccountModal({ account, onClose, onSaved }) {
  const { toast } = useAdmin()
  const [form, setForm] = useState(account ? { ...account } : { branch_id: '', name: '', type: 'bank', bank_name: '', account_number: '', opening_balance: 0, is_active: true })
  const [errors, setErrors] = useState({})
  const save = async () => {
    try {
      await businessApi.post(account ? `/accounts/${account.id}` : '/accounts', { ...form, is_active: form.is_active ? 1 : 0 })
      toast('Account saved'); onSaved()
    } catch (e) { setErrors(fieldErrors(e)); toast(errorMessage(e), 'error') }
  }
  return (
    <Modal title={account ? 'Edit account' : 'New account'} onClose={onClose} footer={<><button className="a-btn a-btn-light" onClick={onClose}>Cancel</button><button className="a-btn a-btn-primary" onClick={save}>Save</button></>}>
      {!account && <BranchPicker value={form.branch_id} onChange={(v) => setForm({ ...form, branch_id: v })} error={errors.branch_id} />}
      <Field label="Name *" error={errors.name}><input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} placeholder="e.g. City Bank – Current, bKash Merchant" /></Field>
      <Field label="Type *" error={errors.type}>
        <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })}><option value="cash">Cash</option><option value="bank">Bank</option><option value="mobile">Mobile banking (bKash/Nagad)</option></select>
      </Field>
      {form.type !== 'cash' && <Field label="Bank / Provider"><input value={form.bank_name || ''} onChange={(e) => setForm({ ...form, bank_name: e.target.value })} /></Field>}
      {form.type !== 'cash' && <Field label="Account / Wallet number"><input value={form.account_number || ''} onChange={(e) => setForm({ ...form, account_number: e.target.value })} /></Field>}
      <Field label="Opening balance (৳)" error={errors.opening_balance} help="Balance on the day you start using this system"><input type="number" step="any" value={form.opening_balance} onChange={(e) => setForm({ ...form, opening_balance: e.target.value })} /></Field>
      <label className="switch"><input type="checkbox" checked={!!form.is_active} onChange={(e) => setForm({ ...form, is_active: e.target.checked })} /><span className="slider" /><span className="switch-label">Active</span></label>
    </Modal>
  )
}

export default function Accounts() {
  const { toast, can } = useAdmin()
  const [data, setData] = useState(null)
  const [modal, setModal] = useState(null)
  const load = useCallback(() => {
    businessApi.get('/accounts', { params: { inactive: 1 } }).then((r) => setData(r.data)).catch((e) => toast(errorMessage(e), 'error'))
  }, [toast])
  useEffect(() => { load() }, [load])
  if (!data) return <Loader />
  const active = data.data.filter((a) => a.is_active)
  const byType = (t) => active.filter((a) => a.type === t).reduce((s, a) => s + a.balance, 0)

  return (
    <>
      <div className="a-page-head">
        <h1>Cash & Bank Accounts</h1>
        <Can perm="accounts.manage">
          <div className="b-head-actions">
            <button className="a-btn a-btn-light" onClick={() => setModal('transfer')}><FiRepeat /> Transfer</button>
            <button className="a-btn a-btn-primary" onClick={() => setModal('new')}><FiPlus /> Account</button>
          </div>
        </Can>
      </div>
      <div className="b-stats">
        <Stat label="Total balance" value={money(data.total, { symbol: true })} tone="good" />
        <Stat label="Cash" value={money(byType('cash'))} />
        <Stat label="Bank" value={money(byType('bank'))} />
        <Stat label="Mobile banking" value={money(byType('mobile'))} />
      </div>
      <div className="b-account-grid">
        {data.data.map((a) => (
          <div key={a.id} className={`a-panel b-account ${a.is_active ? '' : 'is-inactive'}`}>
            <div className="b-account-head">
              <span className="b-account-icon">{TYPE_ICON[a.type]}</span>
              <div><Link to={`/admin/biz/accounts/${a.id}`}><b>{a.name}</b></Link><div className="a-muted b-sub">{label(a.type)}{a.bank_name ? ` · ${a.bank_name}` : ''}{a.account_number ? ` · ${a.account_number}` : ''}</div></div>
              {can('accounts.manage') && <button className="a-icon-btn" onClick={() => setModal(a)} title="Edit"><FiEdit2 /></button>}
              {can('accounts.manage') && <DeleteButton icon path={`/accounts/${a.id}`} what="account" onDone={load} />}
            </div>
            <div className={`b-account-balance ${a.balance < 0 ? 'is-neg' : ''}`}>{money(a.balance, { symbol: true })}</div>
            <div className="a-muted b-sub">{a.branch?.name}{a.is_active ? '' : ' · inactive'}</div>
          </div>
        ))}
      </div>
      {(modal === 'new' || (modal && modal.id)) && <AccountModal account={modal === 'new' ? null : modal} onClose={() => setModal(null)} onSaved={() => { setModal(null); load() }} />}
      {modal === 'transfer' && <TransferModal accounts={active} onClose={() => setModal(null)} onDone={() => { setModal(null); load() }} />}
    </>
  )
}
