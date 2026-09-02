# Phase 10 — Progressive Web App

Status: **lengkap**.

- Installable manifest, root scope/start URL, 192/512 icons, dan Apple icon.
- Install prompt, service worker, offline page, dan versioned cache.
- Hanya explicit static assets termasuk local Bootstrap dicache.
- Navigation/authenticated HTML sentiasa network-only `cache: 'no-store'`; tiada dynamic `cache.put`.
- Tiada token/state dalam `localStorage` atau `sessionStorage`.

Root-origin deployment mengikut cPanel guide. Subdirectory hosting memerlukan semua PWA paths dilaras konsisten.
