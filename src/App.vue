<template>
  <div class="bw-app">
    <LoginForm
      v-if="!isLoggedIn"
      @logged-in="onLoggedIn"
    />

    <div v-else class="bw-layout">
      <!-- Linke Sidebar: Vault-Liste -->
      <aside class="bw-layout__sidebar">
        <VaultList
          :items="items"
          :folders="folders"
          :selected-id="selectedItem?.id"
          @select="selectedItem = $event; showForm = false"
          @new="openNewForm"
          @logout="logout"
        />
      </aside>

      <!-- Hauptbereich -->
      <main class="bw-layout__main">
        <div v-if="loading" class="bw-main__loading">
          <NcLoadingIcon :size="48" />
          <p>Vault wird entschlüsselt…</p>
        </div>

        <ItemDetail
          v-else-if="selectedItem && !showForm"
          :item="selectedItem"
          :user-key="userKey"
          @close="selectedItem = null"
          @delete="onDelete"
          @edit="openEditForm"
        />

        <ItemForm
          v-else-if="showForm"
          :item="editItem"
          :user-key="userKey"
          :folders="folders"
          @close="showForm = false; editItem = null"
          @saved="onSaved"
        />

        <!-- Leer-State: kein Eintrag gewählt -->
        <div v-else class="bw-main__empty">
          <LockIcon :size="64" />
          <h3>Tresor entsperrt</h3>
          <p>{{ items.length }} {{ items.length === 1 ? 'Eintrag' : 'Einträge' }} geladen</p>
          <p>Wähle einen Eintrag aus der Liste links</p>
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import LockIcon      from 'vue-material-design-icons/Lock.vue'
import LoginForm     from './components/LoginForm.vue'
import VaultList     from './components/VaultList.vue'
import ItemDetail    from './components/ItemDetail.vue'
import ItemForm      from './components/ItemForm.vue'
import { BitwardenApi } from './services/api.js'
import {
  decryptCipher, decryptEncString,
  decryptRsaPrivateKey, decryptOrgKeys,
} from './services/crypto.js'

// camelCase (Vaultwarden) → PascalCase (Bitwarden Cloud) Normalizer
function toPascal(o) {
  if (Array.isArray(o)) return o.map(toPascal)
  if (o !== null && typeof o === 'object')
    return Object.fromEntries(Object.entries(o).map(([k, v]) => [k[0].toUpperCase() + k.slice(1), toPascal(v)]))
  return o
}

const isLoggedIn   = ref(false)
const userKey      = ref(null)
const items        = ref([])
const folders      = ref([])
const selectedItem = ref(null)
const loading      = ref(false)
const showForm     = ref(false)
const editItem     = ref(null)

async function onLoggedIn({ masterKey }) {
  userKey.value    = masterKey
  isLoggedIn.value = true
  await loadVault()
}

async function loadVault() {
  loading.value = true
  try {
    const sync = toPascal(await BitwardenApi.sync())

    // Org-Keys via RSA entschlüsseln
    let orgKeys = {}
    const orgs = sync.Profile?.Organizations ?? []
    if (sync.Profile?.PrivateKey && orgs.length > 0) {
      try {
        const rsaKey = await decryptRsaPrivateKey(sync.Profile.PrivateKey, userKey.value)
        orgKeys      = await decryptOrgKeys(orgs, rsaKey)
        console.info(`[nc_bitwarden] ${Object.keys(orgKeys).length}/${orgs.length} Org-Keys entschlüsselt`)
      } catch (e) {
        console.warn('[nc_bitwarden] Org-Key Entschlüsselung fehlgeschlagen:', e.message)
      }
    }

    // Ordner
    const folderResults = await Promise.allSettled(
      (sync.Folders ?? []).map(async f => ({
        id:   f.Id,
        name: await decryptEncString(f.Name, userKey.value.encKey, userKey.value.macKey),
      }))
    )
    folders.value = folderResults.filter(r => r.status === 'fulfilled').map(r => r.value)

    // Ciphers – ein Fehler killt nicht alle anderen
    const cipherResults = await Promise.allSettled(
      (sync.Ciphers ?? []).map(c => decryptCipher(c, userKey.value, orgKeys))
    )
    const failed = cipherResults.filter(r => r.status === 'rejected').length
    if (failed > 0) console.warn(`[nc_bitwarden] ${failed} Einträge konnten nicht entschlüsselt werden`)
    items.value = cipherResults.filter(r => r.status === 'fulfilled').map(r => r.value)

    console.info(`[nc_bitwarden] Vault geladen: ${items.value.length} Einträge, ${folders.value.length} Ordner`)
  } catch (e) {
    console.error('[nc_bitwarden] loadVault Fehler:', e)
  } finally {
    loading.value = false
  }
}

function logout()          { userKey.value = null; isLoggedIn.value = false; items.value = []; folders.value = []; selectedItem.value = null }
function onDelete(id)      { items.value = items.value.filter(i => i.id !== id); selectedItem.value = null }
function onSaved(item)     {
  const idx = items.value.findIndex(i => i.id === item.id)
  if (idx >= 0) items.value[idx] = item
  else          items.value.push(item)
  selectedItem.value = item
  showForm.value     = false
  editItem.value     = null
}
function openNewForm()     { editItem.value = null; showForm.value = true; selectedItem.value = null }
function openEditForm(item){ editItem.value = item; showForm.value = true }
</script>

<style scoped>
.bw-app {
  height: 100%;
  display: flex;
  flex-direction: column;
}

/* ── Zweispaltiges Layout ── */
.bw-layout {
  display:    flex;
  height:     100%;
  overflow:   hidden;
}

.bw-layout__sidebar {
  width:         320px;
  min-width:     280px;
  max-width:     380px;
  flex-shrink:   0;
  border-right:  1px solid var(--color-border);
  overflow:      hidden;
  display:       flex;
  flex-direction: column;
  background:    var(--color-navigation-bg, var(--color-main-background-translucent));
}

.bw-layout__main {
  flex:           1;
  overflow-y:     auto;
  /* Hintergrund identisch zur Sidebar – einheitliches Erscheinungsbild */
  background:     var(--color-main-background);
  display:        flex;
  flex-direction: column;
}

/* ── Leer-State ── */
.bw-main__empty {
  display:         flex;
  flex-direction:  column;
  align-items:     center;
  justify-content: center;
  height:          100%;
  gap:             0.75rem;
  color:           var(--color-text-maxcontrast);
  text-align:      center;
  padding:         2rem;
}
.bw-main__empty h3 { color: var(--color-main-text); font-size: 1.2rem; }

/* ── Lade-Zustand ── */
.bw-main__loading {
  display:         flex;
  flex-direction:  column;
  align-items:     center;
  justify-content: center;
  height:          100%;
  gap:             1rem;
  color:           var(--color-text-maxcontrast);
}
</style>
