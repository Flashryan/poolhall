# Deploy the Poolhall prototype to Vercel

This folder is a **complete, self-contained static prototype** of the Poolhall
Recruitment website. `index.html` is a single bundled file — all CSS, JS,
fonts, images and the logo are inlined as data URIs. There is **no build step
and there are no external dependencies**. It just needs to be served as a static
site.

## Contents
- `index.html` — the entire prototype (~5.8 MB, self-contained).
- `vercel.json` — minimal static config (clean URLs + basic security headers).

---

## Prompt for Claude Code

> Deploy this folder to Vercel as a **static site** and give me the live URL.
>
> Details:
> - It's a single self-contained `index.html` (everything is inlined — no build, no framework, no dependencies). Do **not** add a framework, bundler, or `package.json`; do **not** run a build command.
> - Treat it as a plain static deployment. The included `vercel.json` already sets clean URLs and security headers — keep it.
> - Use the Vercel CLI: from inside this folder run `vercel --prod` (or `vercel deploy --prod`). If I'm not logged in, walk me through `vercel login` first.
> - Project name: `poolhall-prototype`. Framework preset: **Other / None**. Output/root directory: this folder (the one containing `index.html`).
> - After it deploys, print the production URL and confirm the page loads (it should show the Poolhall homepage with the four-stage hero).
>
> This is a **client-facing prototype**, so once it's live, tell me the URL I can share. If you can set the deployment to **not** be indexed by search engines (e.g. a `X-Robots-Tag: noindex` header in `vercel.json`), do that too so it doesn't get picked up by Google while it's a draft.

---

## Doing it yourself (no Claude Code)

1. Install the CLI: `npm i -g vercel`
2. `cd` into this folder.
3. Run `vercel login` (first time only).
4. Run `vercel --prod`.
5. Accept the defaults; when asked for the framework, choose **Other**.
6. Vercel prints a `https://poolhall-prototype-*.vercel.app` URL — that's your shareable link.

Alternatively, **drag-and-drop**: go to vercel.com → Add New → Project →
deploy, and drop this folder in. No CLI needed.

> Tip: to keep it private while it's a draft, add this to `vercel.json` headers:
> `{ "key": "X-Robots-Tag", "value": "noindex" }`
