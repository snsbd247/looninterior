import { useEffect, useState } from 'react'
import { api } from './api'

export function useFetch(url) {
  const [state, setState] = useState({ data: null, loading: true, error: null })

  useEffect(() => {
    if (!url) return
    let alive = true
    setState((s) => ({ ...s, loading: true, error: null }))
    api.get(url)
      .then((res) => alive && setState({ data: res.data, loading: false, error: null }))
      .catch((err) => alive && setState({ data: null, loading: false, error: err }))
    return () => { alive = false }
  }, [url])

  return state
}
