<template>
  <div class="bw-detail">
    <div class="bw-detail__header">
      <h3>{{ item.name }}</h3>
      <div class="bw-detail__actions">
        <NcButton @click="$emit('close')"><CloseIcon :size="18" /></NcButton>
        <NcButton @click="confirmDelete" type="error"><DeleteIcon :size="18" /></NcButton>
      </div>
    </div>

    <template v-if="item.type === 1 && item.login">
      <FieldRow label="Benutzername" :value="item.login.username" copyable />
      <FieldRow label="Passwort"     :value="item.login.password" copyable secret />
      <FieldRow v-if="item.login.totp" label="TOTP" :value="item.login.totp" copyable />
      <div v-if="item.login.uris?.length" class="bw-detail__section">
        <label>URLs</label>
        <a v-for="(u, i) in item.login.uris" :key="i" :href="u.uri" target="_blank" rel="noopener noreferrer">{{ u.uri }}</a>
      </div>
    </template>

    <template v-if="item.type === 3 && item.card">
      <FieldRow label="Inhaber" :value="item.card.cardholderName" />
      <FieldRow label="Nummer"  :value="item.card.number"  copyable secret />
      <FieldRow label="Ablauf"  :value="`${item.card.expMonth}/${item.card.expYear}`" />
      <FieldRow label="CVV"     :value="item.card.code"    copyable secret />
    </template>

    <template v-if="item.type === 4 && item.identity">
      <FieldRow label="Name"    :value="`${item.identity.firstName} ${item.identity.lastName}`" />
      <FieldRow label="E-Mail"  :value="item.identity.email"   copyable />
      <FieldRow label="Telefon" :value="item.identity.phone" />
      <FieldRow label="Adresse" :value="item.identity.address1" />
      <FieldRow label="Firma"   :value="item.identity.company" />
    </template>

    <div v-if="item.notes" class="bw-detail__section">
      <label>Notizen</label>
      <p class="bw-detail__notes">{{ item.notes }}</p>
    </div>

    <template v-if="item.fields?.length">
      <FieldRow v-for="(f, i) in item.fields" :key="i" :label="f.name" :value="f.value" :secret="f.type === 1" copyable />
    </template>
  </div>
</template>

<script setup>
import NcButton   from '@nextcloud/vue/components/NcButton'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import CloseIcon  from 'vue-material-design-icons/Close.vue'
import FieldRow   from './FieldRow.vue'
import { BitwardenApi } from '../services/api.js'

const props = defineProps({ item: Object, userKey: Object })
const emit  = defineEmits(['close', 'update', 'delete'])

async function confirmDelete() {
  if (!confirm(`"${props.item.name}" wirklich löschen?`)) return
  await BitwardenApi.deleteCipher(props.item.id)
  emit('delete', props.item.id)
}
</script>

<style scoped>
.bw-detail__header  { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-bottom: 1px solid var(--color-border); }
.bw-detail__actions { display: flex; gap: 0.5rem; }
.bw-detail__section { padding: 0.75rem 1rem; display: flex; flex-direction: column; gap: 0.25rem; }
.bw-detail__section label { font-size: 0.75rem; font-weight: 600; color: var(--color-text-maxcontrast); text-transform: uppercase; }
.bw-detail__notes   { white-space: pre-wrap; margin: 0; }
</style>
