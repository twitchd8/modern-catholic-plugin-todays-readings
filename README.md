# Modern Catholic Plugin Suite

Part of **Modern Catholic** — modular WordPress tools for Catholic parish websites.

---

# Modern Catholic – Today’s Readings

![License: GPL-3.0-only](https://img.shields.io/badge/License-GPL--3.0--only-blue.svg)
![WordPress: 7.0+](https://img.shields.io/badge/WordPress-7.0%2B-21759b.svg)
![PHP: 7.4+](https://img.shields.io/badge/PHP-7.4%2B-777bbb.svg)

A dynamic WordPress block backed by a persistent cache of the USCCB calendar and daily-readings feed, with an exact-date USCCB link fallback.

---

## Current upstream status

Automated retrieval is currently dormant because the upstream USCCB feed presents browser verification to server-side requests. Existing cached readings remain available. When cached content is unavailable, the block links visitors to the correct dated page on USCCB.org.

The plugin does not attempt upstream retrieval during visitor rendering.

---

## Features

- Dynamic **Modern Catholic – Today’s Readings** block
- Compact-text and card layouts
- Editor controls for heading, date, liturgical color, lectionary number, citations, permitted feed description, and source link
- Persistent rolling cache covering today through the following 89 calendar dates
- Separate records for multiple Masses or celebrations on the same civil date
- Vigil handling that preserves the celebration date while filing the Mass under its actual civil date
- Background-only refresh, cache inspection, diagnostics, and manual administrator controls
- Exact-date USCCB link fallback when cached readings are unavailable

---

## Installation

1. Upload or clone `modern-catholic-plugin-todays-readings` into `wp-content/plugins/`.
2. Activate **Modern Catholic – Today’s Readings**.
3. Add the **Today’s Readings** block to a page, template, or template part.
4. Review cache and retrieval status under **Settings → USCCB Readings**.

The block is dynamic: reading data is not embedded in saved post, page, template, or Create Block Theme export content.

---

## Changelog

### 0.5.2

- Add a fully formatted GitHub README with Modern Catholic branding, compatibility badges, upstream-status guidance, and GPL-3.0-only licensing.

### 0.5.1

- Add block usage and Create Block Theme export guidance to the settings page.
- Explain the no-cache fallback behavior.

---

## License

Licensed under the GNU General Public License version 3.0 only (`GPL-3.0-only`).
