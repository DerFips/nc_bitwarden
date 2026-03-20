<template>
  <div class="bw-settings">
    <h3>Bitwarden Server</h3>
    <p class="bw-settings__desc">Wähle deinen Bitwarden-Server und speichere die Einstellungen.</p>

    <NcNoteCard v-if="saved"  type="success">✅ Einstellungen gespeichert</NcNoteCard>
    <NcNoteCard v-if="error"  type="error">{{ error }}</NcNoteCard>

    <div class="bw-settings__options">
      <NcCheckboxRadioSwitch v-model="form.server_type" value="cloud_us"   name="server_type" type="radio">
        ☁️ Bitwarden Cloud (US) – <code>bitwarden.com</code>
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch v-model="form.server_type" value="cloud_eu"   name="server_type" type="radio">
        🇪🇺 Bitwarden Cloud (EU) – <code>bitwarden.eu</code>
      </NcCheckboxRadioSwitch>
      <NcCheckboxRadioSwitch v-model="form.server_type" value="selfhosted" name="server_type" type="radio">
        🏠 Selbst gehostete Instanz (Bitwarden / Vaultwarden)
      </NcCheckboxRadioSwitch>
    </div>

    <div v-if="form.server_type === 'selfhosted'" class="bw-settings__custom">
      <NcTextField v-model="form.custom_url" label="Server-URL" placeholder="https://vault.meine-domain.de"
        :helper-text="urlError || 'Basis-URL ohne /api oder /identity'" />
    </div>

    <NcButton type="primary" :disabled="saving || !!urlError" @click="save">
      {{ saving ? 'Speichern...' : 'Speichern' }}
    </NcButton>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import NcButton               from '@nextcloud/vue/components/NcButton'
import NcTextField            from '@nextcloud/vue/components/NcTextField'
import NcCheckboxRadioSwitch  from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcNoteCard             from '@nextcloud/vue/components/NcNoteCard'
import { BitwardenApi } from '../services/api.js'

const form   = reactive({ server_type: 'cloud_us', custom_url: '' })
const saved  = ref(false)
const error  = ref('')
const saving = ref(false)

const urlError = computed(() => {
  if (form.server_type !== 'selfhosted' || !form.custom_url) return ''
  try { new URL(form.custom_url); return '' } catch { return 'Ungültige URL' }
})

onMounted(async () => {
  try {
    const s = await BitwardenApi.getSettings()
    form.server_type = s.server_type
    form.custom_url  = s.custom_url
  } catch (e) { error.value = 'Einstellungen konnten nicht geladen werden' }
})

async function save() {
  if (urlError.value) return
  saving.value = true; error.value = ''
  try {
    await BitwardenApi.saveSettings({ server_type: form.server_type, custom_url: form.custom_url })
    saved.value = true
    setTimeout(() => (saved.value = false), 3000)
  } catch (e) {
    error.value = e.response?.data?.error ?? 'Fehler beim Speichern'
  } finally { saving.value = false }
}
</script>

<style scoped>
.bw-settings          { padding: 1rem 0; max-width: 560px; }
.bw-settings__desc    { color: var(--color-text-maxcontrast); margin-bottom: 1rem; }
.bw-settings__options { display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem; }
.bw-settings__custom  { margin-bottom: 1rem; }
</style>
