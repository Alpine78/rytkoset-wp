# 🧠 AGENTS.md — Rytköset.net WordPress Project

## 🎯 Project Overview

This repository contains the development of the **Rytköset Family Association (Rytkösten sukuseura ry)** website.

This is a **real production project**, not a demo.

Primary goals:
- Build a maintainable WordPress site for non-technical users
- Provide tools for events, communication, and media
- Keep the system simple, reliable, and long-term maintainable

Secondary goal:
- Support learning of backend thinking and real-world system design

---

## 🧭 Development Priorities (VERY IMPORTANT)

When making decisions, prioritize:

1. **Working functionality (MVP first)**
2. **Simplicity over completeness**
3. **Maintainability for non-technical users**
4. **Small, incremental progress**

Avoid:
- building full systems at once
- overengineering
- polishing UI before functionality exists

---

## 🏗️ Architecture

### Core stack
- WordPress (PHP 8.3)
- MariaDB
- Custom theme: `rytkoset-theme`

### Plugins
- AcyMailing (newsletter system)
- WooCommerce (payments, later phase)
- PhotoSwipe (gallery)

### Environments
- Local: Docker
- Dev: `dev.rytkoset.net`
- Production: `rytkoset.net`

### Deployment
- GitHub Actions → FTPS → dev
- Production deploy is manual

---

## ⚙️ Development Workflow

### Branching
- `main` = deploys automatically to dev
- Use:
  - `feature/...`
  - `fix/...`
  - `codex/...`

### Commit style
Use conventional commits:
- `feat: add event registration form`
- `fix: improve mobile navigation`
- `chore: update styles`

---

## 🧩 Implementation Strategy (CRITICAL)

Features must be implemented in **small working slices**.

Example (Event system):

❌ DO NOT:
- build full event system with payments, roles, emails at once

✅ DO:
1. Show events (CPT + template)
2. Add simple registration form
3. Save data
4. Show registrations in admin

Then iterate.

---

## 🧱 Key Features (High-level)

### 1. Website & Content
- Static pages
- Blog / news
- Navigation

### 2. Media
- Photo galleries
- Albums
- Video support

### 3. Events (IMPORTANT)
- Event CPT
- Registration system (incremental)
- Admin tools

### 4. WooCommerce (LATER)
- Membership payments
- Digital products
- Paid events

### 5. Newsletter
- AcyMailing
- Mailing lists
- Basic automations

---

## 🚦 Feature Status Awareness

Current state:
- UI and theme foundation mostly complete
- Media system in progress
- Event system NOT implemented yet

👉 Therefore:
**New work should focus on Event system MVP**

---

## 🧠 Information Accuracy & Uncertainty Handling (CRITICAL)

- NEVER invent:
  - technical constraints
  - plugin capabilities
  - system behavior

If unsure:
→ say clearly:
"Tarvitsen lisätietoa ennen tarkkaa vastausta"

Always separate:
1. Facts
2. Assumptions
3. Recommendations

---

## 🎨 Frontend Guidelines

DO:
- Mobile-first
- WCAG 2.1 AA
- Semantic HTML
- CSS variables

DO NOT:
- Add frameworks (no Bootstrap)
- Overcomplicate UI

---

## 🧱 Backend Guidelines

Prefer:
- Custom Post Types
- Post meta
- WordPress hooks

Avoid:
- Custom DB tables unless necessary
- Complex abstractions

---

## 🧪 Testing Strategy

Always test in dev environment:

- mobile view
- navigation
- forms
- data saving
- admin usability

---

## 📬 Email Constraints

- AcyMailing Essential
- ~18 emails/hour limit

Implications:
- batching required
- avoid heavy automation early

---

## 🗂️ GitHub Workflow

Use:
- epics → features → tasks

Focus:
- small issues
- clear scope
- visible progress

---

## 🤖 AI Usage Guidelines

### ALWAYS:
- propose small steps
- keep solutions simple
- follow existing structure

### NEVER:
- generate large systems in one go
- introduce heavy dependencies
- break accessibility

### WHEN UNSURE:
- ask clarifying questions
- propose 2–3 options

---

## 🚫 Known Constraints

- Finnish-only site
- No Bootstrap
- No heavy frameworks
- No Node-based tooling
- Manual changelog

---

## 📦 Important Paths

- `wp-content/themes/rytkoset-theme/`
- `docs/`
- `CHANGELOG.md`
- `.github/workflows/`

---

## 🎓 Learning Awareness (Light Guidance)

This project also supports learning:

Prefer:
- solutions that improve backend understanding
- simple data flows (CPT → form → save → admin)

Avoid:
- spending excessive time on visual polish

---

## 🧭 If starting a new task

1. Identify scope (feature / bug / task)
2. Check existing issues
3. Propose minimal implementation
4. Implement one step only
5. Test in dev

---

## 📌 Final Principle

> "Make it work → make it simple → then improve."

This is a **real system for real users**, not a perfect technical showcase.
