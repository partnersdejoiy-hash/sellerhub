import React from 'react'
import ReactDOM from 'react-dom/client'
import { HashRouter } from 'react-router-dom'
import App from './App'

// WordPress injects DSA_CONFIG (restUrl, nonce, user). Hash routing keeps
// deep links working without server rewrites beyond the SPA shell.
ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <HashRouter>
      <App />
    </HashRouter>
  </React.StrictMode>,
)
