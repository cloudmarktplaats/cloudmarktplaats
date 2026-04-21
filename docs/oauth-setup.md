# OAuth & WalletConnect Setup

This guide explains how to register external auth apps and populate `.env`.

## Google OAuth

1. Go to <https://console.cloud.google.com/>.
2. Create a project (or select an existing one).
3. Navigate to **APIs & Services → Credentials → Create Credentials → OAuth client ID**.
4. Application type: **Web application**.
5. Authorized redirect URI: `https://<your-domain>/auth/oauth/google/callback` (for local dev add `http://localhost:8000/auth/oauth/google/callback`).
6. Copy the generated **Client ID** and **Client Secret** into `.env`:

        GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
        GOOGLE_CLIENT_SECRET=your-client-secret

7. On the "OAuth consent screen" page, publish the app (or add test users during development).

## GitHub OAuth

1. Go to <https://github.com/settings/developers> → **New OAuth App**.
2. Application name: `Cloudmarkplaats` (or env-specific e.g. `Cloudmarkplaats Dev`).
3. Homepage URL: your app URL.
4. Authorization callback URL: `https://<your-domain>/auth/oauth/github/callback`.
5. Copy **Client ID**, generate a **Client Secret**, and paste into `.env`:

        GITHUB_CLIENT_ID=...
        GITHUB_CLIENT_SECRET=...

## WalletConnect v2

1. Go to <https://cloud.walletconnect.com/>, sign up (free tier).
2. Create a new project.
3. Copy the **Project ID** into `.env`:

        WALLETCONNECT_PROJECT_ID=your-32-char-project-id

4. If `WALLETCONNECT_PROJECT_ID` is empty, only the MetaMask button will work on the login page; the WalletConnect button will show "WalletConnect is niet geconfigureerd."

## MetaMask

No server-side registration needed — MetaMask is a browser extension the user installs themselves. The site only needs to call `window.ethereum.request(...)` from the frontend JS.

## Sanity check

After setting all four (or subset) of the above:

- Restart the PHP dev server so `.env` is re-read.
- Visit `/auth/login` — all four buttons should be present.
- Click Google → should redirect to Google's consent screen.
- Click GitHub → should redirect to GitHub's consent screen.
- Click MetaMask → should open the MetaMask popup.
- Click WalletConnect → should open QR-modal.
