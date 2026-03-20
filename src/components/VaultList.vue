<template>
  <div class="bw-vault">
    <!-- Suchleiste -->
    <div class="bw-vault__search">
      <NcTextField v-model="search" :label="t('Suchen...')" />
    </div>

    <!-- Ordner-Navigation -->
    <div class="bw-vault__folders">
      <button
        class="bw-folder"
        :class="{ 'bw-folder--active': selectedFolder === null }"
        @click="selectedFolder = null"
      >
        <EarthIcon :size="16" /> Alle Einträge
        <span class="bw-folder__count">{{ items.length }}</span>
      </button>
      <button
        v-for="folder in folders"
        :key="folder.id"
        class="bw-folder"
        :class="{ 'bw-folder--active': selectedFolder === folder.id }"
        @click="selectedFolder = folder.id"
      >
        <FolderIcon :size="16" /> {{ folder.name }}
        <span class="bw-folder__count">{{ folderCount(folder.id) }}</span>
      </button>
    </div>

    <!-- Einträge -->
    <div class="bw-vault__items">
      <button
        v-for="item in filtered"
        :key="item.id"
        class="bw-item"
        :class="{ 'bw-item--active': selectedId === item.id }"
        @click="$emit('select', item)"
      >
        <span class="bw-item__icon">{{ typeIcon(item.type) }}</span>
        <span class="bw-item__name">{{ item.name || '(kein Name)' }}</span>
        <span v-if="item.favorite" class="bw-item__star">★</span>
      </button>

      <div v-if="filtered.length === 0" class="bw-vault__empty">
        <LockIcon :size="32" />
        <p>{{ search ? 'Keine Treffer' : 'Keine Einträge' }}</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="bw-vault__footer">
      <NcButton @click="$emit('new')" type="primary">
        <template #icon><PlusIcon :size="16" /></template>
        Neuer Eintrag
      </NcButton>
      <NcButton @click="$emit('logout')">
        <template #icon><LogoutIcon :size="16" /></template>
        Abmelden
      </NcButton>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import NcButton    from '@nextcloud/vue/components/NcButton'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import EarthIcon   from 'vue-material-design-icons/Earth.vue'
import FolderIcon  from 'vue-material-design-icons/Folder.vue'
import LockIcon    from 'vue-material-design-icons/Lock.vue'
import PlusIcon    from 'vue-material-design-icons/Plus.vue'
import LogoutIcon  from 'vue-material-design-icons/Logout.vue'

const props = defineProps({ items: Array, folders: Array, selectedId: String })
const emit  = defineEmits(['select', 'new', 'logout'])

const search         = ref('')
const selectedFolder = ref(null)
const t              = (s) => s

const filtered = computed(() => {
  let list = props.items ?? []
  if (selectedFolder.value !== null)
    list = list.filter(i => i.folderId === selectedFolder.value)
  if (search.value.trim())
    list = list.filter(i => i.name?.toLowerCase().includes(search.value.toLowerCase()))
  return list.sort((a, b) => (a.name ?? '').localeCompare(b.name ?? ''))
})

function folderCount(id) { return (props.items ?? []).filter(i => i.folderId === id).length }
function typeIcon(t) {
  return { 1: '🔑', 2: '📝', 3: '💳', 4: '🪪' }[t] ?? '🔒'
}
</script>

<style scoped>
.bw-vault {
  display:        flex;
  flex-direction: column;
  height:         100%;
  overflow:       hidden;
  background:     var(--color-navigation-bg, var(--color-main-background-translucent));
}

/* ── Suchleiste ── */
.bw-vault__search {
  padding: 0.75rem;
  border-bottom: 1px solid var(--color-border);
}

/* ── Ordner ── */
.bw-vault__folders {
  padding:       0.5rem 0;
  border-bottom: 1px solid var(--color-border);
}

.bw-folder {
  display:        flex;
  align-items:    center;
  gap:            0.5rem;
  width:          100%;
  padding:        0.4rem 0.75rem;
  border:         none;
  background:     transparent;
  cursor:         pointer;
  color:          var(--color-main-text);
  font-size:      0.85rem;
  text-align:     left;
  border-radius:  var(--border-radius);
  transition:     background 0.1s;
}
.bw-folder:hover       { background: var(--color-background-hover); }
.bw-folder--active     { background: var(--color-primary-element-light); font-weight: 600; }
.bw-folder__count {
  margin-left:   auto;
  font-size:     0.75rem;
  color:         var(--color-text-maxcontrast);
  background:    var(--color-background-dark);
  border-radius: 10px;
  padding:       0 0.4rem;
}

/* ── Eintrags-Liste ── */
.bw-vault__items {
  flex:           1;
  overflow-y:     auto;
  padding:        0.5rem;
  display:        flex;
  flex-direction: column;
  gap:            0.25rem;
}

/* ── Einzelner Eintrag (Karten-Optik wie Sidebar-Elemente) ── */
.bw-item {
  display:       flex;
  align-items:   center;
  gap:           0.6rem;
  width:         100%;
  padding:       0.55rem 0.75rem;
  border:        1px solid var(--color-border);
  border-radius: var(--border-radius);
  background:    var(--color-main-background);
  cursor:        pointer;
  text-align:    left;
  color:         var(--color-main-text);
  font-size:     0.9rem;
  transition:    background 0.15s, border-color 0.15s, box-shadow 0.15s;
  box-shadow:    0 1px 2px rgba(0,0,0,.04);
}
.bw-item:hover {
  background:   var(--color-background-hover);
  border-color: var(--color-border-dark, var(--color-primary-element-light));
  box-shadow:   0 2px 6px rgba(0,0,0,.08);
}
.bw-item--active {
  background:   var(--color-primary-element-light);
  border-color: var(--color-primary-element);
  font-weight:  600;
}
.bw-item__icon { font-size: 1.1rem; flex-shrink: 0; }
.bw-item__name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.bw-item__star { color: #f4a800; font-size: 0.9rem; }

/* ── Leer-State ── */
.bw-vault__empty {
  display:         flex;
  flex-direction:  column;
  align-items:     center;
  justify-content: center;
  gap:             0.5rem;
  padding:         2rem;
  color:           var(--color-text-maxcontrast);
  text-align:      center;
}

/* ── Footer ── */
.bw-vault__footer {
  display:       flex;
  gap:           0.5rem;
  padding:       0.75rem;
  border-top:    1px solid var(--color-border);
  background:    var(--color-navigation-bg, var(--color-main-background-translucent));
}
</style>
