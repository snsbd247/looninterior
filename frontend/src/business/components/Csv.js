import { businessApi, errorMessage } from '../../lib/api'

/** Download a ?format=csv report from the business API (sent with the login token). */
export async function downloadCsv(path, params, filename, toast) {
  try {
    const res = await businessApi.get(path, { params: { ...params, format: 'csv' }, responseType: 'blob' })
    const url = URL.createObjectURL(res.data)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a); a.click(); a.remove()
    setTimeout(() => URL.revokeObjectURL(url), 30000)
  } catch (e) {
    toast?.(errorMessage(e), 'error')
  }
}
