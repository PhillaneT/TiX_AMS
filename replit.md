# AjanaNova — Moodle Plugin + Assessor Management System (AMS)

This repo holds two related products:

1. **AjanaNova Grader** (`local_ajananova`) — the existing Moodle local plugin. Untouched.
2. **AjanaNova AMS** — a new standalone web application (being built here) that gives assessors who *don't* use Moodle the same AI-marking power, plus a per-cohort gradebook, learner management, and assessor-controlled email delivery of locked annotated PDFs.

The AMS reuses the plugin's PHP AI/PDF code (prompt builder, Anthropic client, PDF extractor, PDF annotator) by porting it out of Moodle dependencies into framework-agnostic classes.

---

## AMS — Web Application

### Stack
- PHP 8.2 + Laravel (chosen per `attached_assets/AJANANOVA_HANDOVER_*.md` §5).
- PostgreSQL (Replit built-in).
- Existing Composer libs reused: `smalot/pdfparser`, `setasign/fpdi`, `tecnickcom/tcpdf`.
- Server: Laravel `artisan serve` on `0.0.0.0:5000`.

### v1 scope (in build now)
1. Assessor login (single tenant for now).
2. **Qualification → Cohort → Learner** hierarchy with always-visible "currently assessing" context badge.
3. **CSV learner import** — assessor downloads a template, fills it, uploads once per cohort. Validates and stores.
4. Assignment + memo per qualification (text paste / criteria list / PDF upload — same three-tier pattern as the Moodle plugin).
5. Single OR bulk submission upload.
6. **AI-assisted file→learner matching** on bulk upload — model reads the first page of each PDF and proposes the matching learner from the cohort roster; assessor confirms.
7. Grade now OR queue for later (in-process queue worker).
8. AI marking (mock mode default; real Anthropic when key is set — same key as Moodle plugin).
9. Review & edit screen — editable per-question feedback, constructive-only enforcement (warn on negative phrasing patterns).
10. Sign-off → produces:
    - **Locked annotated PDF** (red ticks/crosses on the submission — owner password + permissions flags blocking edit/copy/annotate).
    - **Cover-page feedback letter** (clean per-question summary the learner reads first).
    - SHA-256 hash of the final PDF stored for tamper evidence.
11. Email PDF + cover letter to learner from inside the AMS; copy stored against the submission.
12. Cohort gradebook view (learners × assignments × scores), CSV/PDF export.
13. Audit log of every action (who, what, when, IP).
14. Compliance baselines from day one even if features come later:
    - 5-year retention (soft-delete only).
    - `track` field on every qualification (`legacy_seta` | `qcto_occupational`).
    - Personal-info fields kept separate (encrypted-at-rest where applicable) for future POPIA compliance.

### v2 backlog (deferred, not lost)
- **Interactive PDF annotation editor.** After AI grading, assessor opens a per-question view showing the learner's text with the AI's red ticks/crosses inline. They can:
  - Edit or delete any AI-placed tick/cross.
  - Add new ticks/crosses from a stamp/edit toolbar (drag-to-place).
  - See an "annotations per question" summary view per assignment.
- Moderator workflow + sign-off (US 115759 / ASSMT02).
- 25% formative / 100% summative moderation gating before SOR.
- VACS sufficiency checker (flag incomplete criteria coverage).
- POE bundle generation in the web app (already in Moodle plugin).
- SOR generation (QCTO format).
- Cohort analytics dashboard (pass rates, weak criteria, time-to-mark).
- Multi-assessor admin + role permissions.
- Multi-tenant client onboarding + PayFast billing + invoices (per handover doc §5–7).
- POPIA Operator Agreement workflow + Information Officer registration.
- Assessor/moderator registration certificate storage + expiry alerts.

### Session 4 additions (2026-04-25) — SAQA Module Mapping + Learner POE Profile

**New tables:**
- `qualification_modules` — KM/PM/WM/US/MOD modules per qualification (fetched from SAQA or added manually). Fields: qualification_id, module_type, module_code, title, nqf_level, credits, sortorder.
- `assignment_modules` — pivot: which assignment(s) assess each qualification module.
- `qualifications.saqa_raw_data` (JSON) + `saqa_fetched_at` — stores full SAQA API response for audit.

**New service:** `app/Services/SaqaFetcher.php` — standalone port of the Moodle plugin's `saqa_fetcher` class. Scrapes `regqs.saqa.org.za`. Handles KM/PM/WM (QCTO), Unit Standards (legacy SETA), and generic prose modules (HEQSF).

**New controller:** `QualificationModuleController` — SAQA fetch → store modules, module-to-assignment mapping (checkbox UI), manual add, delete module.

**New routes:**
- `GET /qualifications/{qual}/modules` — module management page
- `POST /qualifications/{qual}/modules/fetch-saqa` — fetch from SAQA and replace modules
- `POST /qualifications/{qual}/modules/save-mapping` — save assignment mappings
- `POST /qualifications/{qual}/modules/add` — manual module
- `DELETE /qualifications/{qual}/modules/{module}` — remove module
- `GET /qualifications/{qual}/cohorts/{cohort}/learners/{learner}/poe` — learner POE profile

**Learner POE profile** (`learners/poe.blade.php`):
- Shows all qualification modules in order
- Per module: which assignments assess it, the learner's submission status, and C/NYC verdict
- Module-level result: C (all assignments C), NYC (any assignment NYC), or Pending
- Summary counts + printable assessor declaration block
- Print → PDF button (browser print)

**Decision:** SAQA fetch is done at qualification level (not per cohort). All cohorts under the same qualification share the same module list. Assignment-to-module mapping is also per qualification.

### Decisions logged (Session 3, 2026-04-25)
| Decision | Answer |
|---|---|
| Learner authentication | None. Assessor downloads PDF, AMS emails it. |
| Where scores live | Per cohort/class inside AMS (gradebook view, future analytics). |
| PDF format to learner | Locked PDF (no edit/copy/annotate) + cover-page feedback letter. |
| Anthropic API key | Reuse the one already working in the Moodle plugin. |
| SMTP provider | None yet. Use Laravel `log` mail driver in dev; pick a provider before pilot. |
| SA ID numbers | Not stored in v1 web app. (Stored on Moodle side already.) |
| Bulk upload matching | CSV-imported roster + AI-assisted file→learner matching with assessor confirm. |
| Stack | Laravel + PostgreSQL (PHP, reuses Moodle plugin code). |

### Project layout (AMS — created during build)
- `app/`, `bootstrap/`, `config/`, `database/`, `resources/`, `routes/`, `public/` — standard Laravel.
- `app/Services/Ai/` — ported `PromptBuilder`, `AnthropicClient`, `MockClient` from the Moodle plugin.
- `app/Services/Pdf/` — ported `Extractor`, `Annotator` (with new locking step).

---

## Moodle Plugin (unchanged — reference only)

`local_ajananova` is a Moodle 4.1+ local plugin that adds AI-assisted marking inside an existing Moodle install at `<moodle>/local/ajananova/`. Its files (`version.php`, `lib.php`, `settings.php`, `mark.php`, `memo.php`, `db/install.xml`, `classes/ai|billing|grading|output|pdf/`, `lang/`, `templates/`) all bootstrap Moodle and cannot run standalone. The Replit landing page (`index.php`, gated on `!defined('MOODLE_INTERNAL')`) renders plugin metadata for browsing.

### Composer dependencies (shared with AMS)
- `smalot/pdfparser` ^2.0 — extract text from learner submission PDFs.
- `setasign/fpdi` ^2.3 — import existing PDFs as templates.
- `tecnickcom/tcpdf` ^6.6 — write annotated PDFs.

### Installing the plugin into Moodle
1. Copy `local/ajananova/` contents to `<moodle>/local/ajananova/`.
2. Run `composer install` inside that directory.
3. Visit `Site administration → Notifications` to install tables.
4. Configure under `Site administration → Plugins → Local plugins → AjanaNova Grader`. Mock mode is on by default.
5. Open any assignment as an assessor; the gear menu shows *AjanaNova: Upload marking guide* and *AjanaNova: Mark with AI*.

---

## User preferences
- Build for speed over polish in v1 — get something the user can pilot ASAP.
- Defer compliance features that aren't blocking pilot use, but don't lose them.
- Plain-language updates — minimal jargon.
