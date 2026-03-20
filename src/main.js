import { createApp } from 'vue'
import './main.css'
import App from './App.vue'

const el = document.querySelector('#nc-bitwarden-app')
if (!el) {
    console.error('[nc_bitwarden] #nc-bitwarden-app nicht gefunden!')
} else {
    const app = createApp(App)
    app.config.errorHandler = (err, _vm, info) => {
        console.error('[nc_bitwarden] Vue-Fehler:', info, err)
    }
    app.mount(el)
    console.info('[nc_bitwarden] App gemountet.')
}
