# STATRA — Frontend API Integration Guide

The backend is now live and provides real authentication and check-in storage.
This document lists every change you need to make in the Next.js frontend to connect to it.

---

## Overview of what changes

| Area              | What changes                                                        |
|-------------------|---------------------------------------------------------------------|
| `src/utils/api.ts`| Rewrite all 3 functions to use real API + add token to requests     |
| `src/app/page.tsx`| Store token after login/register, load history from API on mount   |
| `src/types/index.ts` | Add `token` to the `User` type                                  |

That's it — no components need to change.

---

## Step 1 — Set your API base URL

Create (or update) `.env.local` in the project root:

```
NEXT_PUBLIC_API_URL=https://your-backend-domain.com/api
```

For local development this might be `http://localhost:8000/api`.

---

## Step 2 — Update `src/types/index.ts`

Add `token` to the `User` type so it can be stored and passed around:

```ts
export type User = {
  username: string;
  name: string;
  token: string;       // ← add this
};
```

---

## Step 3 — Rewrite `src/utils/api.ts`

Replace the entire file with this:

```ts
import type { CheckInResult, FormData } from '@/types'

const API_BASE = process.env.NEXT_PUBLIC_API_URL || ''

// ─── helpers ────────────────────────────────────────────────────────────────

function getToken(): string {
  return typeof window !== 'undefined'
    ? (localStorage.getItem('statra_token') ?? '')
    : ''
}

function authHeaders(): HeadersInit {
  return {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${getToken()}`,
  }
}

// ─── auth ────────────────────────────────────────────────────────────────────

export async function loginUser(
  username: string,
  password: string
): Promise<{ name: string; username: string; token: string }> {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, password }),
  })

  if (!res.ok) throw new Error('Invalid username or password')

  const data = await res.json()
  localStorage.setItem('statra_token', data.token)
  return data
}

export async function registerUser(
  username: string,
  name: string,
  password: string,
  email?: string
): Promise<{ name: string; username: string; token: string }> {
  const res = await fetch(`${API_BASE}/auth/register`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username, name, password, email }),
  })

  if (!res.ok) throw new Error('Registration failed. Username may already exist.')

  const data = await res.json()
  localStorage.setItem('statra_token', data.token)
  return data
}

export async function logoutUser(): Promise<void> {
  await fetch(`${API_BASE}/auth/logout`, {
    method: 'POST',
    headers: authHeaders(),
  })
  localStorage.removeItem('statra_token')
}

// ─── check-in ────────────────────────────────────────────────────────────────

// Submit raw form fields — the backend calculates the risk score server-side
// and returns the full CheckInResult to display.
export async function submitCheckIn(
  formData: FormData,
  symptoms: string[],
  flags: string[],
  triggers: string[]
): Promise<CheckInResult> {
  const res = await fetch(`${API_BASE}/checkin`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({
      pid:       formData.pid,
      name:      formData.name,
      genotype:  formData.genotype,
      meds:      formData.meds,
      pain:      formData.pain,
      fatigue:   formData.fatigue,
      sleep:     formData.sleep,
      hydration: formData.hydration,
      condition: formData.condition,
      safety:    formData.safety,
      notes:     formData.notes,
      symptoms,
      flags,
      triggers,
    }),
  })

  if (!res.ok) throw new Error(`Submission failed: ${res.status}`)
  return res.json()
}

// Load full check-in history for the current user
export async function fetchHistory(): Promise<CheckInResult[]> {
  const res = await fetch(`${API_BASE}/checkin`, {
    headers: authHeaders(),
  })
  if (!res.ok) return []
  const data = await res.json()
  // Laravel resource collections wrap data in { data: [...] }
  return data.data ?? data
}

// Load only the latest result (for showing the Result tab on refresh)
export async function fetchLatestCheckIn(): Promise<CheckInResult | null> {
  const res = await fetch(`${API_BASE}/checkin/latest`, {
    headers: authHeaders(),
  })
  if (res.status === 404) return null
  if (!res.ok) return null
  return res.json()
}
```

---

## Step 4 — Update `src/app/page.tsx`

There are 4 places to change. Find each section by the comment or function name shown below.

### 4a. On signup — pass email and save token

Find the `handleSignup` function (or wherever `registerUser` is called).

Before (current code):
```ts
const data = await registerUser(form.username, form.name, form.password)
setUser({ username: data.username, name: data.name })
```

After:
```ts
const data = await registerUser(
  form.username,
  form.name,
  form.password,
  form.email          // pass email if your form collects it
)
setUser({ username: data.username, name: data.name, token: data.token })
```

---

### 4b. On login — save token

Find the `handleLogin` function (or wherever `loginUser` is called).

Before:
```ts
const data = await loginUser(username, password)
setUser({ username: data.username ?? username, name: data.name })
```

After:
```ts
const data = await loginUser(username, password)
setUser({ username: data.username, name: data.name, token: data.token })
```

---

### 4c. On submit — use the new `submitCheckIn` signature

The function signature changed — it no longer takes a pre-calculated `CheckInResult`.
Instead it takes raw form fields and receives the calculated result back from the server.

Before (current code):
```ts
// calcRisk was called here, then result was passed to submitCheckIn
const riskResult = calcRisk({ ... })
await submitCheckIn({ ...formData, ...riskResult, symptoms, flags, triggers })
setResult(riskResult)
```

After:
```ts
// No need to call calcRisk locally — the server does it and returns the result
const riskResult = await submitCheckIn(formData, symptoms, flags, triggers)
setResult(riskResult)
setHistory(prev => [riskResult, ...prev])
```

---

### 4d. On mount — load token + history from API

Add a `useEffect` that runs once when the app loads. This restores the session if the
user already has a token, and loads their check-in history.

```ts
useEffect(() => {
  const token = localStorage.getItem('statra_token')
  if (!token) return

  // Restore session — you may want to store username/name in localStorage too
  // or add a GET /auth/me endpoint to the backend (optional)

  // Load history
  fetchHistory().then(history => {
    if (history.length > 0) {
      setHistory(history)
      setResult(history[0])   // show latest result on the Result tab
    }
  })
}, [])
```

---

### 4e. On logout — clear token

Find the logout handler and add:

```ts
async function handleLogout() {
  await logoutUser()          // calls POST /auth/logout on the backend
  setUser(null)
  setResult(null)
  setHistory([])
}
```

---

## Step 5 — Update `src/components/auth/SignupScreen.tsx`

The `registerUser` function now accepts an optional `email` parameter. Pass it from
the signup form so it gets stored on the backend.

Find where `onSignup` is called (the `handleSubmit` function):

Before:
```ts
onSignup({
  username: form.username.trim(),
  name: `${form.firstName.trim()} ${form.lastName.trim()}`,
})
```

After:
```ts
onSignup({
  username: form.username.trim(),
  name: `${form.firstName.trim()} ${form.lastName.trim()}`,
  email: form.email.trim(),    // ← add this
  token: '',                   // placeholder — will be filled after API call
})
```

> Note: You'll need to thread `email` through the `onSignup` callback. Update the `Props`
> type in `SignupScreen.tsx` and the `handleSignup` function in `page.tsx` to accept and
> use the email field when calling `registerUser`.

---

## What the backend returns

### POST /auth/login and POST /auth/register

```json
{
  "name": "John Doe",
  "username": "patient01",
  "token": "1|abc123..."
}
```

### POST /checkin (submit)

Returns a single check-in object matching your `CheckInResult` type exactly:

```json
{
  "id": 1,
  "pid": "patient01",
  "name": "John Doe",
  "genotype": "SS",
  "meds": "Yes",
  "pain": 4,
  "fatigue": "Medium",
  "sleep": "Okay",
  "hydration": "Good",
  "condition": "Slightly different",
  "safety": "None",
  "notes": null,
  "symptoms": ["Headache"],
  "flags": [],
  "triggers": ["Stress"],
  "total": 6,
  "displayScore": "6",
  "status": "Watch closely",
  "redFlag": false,
  "reason": "Pain level is the primary risk driver today",
  "scores": {
    "pain": 3,
    "fatigue": 1,
    "sleep": 0.5,
    "hydration": 0,
    "symptoms": 1,
    "triggers": 1
  },
  "genoMult": 1.2,
  "ts": "2024-01-15T10:30:00.000000Z"
}
```

### GET /checkin (history)

Returns `{ "data": [ ...array of check-in objects... ] }`.

### GET /checkin/latest

Returns the most recent check-in object, or `404` if none exist yet.

---

## Error handling

All API errors return JSON in this shape:

```json
{ "message": "Invalid username or password" }
```

Validation errors (422) return:

```json
{
  "message": "The username field is required.",
  "errors": {
    "username": ["The username field is required."]
  }
}
```

Catch these in your existing error handling and show the `message` field to the user.

---

## Summary of file changes

| File                                    | What to do                                             |
|-----------------------------------------|--------------------------------------------------------|
| `.env.local`                            | Add `NEXT_PUBLIC_API_URL`                              |
| `src/types/index.ts`                    | Add `token: string` to `User` type                    |
| `src/utils/api.ts`                      | Replace entire file (Step 3)                           |
| `src/app/page.tsx`                      | 4 small updates (Steps 4a–4e)                          |
| `src/components/auth/SignupScreen.tsx`  | Pass `email` through `onSignup` callback (Step 5)      |
