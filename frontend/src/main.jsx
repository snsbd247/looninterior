import ReactDOM from 'react-dom/client'
import { BrowserRouter } from 'react-router-dom'
import App from './App'
import { BASE_PATH } from './lib/api'
import './styles/site.css'

ReactDOM.createRoot(document.getElementById('root')).render(
  <BrowserRouter basename={BASE_PATH || '/'}>
    <App />
  </BrowserRouter>
)
