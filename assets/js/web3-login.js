// Web3 SIWE login client. Activates on login page when MetaMask or WalletConnect buttons are clicked.
// Flow: connect wallet -> request nonce + SIWE message -> personal_sign -> verify -> redirect.

(function () {
  'use strict';

  const WC_PROJECT_ID = document.querySelector('meta[name="wc-project-id"]')?.content || '';
  const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content || '';

  function setStatus(msg, isError) {
    const el = document.getElementById('web3-status');
    if (!el) return;
    el.textContent = msg;
    el.className = isError ? 'text-danger small mt-2 text-center' : 'text-muted small mt-2 text-center';
  }

  async function postJson(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': CSRF_TOKEN,
      },
      body: JSON.stringify(body),
    });
    return { status: res.status, json: await res.json() };
  }

  async function getMetamaskProvider() {
    if (!window.ethereum) {
      throw new Error('MetaMask (of een andere EVM-wallet) niet gevonden.');
    }
    const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
    if (!accounts || !accounts.length) {
      throw new Error('Geen account geselecteerd.');
    }
    const chainIdHex = await window.ethereum.request({ method: 'eth_chainId' });
    return {
      address: accounts[0].toLowerCase(),
      chainId: parseInt(chainIdHex, 16),
      sign: async (msg) => window.ethereum.request({
        method: 'personal_sign',
        params: [msg, accounts[0]],
      }),
    };
  }

  async function getWalletConnectProvider() {
    if (!WC_PROJECT_ID) {
      throw new Error('WalletConnect is niet geconfigureerd.');
    }

    const { EthereumProvider } = await import('https://esm.sh/@walletconnect/ethereum-provider@2');
    const provider = await EthereumProvider.init({
      projectId: WC_PROJECT_ID,
      chains: [1],
      optionalChains: [10, 137, 8453, 42161],
      showQrModal: true,
    });
    await provider.connect();

    const accounts = await provider.request({ method: 'eth_accounts' });
    const chainIdHex = await provider.request({ method: 'eth_chainId' });

    return {
      address: accounts[0].toLowerCase(),
      chainId: parseInt(chainIdHex, 16),
      sign: async (msg) => provider.request({
        method: 'personal_sign',
        params: [msg, accounts[0]],
      }),
    };
  }

  async function runLogin(getProvider) {
    try {
      setStatus('Verbinden met wallet...');
      const wallet = await getProvider();

      setStatus('Nonce aanvragen...');
      const { status: nonceStatus, json: nonceJson } = await postJson('/auth/web3/nonce', {
        address: wallet.address,
        chain_id: wallet.chainId,
      });
      if (nonceStatus !== 200) throw new Error(nonceJson.error || 'Nonce request mislukt');

      setStatus('Bericht ondertekenen in je wallet...');
      const signature = await wallet.sign(nonceJson.message);

      setStatus('Verifiëren...');
      const { status: verStatus, json: verJson } = await postJson('/auth/web3/verify', {
        message: nonceJson.message,
        signature: signature,
      });
      if (verStatus !== 200) throw new Error(verJson.error || 'Verificatie mislukt');

      window.location.href = verJson.redirect || '/dashboard';
    } catch (err) {
      console.error(err);
      setStatus(err.message || 'Onbekende fout', true);
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    const mmBtn = document.getElementById('web3-metamask');
    const wcBtn = document.getElementById('web3-walletconnect');
    if (mmBtn) mmBtn.addEventListener('click', () => runLogin(getMetamaskProvider));
    if (wcBtn) wcBtn.addEventListener('click', () => runLogin(getWalletConnectProvider));
  });
})();
