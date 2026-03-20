<template>
  <div class="bw-login">
    <div class="bw-login__card">
      <img src="../../img/app.svg" class="bw-login__logo" alt="Bitwarden" />
      <h2>Bitwarden entsperren</h2>
      <NcNoteCard v-if="error" type="error">{{ error }}</NcNoteCard>
      <div class="bw-login__field">
        <NcTextField v-model="email" label="E-Mail" type="email" :disabled="loading" />
      </div>
      <div class="bw-login__field">
        <NcPasswordField v-model="masterPassword" label="Master-Passwort" :disabled="loading" @keyup.enter="doLogin" />
      </div>
      <NcButton type="primary" :disabled="loading || !email || !masterPassword" @click="doLogin" wide>
        <template #icon><NcLoadingIcon v-if="loading" :size="20" /></template>
        {{ loading ? 'Einloggen...' : 'Entsperren' }}
      </NcButton>
      <p class="bw-login__hint">
        🔒 Dein Master-Passwort verlässt niemals diesen Browser.
        Nur der abgeleitete Hash wird zur Authentifizierung verwendet.
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import NcTextField     from '@nextcloud/vue/components/NcTextField'
import NcPasswordField from '@nextcloud/vue/components/NcPasswordField'
import NcButton        from '@nextcloud/vue/components/NcButton'
import NcNoteCard      from '@nextcloud/vue/components/NcNoteCard'
import NcLoadingIcon   from '@nextcloud/vue/components/NcLoadingIcon'
import { BitwardenApi } from '../services/api.js'
import {
  deriveMasterKeyPBKDF2, deriveMasterKeyArgon2id,
  makeMasterPasswordHash, decryptUserSymmetricKey,
} from '../services/crypto.js'

// camelCase (Vaultwarden) → PascalCase (Bitwarden Cloud) Normalizer
function toPascal(o) {
  if (Array.isArray(o)) return o.map(toPascal)
  if (o !== null && typeof o === 'object')
    return Object.fromEntries(Object.entries(o).map(([k, v]) => [k[0].toUpperCase() + k.slice(1), toPascal(v)]))
  return o
}

const emit           = defineEmits(['logged-in'])
const email          = ref('')
const masterPassword = ref('')
const loading        = ref(false)
const error          = ref('')

async function doLogin() {
  error.value   = ''
  loading.value = true
  try {
    const kdfParams = await BitwardenApi.prelogin(email.value)

    // Bitwarden API gibt PascalCase zurück (Kdf, KdfIterations, KdfMemory, KdfParallelism)
    // Normalisierung: beide Varianten abfangen
    const kdfType        = kdfParams.Kdf          ?? kdfParams.kdf          ?? 0
    // Plausibilitätsprüfung gegen DoS durch manipulierte KDF-Parameter
    const kdfIterations  = Math.min(kdfParams.KdfIterations ?? kdfParams.kdfIterations ?? 600000, 2_000_000)
    const kdfMemory      = Math.min(kdfParams.KdfMemory     ?? kdfParams.kdfMemory     ?? 64,      256)
    const kdfParallelism = Math.min(kdfParams.KdfParallelism ?? kdfParams.kdfParallelism ?? 4,     16)

    let masterKeyBuffer
    if (kdfType === 1) {
      // Argon2id
      masterKeyBuffer = await deriveMasterKeyArgon2id(
        masterPassword.value, email.value,
        kdfMemory, kdfIterations, kdfParallelism,
      )
    } else {
      // PBKDF2-SHA256 (Standard)
      masterKeyBuffer = await deriveMasterKeyPBKDF2(
        masterPassword.value, email.value, kdfIterations,
      )
    }

    const passwordHash = await makeMasterPasswordHash(masterKeyBuffer, masterPassword.value)
    // Login-Response normalisieren (Vaultwarden: camelCase, Bitwarden Cloud: PascalCase)
    const loginData    = toPascal(await BitwardenApi.login(email.value, passwordHash))

    // Nach Normalisierung immer PascalCase: 'Key'
    const encUserKey = loginData.Key
    if (!encUserKey) {
      throw new Error('Kein User-Key in der Antwort – prüfe E-Mail und Passwort.')
    }

    const userKey = await decryptUserSymmetricKey(encUserKey, masterKeyBuffer)
    masterPassword.value = ''
    emit('logged-in', { masterKey: userKey, loginData })

  } catch (e) {
    // Axios-Fehler: e.response.data.error  |  direkter Fehler: e.message
    const serverMsg = e.response?.data?.error ?? e.response?.data?.message
    error.value = serverMsg ?? e.message ?? 'Login fehlgeschlagen'
    console.error('[nc_bitwarden] Login-Fehler:', e)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.bw-login        { display: flex; justify-content: center; align-items: center; height: 100%; padding: 2rem; }
.bw-login__card  { max-width: 400px; width: 100%; padding: 2rem; border-radius: var(--border-radius-large); box-shadow: var(--box-shadow); background: var(--color-main-background); }
.bw-login__logo  { width: 64px; display: block; margin: 0 auto 1rem; }
.bw-login__field { margin-bottom: 1rem; }
.bw-login__hint  { font-size: 0.8rem; color: var(--color-text-maxcontrast); margin-top: 1rem; text-align: center; }
</style>
