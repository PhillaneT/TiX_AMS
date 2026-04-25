# AjanaNova — South African Skills Development Compliance Research

**Date compiled:** April 2026  
**Last updated:** April 2026 (Session 5 — scope refined)  
**Sources:** QCTO, SAQA, MICT SETA, RSM South Africa, LabourNet, iLearn, TrainYouCan, DocuSign/ECT Act, POPIA.co.za

---

## App Definition (Refined — Session 5)

> **AjanaNova Grader** is an AI-assisted assessment and moderation tool for South African SDPs. It helps registered assessors mark learner submissions faster and more consistently, supports the internal moderation workflow, compiles compliant POEs, and generates SORs ready for QCTO submission — without replacing any government system.

### What This App Is NOT
- Not a learner enrolment system (QCTO handles that)
- Not a SETA registration portal (SDPs already do that manually)
- Not a replacement for EISA or the QCTO certification system
- Not a WSP/ATR submission tool

### What This App IS — The Assessor and Moderator Workflow
The real daily pain for an SDP is not submitting to QCTO (a 10-minute admin task at the end). It is the **weeks of labour per cohort** that happens before that:

```
Assessor marks submission (AI-assisted) → Assessor signs off
→ Moderator reviews → Moderator signs off
→ POE compiled automatically per learner
→ SOR generated, ready for QCTO submission
```

This covers approximately 80% of the labour in an SDP's compliance workflow. The government submission side stays manual — SDPs already know how to do it.

### Is AI Marking Legally Valid Under VACS?
Yes — and it is arguably more defensible than pure human marking:

| VACS Principle | How AI-assisted marking addresses it |
|---|---|
| **Valid** | AI marks explicitly against the provided rubric/memo. Every verdict maps to a criterion. More consistent than a tired human marker. |
| **Authentic** | AI can flag copied, generic, or inconsistent answers — giving the assessor better information than working alone. Final authenticity call remains the assessor's. |
| **Current** | Unaffected by marking method — currency relates to when evidence was created, not how it was evaluated. |
| **Sufficient** | AI flags when answers don't address all required criteria — something a human may miss on the 20th workbook of the day. |

**Legal anchor:** SAQA requires assessments to be conducted by a *registered assessor*. It does not prohibit the use of tools. The assessor's signature is what makes the assessment valid — and that signature is non-negotiable in this app. The moderator's review of AI-flagged decisions creates a richer audit trail than a handwritten mark sheet.

There is no SAQA/QCTO guidance explicitly permitting *or* prohibiting AI-assisted marking (regulations predate LLMs). This is an advantage — AjanaNova is a first-mover with a legally sound design.

---

## Background Knowledge — Regulatory Framework

---

## 1. Regulatory Bodies and Their Roles

### SAQA — South African Qualifications Authority
- Custodian and overseer of the entire National Qualifications Framework (NQF)
- Registers qualifications and unit standards on the NQF via the National Learners' Records Database (NLRD)
- Accredits Quality Councils (QCTO, CHE, Umalusi) to perform quality assurance
- Verifies qualifications for individuals and employers
- Oversees Recognition of Prior Learning (RPL) policy
- **Does NOT accredit training providers directly** — delegates to Quality Councils

### QCTO — Quality Council for Trades and Occupations
- Manages the Occupational Qualifications Sub-Framework (OQSF)
- Develops, registers, and quality-assures occupational qualifications and skills programmes
- **Accredits SDPs and Assessment Centres (ACs)** — primary accreditation body post-June 2024
- Manages the External Integrated Summative Assessment (EISA) process
- Issues occupational qualification certificates to learners
- Delegates quality assurance to Assessment Quality Partners (AQPs) — often SETAs

### SETAs — Sector Education and Training Authorities
- 21 SETAs covering different economic sectors
- Collect and disburse Skills Development Levy (SDL)
- Register learnership agreements (still required even post-June 2024)
- Approve workplaces for experiential learning
- Submit/receive WSPs and ATRs from employers
- **Still quality-assure legacy programmes until 30 June 2027**
- Act as Development Quality Partners (DQPs) and Assessment Quality Partners (AQPs) for QCTO

### Regulatory Hierarchy
```
SAQA
  └── Oversees NQF; accredits Quality Councils
        └── QCTO (OQSF — occupational/vocational)
              ├── Accredits SDPs and ACs
              ├── Delegates QA to AQPs (often SETAs)
              └── SETAs (sector bodies)
                    ├── Fund learnerships and grants (SDL)
                    ├── Register learnership agreements
                    └── Approve workplaces
```

### MICT SETA (ICT sector)
- Covers: Advertising, Electronic Media & Film, Electronics, IT, Telecommunications
- Primary SETA for IT/software development learnerships and qualifications
- Retains ETQA accreditation for all legacy ICT qualifications until June 2027
- As of 2025: 57 qualifications developed; 10 awaiting QCTO approval
- Key qualifications: software development, network engineering, cybersecurity, digital marketing, systems analysis (NQF Levels 4–6)

---

## 2. The Critical Transition: SETA → QCTO (Post June 2024)

This is the most significant recent regulatory change affecting all SDPs.

| Milestone | Date |
|---|---|
| Legacy qualifications deregistered from NQF | 30 June 2023 |
| **Last date to enrol new learners on legacy/SETA qualifications** | **30 June 2024** |
| **QCTO becomes the sole accreditation authority** | **1 July 2024** |
| Last date to complete legacy qualifications | 30 June 2027 |

### Key Implications for the Platform
- The platform must handle **two tracks simultaneously**:
  - **Legacy track**: SETA/unit-standard-based programmes enrolled before 30 June 2024 — active until June 2027
  - **QCTO track**: New occupational qualifications only — all new enrolments from July 2024
- Legacy content cannot be directly transferred to QCTO format — must be redesigned into 3-module structure
- B-BBEE treatment differs: QCTO full qualifications = Categories B/C/D (full credit); legacy = Category F (25% of course fees only)

---

## 3. NQF Structure

### NQF Levels 1–10

| Level | Equivalent | Sub-framework |
|---|---|---|
| 1 | Grade 9 | GFETQSF |
| 2 | Grade 10 | GFETQSF |
| 3 | Grade 11 | GFETQSF |
| 4 | Grade 12 / Matric | GFETQSF |
| 5 | Higher Certificate | HEQSF or OQSF |
| 6 | Diploma / Occupational Certificate | HEQSF or OQSF |
| 7 | Bachelor's Degree / Advanced Diploma | HEQSF or OQSF |
| 8 | Honours / Postgraduate Diploma | HEQSF or OQSF |
| 9 | Master's Degree | HEQSF |
| 10 | Doctoral Degree | HEQSF |

1 NQF credit = 10 hours of learning.

### Legacy vs. QCTO Qualifications

| Feature | Legacy (SETA/Unit Standard) | QCTO Occupational |
|---|---|---|
| Structure | Multiple unit standards | 3 modules: Knowledge, Practical Skills, Work Experience |
| Credit allocation | Per unit standard | Per whole qualification |
| Workplace requirement | Optional | **Mandatory** |
| Assessment | Internal formative + summative per unit standard | Internal per module + compulsory external **EISA** |
| B-BBEE status | Category F (25%) | Category B/C/D (full credit) |

### Learnerships vs. Skills Programmes

**Learnerships**
- 12–24 months; leads to a full NQF qualification
- Requires three-party Learnership Agreement (learner + employer + SDP)
- Must be registered with the SETA within 30 days of commencement
- Tax incentive under Section 12H of the Income Tax Act
- EISA required at end (for QCTO qualifications)

**Skills Programmes**
- Shorter, targeted; leads to part qualification or unit standards
- Can be stacked toward full qualifications
- Final Integrated Supervised Assessment (FISA) conducted by SDP (no EISA)
- Results submitted to QCTO within 21 working days of FISA

---

## 4. Assessment and Moderation Framework

### 4.1 Formative vs. Summative Assessment

| | Formative | Summative |
|---|---|---|
| **Purpose** | Monitor progress, provide feedback | Make final competence judgement |
| **When** | Throughout the programme | End of module / programme |
| **Moderation required** | Minimum 25% (or min 3) internally moderated | **100% internally moderated before SOR** |
| **Feeds into** | Learning improvement | Statement of Results (SOR) |
| **Examples** | Workbook exercises, quizzes, case studies | Final projects, practical demonstrations, written tests |

### 4.2 Assessor Role and Registration

An assessor evaluates learner evidence and makes a formal C/NYC (Competent / Not Yet Competent) decision.

**Registration requirements:**
- Hold unit standard US 115753 — "Conduct outcomes-based assessments" (NQF Level 5, 15 credits) — also known as ASMT01
- Be a subject matter expert: hold qualification at same level or higher as what is being assessed
- Register with the relevant SETA/ETQA (legacy) or AQP (QCTO)
- Two-year renewable registration tied to specific qualifications
- Must be actively practicing in the relevant occupational field

**Registration process:**
1. Complete assessor training course (3–5 days)
2. Build POE demonstrating competence against US 115753
3. Submit to SETA/AQP: application form + certified ID + qualification(s) + CV + POE
4. Receive registration certificate with registration number

### 4.3 Moderator Role and Registration

A moderator verifies the assessment **process** itself — not just the learner's work, but whether the assessor applied VACS principles correctly.

**Registration requirements:**
- Hold US 115759 — "Moderate outcomes-based assessments" (NQF Level 6, 10 credits) — also known as ASSMT02
- Must also hold the assessor unit standard (US 115753 / ASMT01)
- Subject matter expert at or above the level of the qualification being moderated
- Two-year renewable registration
- For MICT SETA: moderators must be certified by ETDP SETA or relevant ETQA

### 4.4 Internal vs. External Moderation

**Internal Moderation** (SDP's own moderator)
- Must cover: minimum 25% of formative assessments (or min 3)
- Must cover: **100% of summative assessments** before SOR can be issued
- Moderator produces a Moderation Report
- Any irregularities must be resolved before SOR sign-off

**External Moderation** (SETA/QCTO appointed, independent)
- Samples the SDP's internal moderation process and POEs
- Conducted during SETA monitoring visits or QCTO QA monitoring visits
- At least one QCTO monitoring visit required before EISA registration is permitted

---

## 5. Portfolio of Evidence (POE)

The POE is the primary documentary record of a learner's assessed competence throughout the programme.

### 5.1 Mandatory POE Contents

1. Learner personal details and certified copy of ID
2. Signed learnership / training agreement
3. Programme / qualification details (NQF level, SAQA ID, credit value)
4. Learning outcomes and assessment criteria for each unit standard or module
5. Formative assessment tasks, assignments, and workbook exercises with assessor feedback
6. Summative assessment evidence (final projects, practical demonstrations, written tests)
7. Workplace evidence: logbooks, observation records, supervisor/mentor sign-off sheets, photographs, work samples
8. Learner reflective statements linking evidence to competencies
9. Assessor judgement records (C / NYC decisions per unit standard or module)
10. Internal moderation reports (moderator sign-off)
11. Appeals and feedback records (if applicable)
12. RPL evidence (if Recognition of Prior Learning was applied)

### 5.2 VACS Principles — Evidence Quality Standards

All evidence in the POE must satisfy four criteria:

| Principle | Requirement |
|---|---|
| **Valid** | Directly relates to the specific outcomes being assessed; allows an accurate competence judgement |
| **Authentic** | The work belongs to the candidate and reflects their own independent achievement |
| **Current** | Evidence generally from within the past two years; reflects present-day competence |
| **Sufficient** | Volume and range of evidence is adequate to prove competence "beyond reasonable doubt" across all outcomes |

### 5.3 Evidence Types Accepted

- **Direct**: observed performance, products created, workbooks completed
- **Indirect**: testimonials, supervisor confirmation, third-party witness statements
- **Historical**: past work samples verified for authenticity + supplemented by current assessment

---

## 6. Statement of Results (SOR)

The SOR is the formal document issued by an accredited SDP confirming a learner has been found competent against all internal assessment criteria.

**Key facts:**
- The SOR is the **gateway document** — a learner cannot register for EISA without it
- Must reflect competence across all three modules (Knowledge, Practical Skills, Work Experience)
- Subject to QCTO QA sampling after submission
- For legacy qualifications: issued by the SETA/ETQA
- **SDPs cannot issue QCTO occupational qualification certificates** — those are issued exclusively by the QCTO via the Certificate Verification System (CVS)
- For skills programmes: SOR is the final document (no EISA); submit to QCTO within 21 working days of FISA

---

## 7. Full SDP Compliance Workflow — 28 Steps

### Phase 1: Pre-Enrolment
| Step | Action | Who | Documents |
|---|---|---|---|
| 1 | Obtain QCTO SDP accreditation for specific qualification(s) | SDP | QCTO application + site visit; Q-number (valid 5 years) |
| 2 | Obtain workplace approval (for learnerships) | Employer/SDP | SETA workplace approval letter |
| 3 | Appoint registered assessors and moderators | SDP | Registration certificates from SETA or AQP |
| 4 | Develop QCTO-compliant assessment materials | SDP | Assessment guides aligned to Qualification Assessment Specifications (QAS) |

### Phase 2: Enrolment
| Step | Action | Who | Documents | Timeline |
|---|---|---|---|---|
| 5 | Sign Learnership Agreement | Learner + Employer + SDP | Three-party agreement per Skills Development Act | Before training begins |
| 6 | Register agreement with SETA | SDP | SETA registration form + certified agreement | **Within 30 days** |
| 7 | Collect learner documentation | SDP | Certified ID, educational certificates, signed programme agreement | At enrolment |
| 8 | Submit QCTO learner enrolment | SDP | QCTO Learner Enrolment Template → learnerenrolments@qcto.org.za | **Within 21 working days** |
| 9 | Set up learner POE folder | Learner + SDP | POE structure per unit standard or module | Day 1 |

### Phase 3: Training and Assessment
| Step | Action | Who | Documents |
|---|---|---|---|
| 10 | Deliver Knowledge Module training | Facilitator | Attendance registers; session plans; learning materials |
| 11 | Formative assessments — Knowledge Module | Registered Assessor | Assessment instruments; feedback sheets; C/NYC decisions |
| 12 | Deliver Practical Skills Module training | Facilitator | Attendance registers; practical assessment guides |
| 13 | Formative assessments — Practical Skills Module | Registered Assessor | Observation checklists; practical task assessments; sign-off |
| 14 | Facilitate Work Experience Module | Employer/Workplace Mentor | Workplace logbook; mentor observation records; supervisor sign-offs |
| 15 | Internal moderation — formative assessments | Registered Moderator (internal) | Moderation report; minimum 25% coverage; irregularity log |
| 16 | Summative assessments — all modules | Registered Assessor | Summative instruments; assessor competence judgement records |
| 17 | Internal moderation — **100% of summative assessments** | Registered Moderator (internal) | Moderation report; all summative confirmed; signed |
| 18 | Compile and finalise learner POEs | Learner + Assessor | Completed POE per VACS; assessor sign-off per unit/module |

### Phase 4: QCTO Quality Assurance
| Step | Action | Who |
|---|---|---|
| 19 | QCTO QA monitoring visit (required before EISA) | QCTO QA team samples POEs and assessment records |

### Phase 5: SOR and EISA Registration
| Step | Action | Who | Documents | Timeline |
|---|---|---|---|---|
| 20 | Issue Statement of Results (SOR) | SDP | QCTO SOR Template; signed by assessor + SDP | After all summative moderated |
| 21 | Submit updated QCTO enrolment with EISA readiness | SDP | Updated enrolment → EISAreadiness@qcto.org.za | **At least 3 months before EISA** |
| 22 | QCTO QA samples SORs | QCTO | SORs forwarded to Quality Partner | After submission |

### Phase 6: EISA (Occupational Qualifications Only)
| Step | Action | Who | Timeline |
|---|---|---|---|
| 23 | Learner completes EISA at accredited Assessment Centre | Learner + AC + Quality Partner | Per QCTO EISA schedule |
| 24 | Quality Partner marks, moderates, and QA | Quality Partner | 21 working days post-EISA |
| 25 | QP submits final results to QCTO | Quality Partner | Within 21 working days |
| 26 | QCTO approves results | QCTO | Within 21 working days |
| 27 | QCTO issues occupational certificates (via CVS) | QCTO | Within 21 working days |
| 28 | SDP/QP distributes certificates to learners | SDP | Signed receipt retained on file |

**Total post-EISA certification timeline: up to 63 working days (~3 months)**

### Skills Programmes (No EISA)
Steps 21–28 replaced by:
- SDP conducts Final Integrated Supervised Assessment (FISA)
- Submit results to QCTO within 21 working days of FISA
- QCTO samples learner evidence for QA
- QCTO issues skills programme certificates

---

## 8. Digital and Paperless Compliance

### 8.1 Electronic Signatures — Legal Status (ECTA)

The **Electronic Communications and Transactions Act No. 25 of 2002 (ECTA)** governs digital signatures in South Africa.

**Standard Electronic Signature (ES)**
- Legally valid and enforceable for the majority of commercial and training documents
- Includes typed names, scanned signatures, click-to-accept, stylus signatures
- **Sufficient for all skills development documents**: learnership agreements, POE sign-offs, SORs, attendance registers, moderation reports

**Advanced Electronic Signature (AES)**
- Required only for: suretyship, copyright assignments, notarial documents — **NOT required for any skills development documents**
- Skills development documents are **not excluded** from standard electronic signature validity

**Platform implication:** Digital sign-off in the Moodle plugin and web portal is legally valid. No biometric or AES required.

### 8.2 Digital POE Practical Requirements

- Audit trail required: tamper-evident PDFs, version control, audit logs, date-stamped uploads
- Authenticity must be demonstrable (VACS): learner-specific watermarking, device/IP logging
- Platform must export a complete self-contained POE package (PDF bundle) on demand for QCTO QA visits
- Moderator sign-off must be traceable to their registered identity

### 8.3 Data Retention Requirements

| Record | Applicable Law | Minimum Retention |
|---|---|---|
| Employment records / learnership agreements | BCEA | 3 years post-termination |
| Tax / SDL records | Tax Administration Act | 5 years |
| Training / assessment records | Skills Development Act / SETA | 5 years |
| Personal information (learner data) | POPIA | Not longer than necessary; min 3 years |
| QCTO certification records | QCTO CVS | Indefinite (QCTO holds authoritative record) |

**Platform standard: retain all records for minimum 5 years post-programme completion.**

### 8.4 POPIA Obligations

- Learner personal data (ID numbers, qualifications, employment) constitutes personal information under POPIA
- Platform must appoint an Information Officer registered with the Information Regulator
- Learners have the right to access, correct, and request deletion of their data
- Mandatory data breach notification to Information Regulator and affected learners
- If platform processes data on behalf of SDPs: a written **Operator Agreement** under POPIA is required
- Data stored outside South Africa: cross-border transfer restrictions apply

---

## 9. Feature Scope — What AjanaNova Needs to Build

### In Scope (Assessor and Moderator Workflow)

| Feature | Moodle Plugin | Web Portal | Priority |
|---|---|---|---|
| AI-assisted marking (formative + summative) | ✅ Built | Planned | — |
| Assessor review and sign-off per verdict | ✅ Built | Planned | — |
| Assessor override of any AI decision | ✅ Built | Planned | — |
| Registered assessor identity stored per sign-off | ✅ Partial (name only) | Planned | Medium |
| Registered moderator sign-off workflow | ❌ | ❌ | **High** |
| Internal moderation — 25% formative coverage tracking | ❌ | ❌ | **High** |
| Internal moderation — 100% summative gate before SOR | ❌ | ❌ | **High** |
| VACS sufficiency check (flag incomplete criteria coverage) | ❌ | ❌ | **High** |
| POE builder (auto-compiled per learner, structured) | Partial (existing plugin) | ❌ | **High (web portal)** |
| SOR generation (QCTO-format, ready to submit) | ❌ | ❌ | **High** |
| PDF annotation — tick/cross glyphs only (SAQA style) | ❌ Pending | ❌ | **Immediate** |
| Vision API upgrade (send PDF pages as images to Claude) | ❌ Pending | ❌ | **High** |
| Assessor/moderator registration certificate storage + expiry alert | ❌ | ❌ | Medium |
| Digital signature audit trail (tamper-evident, ECTA-compliant) | Partial | ❌ | Medium |
| 5-year data retention + POPIA consent | ❌ | ❌ | Infrastructure |
| POE export as complete PDF bundle | Partial (existing plugin) | ❌ | High (web portal) |

### Out of Scope (Government Systems — SDPs Use These Directly)

| Feature | Reason Out of Scope |
|---|---|
| QCTO learner enrolment submission (21-day trigger) | Government system — SDPs submit to QCTO directly |
| SETA learnership registration | SETA portal — not replacing this |
| EISA registration and readiness submission | QCTO process — not replacing this |
| WSP/ATR submission | Employer/SDF function — not AjanaNova's domain |
| NLRD / SAQA certificate verification | SAQA system — not replacing this |
| QCTO certificate issuance | QCTO-only — SDPs cannot issue these |

---

## 10. Key Terminology Reference

| Term | Meaning |
|---|---|
| SDP | Skills Development Provider — accredited training provider |
| ETQA | Education and Training Quality Assurance body — SETAs acting as QA bodies |
| AQP | Assessment Quality Partner — body delegated by QCTO to oversee EISA |
| DQP | Development Quality Partner — body delegated to develop qualifications |
| EISA | External Integrated Summative Assessment — compulsory external exam for QCTO occupational qualifications |
| FISA | Final Integrated Supervised Assessment — internal equivalent for skills programmes |
| SOR | Statement of Results — issued by SDP; gateway to EISA |
| POE | Portfolio of Evidence — complete learner evidence file |
| VACS | Valid, Authentic, Current, Sufficient — four evidence quality principles |
| RPL | Recognition of Prior Learning — crediting prior experience and knowledge |
| NQF | National Qualifications Framework — 10-level SA framework for all qualifications |
| OQSF | Occupational Qualifications Sub-Framework — the QCTO's section of the NQF |
| NLRD | National Learners' Records Database — SAQA's central qualifications database |
| CVS | Certificate Verification System — QCTO's certificate issuance and verification platform |
| WSP | Workplace Skills Plan — annual skills planning submission by employers to SETAs |
| ATR | Annual Training Report — annual training report submitted to SETAs |
| SDL | Skills Development Levy — 1% payroll levy collected by SARS; distributed via SETAs |
| C / NYC | Competent / Not Yet Competent — the two valid assessment verdicts |
| US | Unit Standard — legacy NQF learning outcome statement (being phased out) |
| Q-number | QCTO accreditation number assigned to an SDP per qualification |

---

*Last updated: 2026-04-03*  
*Source: QCTO, SAQA, RSM SA, LabourNet, iLearn, TrainYouCan, MICT SETA, DocuSign/ECT Act, POPIA.co.za*
