# Dr. Jaydeep Dalvi — Website (READ ME FIRST)

You have **two ways to deploy**. Pick ONE.

---

## ✅ OPTION A — Single File (recommended — foolproof, fixes your issue)

Folder: **`Single File Deploy/`**

```
Single File Deploy/
├── index.html     ← the ENTIRE website in one file (images + fonts + code all inside)
└── contact.php    ← the form mailer
```

**Why this fixes the blank/`{{ }}` problem:** your live site broke because the
browser could not load the separate script/image files (missing folder, wrong
path, or a blocked CDN). This single `index.html` has **everything baked in** —
no `js/`, `images/`, or `fonts/` folders needed, and no internet CDN. If the
file loads, the site works.

**Deploy:**
1. hPanel → File Manager → open `public_html`.
2. Delete whatever is in there now (old broken files).
3. Upload **`index.html`** and **`contact.php`** from `Single File Deploy/`.
4. Open your domain. Done. (It's a big file ~17 MB, so the very first load
   takes a few seconds; after that the browser caches it.)

> Downside: because images are embedded, editing images means re-exporting.
> For day-to-day text/image edits use Option B and keep this as your "known
> good" deploy.

---

## 🛠 OPTION B — Editable Folder (for making changes)

Folder: **`Site/`**

```
Site/
├── index.html      ← page markup you edit
├── contact.php     ← form mailer
├── js/             ← React + runtime, SELF-HOSTED (no CDN) — do NOT edit
├── images/         ← photos & logos (editable, one file each)
└── fonts/          ← Poppins fonts
```

The runtime is **self-hosted in `js/`** (no internet CDN) — that removes the
cause of the earlier breakage. All four folders/files must be uploaded together.

**Deploy (everything must land in `public_html`, side by side):**
1. hPanel → File Manager → `public_html` (clear old files first).
2. Upload `index.html`, `contact.php`, **and the `js/`, `images/`, `fonts/` folders**.
   Easiest reliable way: select the *contents* of `Site/`, right-click → compress
   to a **.zip**, upload the zip into `public_html`, then right-click → **Extract**.
3. Confirm the result looks like:
   ```
   public_html/
   ├── index.html
   ├── contact.php
   ├── js/       (4 files)
   ├── images/   (7 files)
   └── fonts/    (24 files)
   ```
   If `js/`, `images/`, or `fonts/` is missing or nested inside another folder,
   the page/photos/fonts won't show — that was the problem before. If in doubt,
   use Option A (single file) instead.

---

## Form setup (both options) — `contact.php`

Open `contact.php`, edit the **SMTP CONFIG** block:
- **Gmail:** 2-Step Verification ON → App Password (Google Account → Security →
  App passwords → Mail) → set `$SMTP_USER` and `$SMTP_PASS`.
- **Hostinger mailbox:** `smtp.hostinger.com`, port 465, `ssl`, mailbox password.

**reCAPTCHA (optional):** register the domain at
<https://www.google.com/recaptcha/admin> (v3), put the SECRET key in
`$RECAPTCHA_SECRET`, and add to `index.html` before `</body>`:
```html
<script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY"></script>
<script>window.RECAPTCHA_SITE_KEY='YOUR_SITE_KEY';</script>
```

Enquiries are delivered to `$TO` (currently `growthpixelagency@gmail.com`).

---

## Fixes included in this version
- **No CDN dependency** — React/runtime is self-hosted (Option B) or fully inlined
  (Option A). This fixes the `{{ }}` blank render you saw on live (it was caused
  by the browser failing to load a script from an external CDN / missing file).
- **Tablet hero + counters** tuned (no more text-over-photo at 861–1180px).
- **Footer Quick Links** point to real sections.
- **Testimonials** fit iPhone SE (≤560px) without clipping.
- **Form** posts to `contact.php` (SMTP + optional reCAPTCHA).

After it's live, turn on SSL: hPanel → Security → SSL.
