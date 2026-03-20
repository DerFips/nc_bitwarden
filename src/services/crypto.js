/**
 * Bitwarden Crypto – Client-Side Vault Decryption
 * PBKDF2 / Argon2id · AES-256-CBC · HMAC-SHA256 · RSA-OAEP (Org-Keys)
 */

const encoder = new TextEncoder()
const decoder = new TextDecoder()

// ─── Helpers ──────────────────────────────────────────────────────────────────

export function b64ToBuffer(b64) {
  const binary = atob(b64)
  const bytes  = new Uint8Array(binary.length)
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i)
  return bytes.buffer
}

export function bufferToB64(buffer) {
  const bytes  = new Uint8Array(buffer)
  let   binary = ''
  for (const b of bytes) binary += String.fromCharCode(b)
  return btoa(binary)
}

// ─── Key Derivation ───────────────────────────────────────────────────────────

export async function deriveMasterKeyPBKDF2(password, email, iterations = 600000) {
  const mat = await crypto.subtle.importKey('raw', encoder.encode(password), 'PBKDF2', false, ['deriveBits'])
  return crypto.subtle.deriveBits(
    { name: 'PBKDF2', hash: 'SHA-256', salt: encoder.encode(email.trim().toLowerCase()), iterations },
    mat, 256
  )
}

export async function deriveMasterKeyArgon2id(password, email, memory, iterations, parallelism) {
  const { argon2id }  = await import('@noble/hashes/argon2')
  const saltBuffer    = await crypto.subtle.digest('SHA-256', encoder.encode(email.trim().toLowerCase()))
  const hash = argon2id(encoder.encode(password), new Uint8Array(saltBuffer), {
    t: iterations, m: memory * 1024, p: parallelism, dkLen: 32,
  })
  return hash.buffer
}

export async function makeMasterPasswordHash(masterKeyBuffer, password) {
  const key  = await crypto.subtle.importKey('raw', masterKeyBuffer, 'PBKDF2', false, ['deriveBits'])
  const hash = await crypto.subtle.deriveBits(
    { name: 'PBKDF2', hash: 'SHA-256', salt: encoder.encode(password), iterations: 1 },
    key, 256
  )
  return bufferToB64(hash)
}

// HKDF-Expand (kein Extract) – entspricht Bitwarden hkdfExpand()
async function hkdfExpand(prkBuffer, info, outputLen = 32) {
  const prk   = await crypto.subtle.importKey('raw', prkBuffer, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign'])
  const input = new Uint8Array(encoder.encode(info).length + 1)
  input.set(encoder.encode(info))
  input[input.length - 1] = 0x01
  return new Uint8Array(await crypto.subtle.sign('HMAC', prk, input)).slice(0, outputLen).buffer
}

export async function stretchMasterKey(masterKeyBuffer) {
  const [encKey, macKey] = await Promise.all([
    hkdfExpand(masterKeyBuffer, 'enc', 32),
    hkdfExpand(masterKeyBuffer, 'mac', 32),
  ])
  return { encKey, macKey }
}

// ─── EncString Parsing ────────────────────────────────────────────────────────

/**
 * Parsed AES-EncStrings (Typ 0/1/2) → { type, iv, ct, mac }
 * Parsed RSA-EncStrings (Typ 3/4/5/6) → { type, ct }  (kein IV)
 */
export function parseEncString(encStr) {
  if (!encStr || typeof encStr !== 'string') return null
  const dotIdx = encStr.indexOf('.')
  if (dotIdx < 0) return null
  const type  = parseInt(encStr.substring(0, dotIdx), 10)
  const parts = encStr.substring(dotIdx + 1).split('|')

  // RSA-Typen 3/4/5/6: kein IV, nur Ciphertext (+ optionaler MAC)
  if (type >= 3 && type <= 6) {
    if (!parts[0]) return null
    return { type, ct: b64ToBuffer(parts[0]), mac: parts[1] ? b64ToBuffer(parts[1]) : null }
  }

  // AES-Typen 0/1/2: IV | CT | MAC
  if (parts.length < 2) return null
  return {
    type,
    iv:  b64ToBuffer(parts[0]),
    ct:  b64ToBuffer(parts[1]),
    mac: parts[2] ? b64ToBuffer(parts[2]) : null,
  }
}

// ─── AES-CBC Decryption ───────────────────────────────────────────────────────

async function verifyHmac(iv, ct, mac, macKeyBuffer) {
  const macKey   = await crypto.subtle.importKey('raw', macKeyBuffer, { name: 'HMAC', hash: 'SHA-256' }, false, ['verify'])
  const combined = new Uint8Array(iv.byteLength + ct.byteLength)
  combined.set(new Uint8Array(iv))
  combined.set(new Uint8Array(ct), iv.byteLength)
  return crypto.subtle.verify('HMAC', macKey, mac, combined)
}

export async function decryptEncStringRaw(encStr, encKeyBuffer, macKeyBuffer) {
  const parsed = parseEncString(encStr)
  if (!parsed) throw new Error(`Ungültiger EncString: ${String(encStr).substring(0, 20)}`)
  if (parsed.mac && macKeyBuffer) {
    const valid = await verifyHmac(parsed.iv, parsed.ct, parsed.mac, macKeyBuffer)
    if (!valid) throw new Error('HMAC-Verifikation fehlgeschlagen')
  }
  const decKey = await crypto.subtle.importKey('raw', encKeyBuffer, { name: 'AES-CBC' }, false, ['decrypt'])
  return crypto.subtle.decrypt({ name: 'AES-CBC', iv: parsed.iv }, decKey, parsed.ct)
}

/** FIX 1: decryptEncString gibt '' zurück wenn encStr leer/null – kein Crash */
export async function decryptEncString(encStr, encKeyBuffer, macKeyBuffer) {
  if (!encStr) return ''
  const raw = await decryptEncStringRaw(encStr, encKeyBuffer, macKeyBuffer)
  return decoder.decode(raw)
}

// ─── User Symmetric Key ───────────────────────────────────────────────────────

export async function decryptUserSymmetricKey(encKeyString, masterKeyBuffer) {
  const stretched = await stretchMasterKey(masterKeyBuffer)
  const raw       = await decryptEncStringRaw(encKeyString, stretched.encKey, stretched.macKey)
  const bytes     = new Uint8Array(raw)
  return { encKey: bytes.slice(0, 32).buffer, macKey: bytes.slice(32, 64).buffer }
}

// ─── RSA: Organisation-Key Decryption ─────────────────────────────────────────

/**
 * FIX 3a: RSA Private Key entschlüsseln
 * Profile.PrivateKey = AES-CBC-256-HMAC (Typ 2) verschlüsselt mit User Symmetric Key
 */
export async function decryptRsaPrivateKey(encPrivateKeyStr, userKey) {
  const rawPkcs8 = await decryptEncStringRaw(encPrivateKeyStr, userKey.encKey, userKey.macKey)
  // Zuerst SHA-1 versuchen (Bitwarden-Standard), dann SHA-256 als Fallback
  for (const hash of ['SHA-1', 'SHA-256']) {
    try {
      return await crypto.subtle.importKey(
        'pkcs8', rawPkcs8, { name: 'RSA-OAEP', hash }, false, ['decrypt']
      )
    } catch { /* nächsten Hash versuchen */ }
  }
  throw new Error('RSA Private Key konnte nicht importiert werden')
}

/**
 * FIX 3b: Organisations-Keys entschlüsseln
 * org.Key = RSA-OAEP (Typ 4 oder 6) verschlüsselt mit User RSA Public Key
 * Ergebnis: Map { orgId → { encKey, macKey } }
 */
export async function decryptOrgKeys(organizations = [], rsaPrivateKey) {
  const keys = {}
  await Promise.allSettled(organizations.map(async (org) => {
    try {
      const parsed = parseEncString(org.Key)
      if (!parsed || !parsed.ct) return
      const raw   = await crypto.subtle.decrypt({ name: 'RSA-OAEP' }, rsaPrivateKey, parsed.ct)
      const bytes = new Uint8Array(raw)
      keys[org.Id] = { encKey: bytes.slice(0, 32).buffer, macKey: bytes.slice(32, 64).buffer }
    } catch (e) {
      console.warn(`[nc_bitwarden] Org-Key ${org.Id} nicht entschlüsselbar:`, e.message)
    }
  }))
  return keys
}

// ─── Encryption ───────────────────────────────────────────────────────────────

export async function encryptString(plaintext, encKeyBuffer, macKeyBuffer) {
  if (!plaintext) return null
  const iv     = crypto.getRandomValues(new Uint8Array(16))
  const encKey = await crypto.subtle.importKey('raw', encKeyBuffer, { name: 'AES-CBC' }, false, ['encrypt'])
  const ct     = await crypto.subtle.encrypt({ name: 'AES-CBC', iv }, encKey, encoder.encode(plaintext))
  const combined = new Uint8Array(iv.byteLength + ct.byteLength)
  combined.set(iv)
  combined.set(new Uint8Array(ct), iv.byteLength)
  const macKey = await crypto.subtle.importKey('raw', macKeyBuffer, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign'])
  const mac    = await crypto.subtle.sign('HMAC', macKey, combined)
  return `2.${bufferToB64(iv.buffer)}|${bufferToB64(ct)}|${bufferToB64(mac)}`
}

// ─── Vault Item Decryption ────────────────────────────────────────────────────

/**
 * FIX 1+3: dec() fängt Fehler ab und gibt '' zurück statt zu crashen.
 * FIX 3:   Org-Ciphers nutzen orgKey statt userKey.
 *
 * @param {Object} cipher  – Cipher-Objekt aus Bitwarden Sync
 * @param {Object} userKey – { encKey, macKey } des eingeloggten Benutzers
 * @param {Object} orgKeys – Map { orgId → { encKey, macKey } } (kann leer sein)
 */
export async function decryptCipher(cipher, userKey, orgKeys = {}) {
  // Richtigen Schlüssel wählen: Org-Cipher → orgKey, Personal → userKey
  const key = (cipher.OrganizationId && orgKeys[cipher.OrganizationId])
    ? orgKeys[cipher.OrganizationId]
    : userKey

  // FIX 1: Fehler je Feld abfangen – ein fehlendes Feld killt nicht den ganzen Eintrag
  const dec = async (s) => {
    if (!s) return ''
    try {
      return await decryptEncString(s, key.encKey, key.macKey)
    } catch (e) {
      console.warn('[nc_bitwarden] Feld-Entschlüsselung fehlgeschlagen:', e.message)
      return ''
    }
  }

  const base = {
    id:           cipher.Id,
    type:         cipher.Type,
    folderId:     cipher.FolderId,
    favorite:     cipher.Favorite,
    name:         await dec(cipher.Name),
    notes:        await dec(cipher.Notes),
    revisionDate: cipher.RevisionDate,
    organizationId: cipher.OrganizationId ?? null,
  }

  switch (cipher.Type) {
    case 1: {
      const login = cipher.Login ?? {}
      base.login = {
        username: await dec(login.Username),
        password: await dec(login.Password),
        totp:     await dec(login.Totp),
        uris: await Promise.all((login.Uris ?? []).map(async u => ({
          uri: await dec(u.Uri), match: u.Match,
        }))),
      }
      break
    }
    case 2: break  // Secure Note – nur Name + Notes, kein weiteres Objekt
    case 3: {
      const card = cipher.Card ?? {}
      base.card = {
        cardholderName: await dec(card.CardholderName),
        brand:          await dec(card.Brand),
        number:         await dec(card.Number),
        expMonth:       await dec(card.ExpMonth),
        expYear:        await dec(card.ExpYear),
        code:           await dec(card.Code),
      }
      break
    }
    case 4: {
      const id = cipher.Identity ?? {}
      base.identity = {
        firstName: await dec(id.FirstName),
        lastName:  await dec(id.LastName),
        email:     await dec(id.Email),
        phone:     await dec(id.Phone),
        address1:  await dec(id.Address1),
        company:   await dec(id.Company),
      }
      break
    }
  }

  if (cipher.Fields?.length) {
    base.fields = await Promise.all(cipher.Fields.map(async f => ({
      type:  f.Type,
      name:  await dec(f.Name),
      value: await dec(f.Value),
    })))
  }

  return base
}
