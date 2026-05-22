# 🧠 AGENTS.md — Rytköset.net WordPress Project

## 🎯 Project Overview

This repository contains the development of the **Rytköset Family Association (Rytkösten sukuseura ry)** website.

This is a **real production project**, not a demo.

Primary goals:
- Build a maintainable WordPress site for non-technical users
- Provide tools for events, communication, and media
- Keep the system simple, reliable, and long-term maintainable

Secondary goal:
- Support learning of backend thinking and AI-assisted development

---

## 🧭 Development Priorities (VERY IMPORTANT)

When making decisions, prioritize:

1. Working functionality (MVP first)
2. Simplicity over completeness
3. Maintainability for non-technical users
4. Small, incremental progress

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
- GitHub Actions → FTPS → dev from the `dev` branch
- Production deploy is manual

---

## ⚙️ Development Workflow

### Branching
- `dev` = deploys automatically to dev when theme files change
- `main` = primary integration branch; no automatic dev deploy
- Use:
  - `feature/...`
  - `fix/...`
  - `codex/...`

### Commit style
Use conventional commits:
- `feat: add event registration form`
- `fix: improve mobile navigation`
- `chore: update styles`

### Commit workflow
- Do not create commits automatically when a task appears complete
- First report that the ticket requirements are implemented
- Then suggest a commit message so the user can review changes in the editor before committing

### Tool approval discipline
- Minimize permission prompts.
- Batch related local test actions into one command when practical.
- For Playwright testing, prefer one `playwright-cli.cmd run-code` script over many separate `fill` / `click` commands.
- If escalation is required for safe local testing, request one reasonably scoped persistent prefix instead of repeated one-off approvals.
- Never use broader approval for destructive commands, production actions, secret handling, or Git history rewriting.

---

## 🧩 Implementation Strategy (CRITICAL)

Features must be implemented in small working slices.

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

## 🚦 Feature Status Awareness

Current state:
- UI and theme foundation mostly complete
- Media system (EPIC 2) largely complete: gallery albums, media library ordering, PhotoSwipe 5
- Event system (EPIC 5) partially implemented: CPT, registration flow, participant admin, messaging, and event organizer role exist
- WooCommerce (EPIC 4) partially implemented: membership products, Tampere 2026 fee, Mollie payments

👉 Therefore:
Before starting new work, verify the current issue scope and existing implementation first.

---

## 🧱 Key Features (High-level)

### 1. Website & Content
- Static pages
- Blog / news
- Navigation

### 2. Media (CURRENT FOCUS)
- Photo galleries
- Albums
- Video support (basic first, then improve)

### 3. Events (NEXT PHASE)
- Event CPT
- Registration system (incremental)

### 4. WooCommerce (LATER)
- Membership payments
- Digital products
- Paid events

### 5. Newsletter
- AcyMailing
- Mailing lists
- Basic automations

---

## 🧠 Information Accuracy & Anti-Hallucination Rules (CRITICAL)

### Absolute rules

- NEVER invent:
  - technical facts
  - plugin capabilities
  - system behavior
  - APIs, functions, or WordPress features that are uncertain

- NEVER assume missing information

- NEVER present guesses as facts

---

### If information is missing

The AI MUST STOP and say clearly:

> "I don't have enough information to answer this correctly."

Then request clarification:

- ask for code
- ask for file contents
- ask for requirements
- ask for documentation

Examples:

- "Can you show the current CPT implementation?"
- "What plugin are you using for this?"
- "Is this stored in post meta or custom tables?"

---

### Required response structure

When giving answers, always separate:

1. Facts (verified)
2. Assumptions (if any)
3. Recommendations

If assumptions are used:
→ they MUST be explicitly stated

---

### When uncertain

DO:
- ask questions
- request context
- pause implementation

DO NOT:
- continue with guessed solution
- fabricate APIs or behavior

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

## 🤖 AI Collaboration Mode (IMPORTANT)

### Goal
Use AI as a thinking partner, not as a code generator.

---

### Preferred workflow

1. Ask for:
   - plan
   - options
   - tradeoffs

2. Implement yourself when possible

3. Use AI for:
   - reviewing code
   - suggesting improvements
   - debugging

---

### Avoid

- generating full features blindly
- copy-pasting large code blocks without understanding
- skipping reasoning

---

### Good prompts

- "What is the simplest way to implement this?"
- "What are 2–3 options and tradeoffs?"
- "Review this code"

---

### Bad prompts

- "Build the entire system"
- "Write everything for me"

---

## 🎓 Learning Awareness

This project is also used to develop:

- backend thinking
- data modeling
- AI-assisted development

Prefer:
- simple data flows (CPT → form → save → admin)

Avoid:
- spending excessive time on visual polish

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

## 🧭 If starting a new task

1. Identify scope (feature / bug / task)
2. Check existing issues
3. Propose minimal implementation
4. Implement one step only
5. Test in dev

---

## 📝 Documentation Updates

When implementing any feature or fix:
- Update `docs/` if the change affects WooCommerce features or user-facing workflows
- Update `CLAUDE.md` if the change affects theme architecture, file structure, CPTs, workflows, or other agent-relevant project knowledge
- Update `AGENTS.md` only when project-level working rules, priorities, or AI collaboration guidance change
- Update `CHANGELOG.md` for every merged change

---

## 📌 Final Principle

> "Make it work → make it simple → then improve."

This is a real system for real users — not a perfect technical showcase.
