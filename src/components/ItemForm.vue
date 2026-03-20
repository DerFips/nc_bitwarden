<template>
  <NcDialog :name="isEdit ? 'Eintrag bearbeiten' : 'Neuer Eintrag'" @close="$emit('close')" size="normal">
    <div class="bw-form">
      <div class="bw-form__field">
        <label class="bw-form__label">Typ</label>
        <div class="bw-form__radio-group">
          <NcCheckboxRadioSwitch v-for="t in typeOptions" :key="t.id"
            v-model="selectedType" :value="t.id" name="item_type" type="radio" :disabled="isEdit">
            {{ t.label }}
          </NcCheckboxRadioSwitch>
        </div>
      </div>

      <NcTextField v-model="form.name" label="Name *" class="bw-form__field" />

      <template v-if="selectedType === 1">
        <NcTextField      v-model="form.username" label="Benutzername"    class="bw-form__field" />
        <NcPasswordField  v-model="form.password" label="Passwort"        class="bw-form__field" />
        <NcTextField      v-model="form.uri"      label="URL"             class="bw-form__field" />
        <NcTextField      v-model="form.totp"     label="TOTP (optional)" class="bw-form__field" />
      </template>

      <template v-if="selectedType === 2">
        <div class="bw-form__field">
          <label class="bw-form__label">Notiz</label>
          <textarea v-model="form.notes" class="bw-form__textarea" rows="6" />
        </div>
      </template>

      <template v-if="selectedType === 3">
        <NcTextField v-model="form.cardholderName" label="Karteninhaber" class="bw-form__field" />
        <NcTextField v-model="form.cardNumber"     label="Kartennummer"  class="bw-form__field" />
        <NcTextField v-model="form.expMonth"       label="Monat (MM)"    class="bw-form__field" />
        <NcTextField v-model="form.expYear"        label="Jahr (YYYY)"   class="bw-form__field" />
        <NcTextField v-model="form.cvv"            label="CVV"           class="bw-form__field" />
      </template>

      <template v-if="selectedType === 4">
        <NcTextField v-model="form.firstName" label="Vorname"  class="bw-form__field" />
        <NcTextField v-model="form.lastName"  label="Nachname" class="bw-form__field" />
        <NcTextField v-model="form.idEmail"   label="E-Mail"   class="bw-form__field" />
        <NcTextField v-model="form.phone"     label="Telefon"  class="bw-form__field" />
        <NcTextField v-model="form.address"   label="Adresse"  class="bw-form__field" />
        <NcTextField v-model="form.company"   label="Firma"    class="bw-form__field" />
      </template>

      <NcCheckboxRadioSwitch v-model="form.favorite" type="checkbox">
        Als Favorit markieren
      </NcCheckboxRadioSwitch>
    </div>

    <template #actions>
      <NcButton @click="$emit('close')">Abbrechen</NcButton>
      <NcButton type="primary" :disabled="saving || !form.name" @click="save">
        {{ saving ? 'Speichern...' : 'Speichern' }}
      </NcButton>
    </template>
  </NcDialog>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import NcDialog               from '@nextcloud/vue/components/NcDialog'
import NcButton               from '@nextcloud/vue/components/NcButton'
import NcTextField            from '@nextcloud/vue/components/NcTextField'
import NcPasswordField        from '@nextcloud/vue/components/NcPasswordField'
import NcCheckboxRadioSwitch  from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import { BitwardenApi } from '../services/api.js'
import { encryptString, decryptCipher } from '../services/crypto.js'

const props = defineProps({ userKey: Object, item: Object })
const emit  = defineEmits(['save', 'close'])

const saving       = ref(false)
const isEdit       = computed(() => !!props.item?.id)
const selectedType = ref(props.item?.type ?? 1)

const typeOptions = [
  { id: 1, label: '🔑 Login' }, { id: 2, label: '📝 Sichere Notiz' },
  { id: 3, label: '💳 Karte' }, { id: 4, label: '👤 Identität' },
]

const form = reactive({
  name: props.item?.name ?? '', favorite: props.item?.favorite ?? false,
  username: props.item?.login?.username ?? '', password: props.item?.login?.password ?? '',
  uri: props.item?.login?.uris?.[0]?.uri ?? '', totp: props.item?.login?.totp ?? '',
  notes: props.item?.notes ?? '',
  cardholderName: props.item?.card?.cardholderName ?? '', cardNumber: props.item?.card?.number ?? '',
  expMonth: props.item?.card?.expMonth ?? '', expYear: props.item?.card?.expYear ?? '', cvv: props.item?.card?.code ?? '',
  firstName: props.item?.identity?.firstName ?? '', lastName: props.item?.identity?.lastName ?? '',
  idEmail: props.item?.identity?.email ?? '', phone: props.item?.identity?.phone ?? '',
  address: props.item?.identity?.address1 ?? '', company: props.item?.identity?.company ?? '',
})

const enc = (v) => encryptString(v, props.userKey.encKey, props.userKey.macKey)

async function buildPayload() {
  const base = { Type: selectedType.value, Name: await enc(form.name), Notes: form.notes ? await enc(form.notes) : null, Favorite: form.favorite }
  if (selectedType.value === 1) {
    base.Login = { Username: await enc(form.username), Password: await enc(form.password), Totp: form.totp ? await enc(form.totp) : null, Uris: form.uri ? [{ Uri: await enc(form.uri), Match: null }] : [] }
  } else if (selectedType.value === 3) {
    base.Card = { CardholderName: await enc(form.cardholderName), Number: await enc(form.cardNumber), ExpMonth: await enc(form.expMonth), ExpYear: await enc(form.expYear), Code: await enc(form.cvv) }
  } else if (selectedType.value === 4) {
    base.Identity = { FirstName: await enc(form.firstName), LastName: await enc(form.lastName), Email: await enc(form.idEmail), Phone: await enc(form.phone), Address1: await enc(form.address), Company: await enc(form.company) }
  }
  return base
}

async function save() {
  saving.value = true
  try {
    const payload = await buildPayload()
    const raw = isEdit.value ? await BitwardenApi.updateCipher(props.item.id, payload) : await BitwardenApi.createCipher(payload)
    emit('save', await decryptCipher(raw, props.userKey))
  } finally { saving.value = false }
}
</script>

<style scoped>
.bw-form__field       { margin-bottom: 0.75rem; }
.bw-form__label       { display: block; font-size: 0.75rem; font-weight: 600; color: var(--color-text-maxcontrast); text-transform: uppercase; margin-bottom: 0.25rem; }
.bw-form__radio-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.bw-form__textarea    { width: 100%; padding: 0.5rem; border: 1px solid var(--color-border); border-radius: var(--border-radius); background: var(--color-main-background); color: var(--color-main-text); resize: vertical; font-family: inherit; }
</style>
