<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Avatar Upload Test — Statra</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: system-ui, sans-serif; background: #0D1117; color: #EEF2F8; min-height: 100vh; display: flex; justify-content: center; padding: 48px 16px; }
  .wrap { width: 100%; max-width: 480px; }
  .eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; color: #00C8A0; margin-bottom: 6px; }
  h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
  .sub { font-size: 12px; color: #8899AA; font-family: monospace; margin-bottom: 28px; }
  .card { background: #1A2236; border: 1px solid #2A3650; border-radius: 14px; padding: 24px; display: flex; flex-direction: column; gap: 18px; }
  label { display: block; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #8899AA; margin-bottom: 7px; }
  input[type=password], input[type=text] { width: 100%; background: #0D1117; border: 1px solid #2A3650; border-radius: 8px; padding: 10px 13px; font-family: monospace; font-size: 12px; color: #EEF2F8; outline: none; }
  input[type=password]:focus, input[type=text]:focus { border-color: #00C8A0; }
  .divider { height: 1px; background: #2A3650; }
  .file-row { display: flex; gap: 10px; align-items: center; }
  input[type=file] { flex: 1; background: #0D1117; border: 1px solid #2A3650; border-radius: 8px; padding: 9px 12px; font-size: 12px; color: #8899AA; cursor: pointer; }
  #preview { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid #2A3650; display: none; flex-shrink: 0; }
  button { width: 100%; height: 44px; background: #00C8A0; color: #0D1117; font-size: 14px; font-weight: 700; border: none; border-radius: 9px; cursor: pointer; }
  button:disabled { opacity: .45; cursor: not-allowed; }
  button:hover:not(:disabled) { background: #009F80; }
  .response { display: none; flex-direction: column; gap: 10px; }
  .response.show { display: flex; }
  .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; margin-left: 8px; }
  .pill.ok  { background: rgba(0,200,160,.15); color: #00C8A0; }
  .pill.err { background: rgba(240,79,90,.15);  color: #F04F5A; }
  .resp-label { font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #8899AA; }
  pre { background: #0D1117; border: 1px solid #2A3650; border-radius: 8px; padding: 14px; font-family: monospace; font-size: 12px; white-space: pre-wrap; word-break: break-all; line-height: 1.7; max-height: 220px; overflow-y: auto; }
  .success-box { display: none; align-items: center; gap: 14px; background: rgba(0,200,160,.08); border: 1px solid rgba(0,200,160,.25); border-radius: 10px; padding: 14px; }
  .success-box.show { display: flex; }
  .success-box img { width: 52px; height: 52px; border-radius: 50%; object-fit: cover; border: 2px solid #00C8A0; flex-shrink: 0; }
  .success-box a { color: #00C8A0; font-size: 12px; word-break: break-all; }
</style>
</head>
<body>
<div class="wrap">
  <div class="eyebrow">Statra Dev Tools</div>
  <h1>Avatar Upload Test</h1>
  <p class="sub">POST /api/v1/patient/profile/avatar</p>

  <div class="card">
    <div>
      <label for="token">Bearer Token</label>
      <input type="password" id="token" placeholder="Paste patient auth token…" />
    </div>

    <div class="divider"></div>

    <div>
      <label>Image File</label>
      <div class="file-row">
        <input type="file" id="file" accept=".jpg,.jpeg,.png,.webp" />
        <img id="preview" src="" alt="preview" />
      </div>
      <p style="font-size:11px;color:#8899AA;margin-top:6px;">JPG · PNG · WEBP · max 5 MB</p>
    </div>

    <button id="btn" disabled>Upload Avatar</button>

    <div class="response" id="response">
      <div>
        <span class="resp-label">Response</span>
        <span class="pill" id="pill"></span>
      </div>
      <div class="success-box" id="successBox">
        <img id="successImg" src="" alt="avatar" />
        <div><div style="font-size:12px;color:#8899AA;margin-bottom:4px;">Uploaded successfully</div><a id="successLink" href="#" target="_blank"></a></div>
      </div>
      <pre id="body"></pre>
    </div>
  </div>
</div>

<script>
  const tokenEl = document.getElementById('token')
  const fileEl  = document.getElementById('file')
  const preview = document.getElementById('preview')
  const btn     = document.getElementById('btn')
  const response   = document.getElementById('response')
  const pill       = document.getElementById('pill')
  const bodyEl     = document.getElementById('body')
  const successBox = document.getElementById('successBox')
  const successImg  = document.getElementById('successImg')
  const successLink = document.getElementById('successLink')

  function check() {
    btn.disabled = !(tokenEl.value.trim() && fileEl.files[0])
  }

  tokenEl.addEventListener('input', check)

  fileEl.addEventListener('change', () => {
    const f = fileEl.files[0]
    if (!f) return
    preview.src = URL.createObjectURL(f)
    preview.style.display = 'block'
    check()
  })

  btn.addEventListener('click', async () => {
    btn.disabled = true
    btn.textContent = 'Uploading…'
    response.classList.remove('show')
    successBox.classList.remove('show')

    const form = new FormData()
    form.append('avatar', fileEl.files[0])

    try {
      const res  = await fetch('/api/v1/patient/profile/avatar', {
        method: 'POST',
        headers: { Authorization: 'Bearer ' + tokenEl.value.trim(), Accept: 'application/json' },
        body: form,
      })
      const json = await res.json().catch(() => null)
      const ok   = res.ok

      pill.textContent  = res.status + (ok ? ' OK' : ' Error')
      pill.className    = 'pill ' + (ok ? 'ok' : 'err')
      bodyEl.textContent = JSON.stringify(json, null, 2)
      response.classList.add('show')

      if (ok && json?.data?.avatar) {
        successImg.src        = json.data.avatar
        successLink.href      = json.data.avatar
        successLink.textContent = json.data.avatar
        successBox.classList.add('show')
      }
    } catch (e) {
      pill.textContent  = 'Network Error'
      pill.className    = 'pill err'
      bodyEl.textContent = e.message
      response.classList.add('show')
    } finally {
      btn.textContent = 'Upload Avatar'
      check()
    }
  })
</script>
</body>
</html>
