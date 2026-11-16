import { useEffect, useState } from 'react'
import { FiSave, FiSend } from 'react-icons/fi'
import { adminApi, errorMessage } from '../../lib/api'
import Loader from '../../components/Loader'
import { SETTINGS_TABS } from '../settingsSchema'
import { FieldControl, ImageInput } from '../Fields'
import { useAdmin } from '../AdminApp'

const ALL_FIELDS = SETTINGS_TABS.flatMap((t) => t.fields)

export default function Settings() {
  const { toast } = useAdmin()
  const [tab, setTab] = useState(SETTINGS_TABS[0].key)
  const [saved, setSaved] = useState(null)
  const [values, setValues] = useState({})
  const [files, setFiles] = useState({})
  const [removed, setRemoved] = useState({})
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)

  useEffect(() => {
    adminApi.get('/settings').then((r) => {
      setSaved(r.data.data)
      setValues(r.data.data)
    }).catch((e) => toast(errorMessage(e), 'error'))
  }, [toast])

  if (!saved) return <Loader />

  const submit = async (e) => {
    e.preventDefault()
    setSaving(true)
    const fd = new FormData()
    ALL_FIELDS.forEach((f) => {
      if (f.type === 'image') {
        if (files[f.name]) fd.append(f.name, files[f.name])
        else if (removed[f.name]) fd.append(`remove_${f.name}`, '1')
      } else {
        fd.append(f.name, values[f.name] ?? '')
      }
    })
    try {
      const res = await adminApi.post('/settings', fd)
      setSaved(res.data.data)
      setValues(res.data.data)
      setFiles({})
      setRemoved({})
      toast('Settings saved')
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setSaving(false)
    }
  }

  const testMail = async () => {
    setTesting(true)
    try {
      const res = await adminApi.post('/settings/test-mail')
      toast(res.data.message)
    } catch (err) {
      toast(errorMessage(err), 'error')
    } finally {
      setTesting(false)
    }
  }

  const current = SETTINGS_TABS.find((t) => t.key === tab)

  return (
    <>
      <div className="a-page-head"><h1>Site Settings</h1></div>
      <div className="a-tabs">
        {SETTINGS_TABS.map((t) => (
          <button key={t.key} type="button" className={t.key === tab ? 'is-active' : ''} onClick={() => setTab(t.key)}>{t.label}</button>
        ))}
      </div>
      <form className="a-panel a-form" onSubmit={submit}>
        <div className="a-form-grid">
          {current.fields.map((f) => (
            <div key={f.name} className={`a-field ${f.half ? 'half' : 'full'}`}>
              <span>{f.label}</span>
              {f.type === 'image' ? (
                <ImageInput
                  value={saved[f.name]}
                  file={files[f.name]}
                  removed={removed[f.name]}
                  onFile={(file) => { setFiles((s) => ({ ...s, [f.name]: file })); setRemoved((s) => ({ ...s, [f.name]: false })) }}
                  onRemove={() => { setFiles((s) => ({ ...s, [f.name]: null })); setRemoved((s) => ({ ...s, [f.name]: true })) }}
                  onRestore={() => setRemoved((s) => ({ ...s, [f.name]: false }))}
                />
              ) : (
                <FieldControl f={f} value={values[f.name]} onChange={(v) => setValues((s) => ({ ...s, [f.name]: v }))} />
              )}
              {f.help && <small className="a-help">{f.help}</small>}
            </div>
          ))}
        </div>
        <div className="a-form-actions">
          <button className="a-btn a-btn-primary" disabled={saving}><FiSave /> {saving ? 'Saving…' : 'Save Settings'}</button>
          {current.testMail && (
            <button type="button" className="a-btn a-btn-light" onClick={testMail} disabled={testing}>
              <FiSend /> {testing ? 'Sending…' : 'Send Test E-mail'}
            </button>
          )}
          <span className="a-muted">{current.testMail ? 'Save first, then send a test e-mail' : 'Saves all tabs at once'}</span>
        </div>
      </form>
    </>
  )
}
