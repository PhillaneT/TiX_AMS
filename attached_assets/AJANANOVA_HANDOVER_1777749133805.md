# AjanaNova Grader — Claude Code Handover Document
**Version:** 1.0  
**Date:** March 2026  
**Author:** AjanaNova Founder  
**Purpose:** Full technical handover for Claude Code (VS Code) to begin building the AjanaNova AI Marking plugin and web portal

---

## 1. Project Overview

**AjanaNova Grader** is a South African SaaS platform purpose-built for MICT SETA-accredited Skills Development Providers (SDPs). It is the first LMS platform designed specifically to make the full SETA compliance workflow — from facilitation through assessment, moderation, POE generation and SOR certification — entirely paperless.

### The Problem Being Solved
South African SDPs currently manage learner Portfolios of Evidence (POEs) using physical files, printed documents, email chains and manual signatures. The process from facilitation to Statement of Results (SOR) is paper-heavy, error-prone and not scalable. No existing LMS addresses the full MICT SETA workflow end-to-end.

### What Has Already Been Built (Moodle Plugin)
The following features are already functional in the existing Moodle plugin:
- Facilitation workflow (course delivery, attendance registers)
- Learner assessment workflow (formative and summative)
- Internal moderation workflow
- Paperless POE generation (per learner and per group)
- Digital sign-off by assessor and moderator
- POE print/export per learner

### What This Document Covers (To Be Built)
1. **AI Marking feature** — Anthropic API integration for automated assignment marking
2. **Mock/test mode** — Full sandbox to test AI marking without consuming real API tokens
3. **AjanaNova Central Platform** — Multi-tenant billing, usage tracking, licence management
4. **Web Portal** — Browser-based AI marking for SDPs without Moodle
5. **Unified billing system** — Per-client invoicing for hosted and non-hosted clients
6. **Client self-service dashboard** — Signup, licence keys, usage, top-ups

---

## 2. Business Model

### Revenue Streams
| Stream | Description |
|--------|-------------|
| Moodle hosting fee | AjanaNova manages the client's Moodle on AWS |
| AjanaNova admin fee | Monthly platform management fee |
| POE generation credits | Charged per POE generated |
| AI marking credits | Charged per AI marking event triggered |
| Plugin licence | For clients with their own Moodle (not hosted by AjanaNova) |
| Web portal access | For SDPs with no Moodle at all |
| Top-up bundles | Credits purchased on demand |

### Pricing Tiers
| Tier | Monthly | AI Marks Included | Target |
|------|---------|-------------------|--------|
| Hosted Starter | R499 | 50 | Small SDP |
| Hosted Growth | R1,299 | 200 | Medium SDP |
| Hosted Pro | R2,999 | 500 | Large SDP |
| Plugin Licence (own Moodle) | R299 | 25 | Self-hosted |
| Web Portal Only | R199 | 10 | No Moodle |
| Top-up bundle | R99 | 25 | Any tier |

### Key Billing Rules
- AI marking is triggered **manually by the assessor** (not auto on submission)
- Manual triggering = discrete billable event = clean audit trail
- When AI credits are exhausted, manual grading remains fully available (never blocked)
- Every assignment with a rubric/marking guide **promotes** AI marking as primary action
- Clients NOT on AjanaNova AWS can still use AI marking via plugin or web portal
- All usage across all sources rolls into one monthly invoice per client

---

## 3. Architecture Overview

### Multi-Tenant Model
Each client has their own isolated Moodle instance. AjanaNova plugin is installed on all instances. Every billable event phones home to AjanaNova Central Platform.

```
AjanaNova Central Platform (ajananova.co.za)
├── Billing API
├── Usage tracker (per client, per event)
├── Licence validation endpoint
├── Admin dashboard (founder view)
├── Client self-service portal
└── Anthropic API (single account, managed centrally)

Client instances (any of the below):
├── Client A — AjanaNova-hosted Moodle on AWS (EC2 + RDS)
├── Client B — AjanaNova-hosted Moodle on AWS
├── Client C — Self-hosted Moodle, AjanaNova plugin installed
└── Client D — No Moodle, uses AjanaNova web portal only
```

### Technology Stack
| Layer | Technology |
|-------|-----------|
| LMS | Moodle (PHP 8.x) |
| Plugin language | PHP 8.x |
| Central platform | Laravel (PHP) |
| Database | MySQL 8 (per client) + PostgreSQL (central) |
| File storage | AWS S3 |
| Hosting | AWS EC2 + RDS |
| PDF processing | smalot/pdfparser, FPDI, PDF-lib |
| OCR (fallback) | Tesseract |
| AI | Anthropic Claude API (claude-sonnet-4-20250514) |
| Web portal frontend | Laravel Blade + Alpine.js or Vue.js |
| Email/invoicing | Laravel Mail + custom PDF invoices |

---

## 4. AI Marking Feature — Full Specification

### 4.1 Overview
When an assessor opens a learner's submitted assignment:
1. If the assignment has an attached marking guide/rubric AND the client has AI credits: show **"Mark with AI"** as the primary button
2. Assessor clicks button → billable event logged → Anthropic API called
3. AI reads memo + submission → returns structured JSON verdict per question
4. PDF is annotated with ticks, crosses and comments
5. Assessor reviews AI output, can override any decision, then signs off
6. Signed result flows automatically into the learner's POE

### 4.2 Mock / Test Mode (Build This First)
**Critical:** All AI marking features must work in mock mode before touching the real Anthropic API. This allows full end-to-end testing at zero cost.

#### Mock Mode Behaviour
- Controlled via Moodle plugin setting: `local_ajananova | mock_mode = 1`
- When mock mode is ON:
  - No API call is made
  - A fake JSON response is returned instantly (see mock response below)
  - No credits are consumed
  - No billing events are logged
  - A visible banner shows: `⚠️ MOCK MODE — AI responses are simulated`
- When mock mode is OFF:
  - Real Anthropic API is called
  - Credits are consumed
  - Billing event is logged

#### Mock Response Structure
```php
// lib/mock_response.php
function ajananova_get_mock_marking_response(): array {
    return [
        'overall_recommendation' => 'NOT_YET_COMPETENT',
        'confidence'             => 'HIGH',
        'mock'                   => true,
        'questions' => [
            [
                'question_number'       => '1',
                'question_ref'          => 'Define the concept of skills development',
                'learner_answer_summary'=> 'Learner provided a partial definition...',
                'verdict'               => 'COMPETENT',
                'marks_awarded'         => 8,
                'marks_available'       => 10,
                'ai_comment'            => 'Good understanding shown. Missing reference to NQF alignment.',
                'assessor_flag'         => false,
                'flag_reason'           => null,
            ],
            [
                'question_number'       => '2',
                'question_ref'          => 'Explain the role of a SETA',
                'learner_answer_summary'=> 'Learner did not address quality assurance function...',
                'verdict'               => 'NOT_YET_COMPETENT',
                'marks_awarded'         => 3,
                'marks_available'       => 10,
                'ai_comment'            => 'Answer incomplete. SETA quality assurance role not addressed.',
                'assessor_flag'         => false,
                'flag_reason'           => null,
            ],
            [
                'question_number'       => '3',
                'question_ref'          => 'Describe the POE compilation process',
                'learner_answer_summary'=> 'Response is ambiguous — could be interpreted multiple ways',
                'verdict'               => 'FLAGGED',
                'marks_awarded'         => 0,
                'marks_available'       => 10,
                'ai_comment'            => 'Answer requires assessor interpretation.',
                'assessor_flag'         => true,
                'flag_reason'           => 'Ambiguous response — assessor must make final call',
            ],
        ],
        'moderation_notes'         => 'Mock response: 1 flagged item requires assessor review.',
        'assessor_override_required' => true,
    ];
}
```

### 4.3 Moodle Plugin File Structure
```
local/ajananova/
├── version.php
├── lib.php
├── settings.php                    ← mock_mode toggle, API key, central platform URL
├── db/
│   ├── install.xml                 ← database table definitions
│   ├── events.php                  ← event observers
│   └── tasks.php                   ← scheduled/adhoc tasks
├── classes/
│   ├── event_observer.php          ← listens for assignment submission events
│   ├── task/
│   │   └── ai_mark_submission.php  ← adhoc background task
│   ├── ai/
│   │   ├── marking_engine.php      ← orchestrates the full marking flow
│   │   ├── anthropic_client.php    ← Anthropic API wrapper
│   │   ├── mock_client.php         ← returns mock responses
│   │   ├── prompt_builder.php      ← builds system + user prompts
│   │   └── pdf_extractor.php       ← extracts text from submission PDFs
│   ├── pdf/
│   │   └── annotator.php           ← stamps ticks/crosses/comments onto PDFs
│   ├── billing/
│   │   ├── usage_logger.php        ← logs billable events to DB + central platform
│   │   └── credit_manager.php      ← checks/deducts credits per client
│   └── output/
│       └── marking_review.php      ← assessor review UI renderer
├── amd/
│   └── src/
│       └── marking_ui.js           ← frontend JS for review screen
└── templates/
    ├── marking_review.mustache     ← assessor review screen template
    └── credits_exhausted.mustache  ← shown when AI credits run out
```

### 4.4 Database Tables (install.xml)
```xml
<!-- ajananova_ai_usage — every AI marking event -->
<TABLE NAME="ajananova_ai_usage">
  <FIELDS>
    <FIELD NAME="id"              TYPE="int"    LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="licence_key"     TYPE="char"   LENGTH="64" NOTNULL="true"/>
    <FIELD NAME="sdp_id"          TYPE="int"    LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="assessor_id"     TYPE="int"    LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="learner_id"      TYPE="int"    LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="assignment_id"   TYPE="int"    LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="tokens_input"    TYPE="int"    LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="tokens_output"   TYPE="int"    LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="credits_charged" TYPE="int"    LENGTH="5"  NOTNULL="true" DEFAULT="1"/>
    <FIELD NAME="mock_mode"       TYPE="int"    LENGTH="1"  NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="status"          TYPE="char"   LENGTH="20" NOTNULL="true" DEFAULT="success"/>
    <FIELD NAME="api_response_id" TYPE="char"   LENGTH="100" NOTNULL="false"/>
    <FIELD NAME="timecreated"     TYPE="int"    LENGTH="10" NOTNULL="true"/>
  </FIELDS>
</TABLE>

<!-- ajananova_marking_results — AI output per submission -->
<TABLE NAME="ajananova_marking_results">
  <FIELDS>
    <FIELD NAME="id"                     TYPE="int"  LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="usage_id"               TYPE="int"  LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="submission_id"          TYPE="int"  LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="ai_recommendation"      TYPE="char" LENGTH="30" NOTNULL="true"/>
    <FIELD NAME="ai_confidence"          TYPE="char" LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="questions_json"         TYPE="text" NOTNULL="true"/>
    <FIELD NAME="moderation_notes"       TYPE="text" NOTNULL="false"/>
    <FIELD NAME="assessor_override"      TYPE="int"  LENGTH="1"  NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="final_verdict"          TYPE="char" LENGTH="30" NOTNULL="false"/>
    <FIELD NAME="assessor_id"            TYPE="int"  LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="annotated_pdf_fileid"   TYPE="int"  LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="timecreated"            TYPE="int"  LENGTH="10" NOTNULL="true"/>
    <FIELD NAME="timereviewed"           TYPE="int"  LENGTH="10" NOTNULL="false"/>
  </FIELDS>
</TABLE>

<!-- ajananova_client_credits — credit balance per client -->
<TABLE NAME="ajananova_client_credits">
  <FIELDS>
    <FIELD NAME="id"            TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="licence_key"   TYPE="char" LENGTH="64" NOTNULL="true"/>
    <FIELD NAME="credits_total" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="credits_used"  TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="credits_remaining" TYPE="int" LENGTH="10" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="reset_date"    TYPE="int" LENGTH="10" NOTNULL="false"/>
    <FIELD NAME="timeupdated"   TYPE="int" LENGTH="10" NOTNULL="true"/>
  </FIELDS>
</TABLE>
```

### 4.5 Anthropic API Integration

#### System Prompt
```php
// classes/ai/prompt_builder.php
public function buildSystemPrompt(): string {
    return <<<PROMPT
You are an NQF-aligned assessment assistant for a South African 
SETA-accredited training provider operating under MICT SETA.

Your role is to mark a learner's written assignment against a 
provided marking guide or memo.

STRICT RULES:
1. Evaluate ONLY against the provided memo/marking guide
2. Use COMPETENT or NOT_YET_COMPETENT verdicts only
3. Flag ambiguous answers for assessor review (FLAGGED verdict)
4. Be constructive and specific in feedback
5. Never make the final competency decision — that is the assessor's role
6. Apply NQF principles: valid, sufficient, authentic, current evidence
7. Consider partial credit where the memo allows for it

RESPONSE FORMAT:
Respond ONLY in valid JSON. No text outside the JSON object.

{
  "overall_recommendation": "COMPETENT|NOT_YET_COMPETENT|ASSESSOR_REVIEW_REQUIRED",
  "confidence": "HIGH|MEDIUM|LOW",
  "questions": [
    {
      "question_number": "string",
      "question_ref": "brief question description from memo",
      "learner_answer_summary": "concise summary of what learner wrote",
      "verdict": "COMPETENT|NOT_YET_COMPETENT|PARTIAL|FLAGGED",
      "marks_awarded": 0,
      "marks_available": 0,
      "ai_comment": "specific, constructive, NQF-aligned feedback",
      "assessor_flag": true|false,
      "flag_reason": "reason if flagged, null otherwise"
    }
  ],
  "moderation_notes": "patterns or concerns for the moderator",
  "assessor_override_required": true|false
}
PROMPT;
}

public function buildUserMessage(
    string $memoText,
    string $submissionText,
    string $learnerName,
    string $moduleTitle,
    string $submissionDate
): string {
    return <<<MSG
MARKING GUIDE / MEMO:
{$memoText}

---

LEARNER SUBMISSION:
Learner name: {$learnerName}
Module / Unit Standard: {$moduleTitle}
Submission date: {$submissionDate}

{$submissionText}

---

Please mark this submission against the memo above and return 
your response in the required JSON format only.
MSG;
}
```

#### Anthropic Client
```php
// classes/ai/anthropic_client.php
class ajananova_anthropic_client {

    private string $apiKey;
    private string $model = 'claude-sonnet-4-20250514';
    private int $maxTokens = 4096;

    public function __construct() {
        $this->apiKey = get_config('local_ajananova', 'anthropic_api_key');
    }

    public function mark(string $systemPrompt, string $userMessage): array {
        $payload = [
            'model'      => $this->model,
            'max_tokens' => $this->maxTokens,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userMessage]
            ],
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \moodle_exception('ajananova_api_error', 'local_ajananova',
                '', 'HTTP ' . $httpCode);
        }

        $data = json_decode($response, true);

        // Parse the JSON from Claude's text response
        $rawText = $data['content'][0]['text'] ?? '';
        $result  = json_decode($rawText, true);

        if (!$result) {
            throw new \moodle_exception('ajananova_parse_error', 'local_ajananova',
                '', 'Could not parse AI response as JSON');
        }

        // Attach token usage for billing
        $result['_tokens_input']  = $data['usage']['input_tokens']  ?? 0;
        $result['_tokens_output'] = $data['usage']['output_tokens'] ?? 0;
        $result['_api_id']        = $data['id'] ?? '';

        return $result;
    }
}
```

### 4.6 Marking Engine Orchestrator
```php
// classes/ai/marking_engine.php
class ajananova_marking_engine {

    public function run(int $submissionId, int $assessorId): array {

        // 1. Load submission and assignment
        $submission = $this->loadSubmission($submissionId);
        $assignment = $this->loadAssignment($submission->assignment);
        $learner    = $this->loadUser($submission->userid);

        // 2. Check credits
        $creditMgr = new ajananova_credit_manager();
        if (!$creditMgr->hasCredits()) {
            return ['status' => 'no_credits'];
        }

        // 3. Extract text from submission PDF
        $extractor      = new ajananova_pdf_extractor();
        $submissionText = $extractor->extract($submission->file_path);

        // 4. Extract memo text
        $memoText = $extractor->extract($assignment->memo_file_path);

        // 5. Build prompts
        $builder      = new ajananova_prompt_builder();
        $systemPrompt = $builder->buildSystemPrompt();
        $userMessage  = $builder->buildUserMessage(
            $memoText,
            $submissionText,
            fullname($learner),
            $assignment->name,
            date('Y-m-d', $submission->timemodified)
        );

        // 6. Call AI (real or mock)
        $mockMode = get_config('local_ajananova', 'mock_mode');
        if ($mockMode) {
            $client = new ajananova_mock_client();
        } else {
            $client = new ajananova_anthropic_client();
        }

        $result = $client->mark($systemPrompt, $userMessage);

        // 7. Annotate PDF
        $annotator      = new ajananova_pdf_annotator();
        $annotatedPath  = $annotator->annotate(
            $submission->file_path,
            $result['questions']
        );

        // 8. Save results to DB
        $this->saveResults($submissionId, $assessorId, $result, $annotatedPath);

        // 9. Log billable event (skip if mock)
        if (!$mockMode) {
            $logger = new ajananova_usage_logger();
            $logger->log([
                'event_type'    => 'ai_mark',
                'assessor_id'   => $assessorId,
                'learner_id'    => $submission->userid,
                'assignment_id' => $submission->assignment,
                'tokens_input'  => $result['_tokens_input'],
                'tokens_output' => $result['_tokens_output'],
                'api_id'        => $result['_api_id'],
            ]);
            $creditMgr->deductCredit();
        }

        return ['status' => 'success', 'result' => $result];
    }
}
```

---

## 5. AjanaNova Central Platform — Specification

### 5.1 Purpose
A Laravel web application hosted at `platform.ajananova.co.za` (or your chosen domain) that:
- Manages all client accounts and licence keys
- Receives usage events from all Moodle plugin instances
- Runs monthly billing jobs
- Generates and emails PDF invoices
- Provides the client self-service portal
- Provides the founder admin dashboard
- Hosts the web portal (upload + mark without Moodle)

### 5.2 Laravel Project Structure
```
ajananova-platform/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/
│   │   │   ├── DashboardController.php     ← founder overview
│   │   │   ├── ClientController.php        ← manage client accounts
│   │   │   └── InvoiceController.php       ← view/resend invoices
│   │   ├── Client/
│   │   │   ├── DashboardController.php     ← client self-service
│   │   │   ├── CreditsController.php       ← top-up credits
│   │   │   └── InvoiceController.php       ← client invoice history
│   │   ├── Api/
│   │   │   ├── UsageController.php         ← receives events from plugins
│   │   │   ├── LicenceController.php       ← validates licence keys
│   │   │   └── MarkingController.php       ← web portal AI marking endpoint
│   │   └── Portal/
│   │       └── MarkingController.php       ← web portal UI
│   ├── Models/
│   │   ├── Client.php
│   │   ├── LicenceKey.php
│   │   ├── UsageEvent.php
│   │   ├── CreditBalance.php
│   │   ├── Invoice.php
│   │   └── InvoiceLineItem.php
│   ├── Services/
│   │   ├── BillingService.php              ← monthly invoice generation
│   │   ├── AnthropicService.php            ← AI marking for web portal
│   │   ├── PdfAnnotationService.php        ← annotate PDFs (web portal)
│   │   ├── LicenceService.php              ← generate + validate keys
│   │   └── OnboardingService.php           ← new client setup flow
│   └── Console/Commands/
│       └── GenerateMonthlyInvoices.php     ← runs on 1st of each month
├── database/migrations/
│   ├── create_clients_table.php
│   ├── create_licence_keys_table.php
│   ├── create_usage_events_table.php
│   ├── create_credit_balances_table.php
│   ├── create_invoices_table.php
│   └── create_invoice_line_items_table.php
└── resources/views/
    ├── admin/                              ← founder dashboard views
    ├── client/                             ← client portal views
    └── portal/                             ← web portal marking views
```

### 5.3 Database Schema

#### clients
```sql
CREATE TABLE clients (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    company_name    VARCHAR(255) NOT NULL,
    contact_name    VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL UNIQUE,
    phone           VARCHAR(50),
    seta            VARCHAR(50) DEFAULT 'MICT',
    plan            ENUM('hosted_starter','hosted_growth','hosted_pro',
                         'plugin_licence','portal_only') NOT NULL,
    status          ENUM('active','suspended','cancelled') DEFAULT 'active',
    onboarded_at    TIMESTAMP,
    created_at      TIMESTAMP,
    updated_at      TIMESTAMP
);
```

#### licence_keys
```sql
CREATE TABLE licence_keys (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    client_id       BIGINT NOT NULL REFERENCES clients(id),
    licence_key     VARCHAR(64) NOT NULL UNIQUE,
    domain          VARCHAR(255),         -- null for portal-only clients
    plan            VARCHAR(50) NOT NULL,
    monthly_credits INT NOT NULL DEFAULT 25,
    status          ENUM('active','suspended','expired') DEFAULT 'active',
    expires_at      TIMESTAMP,
    created_at      TIMESTAMP
);
```

#### usage_events
```sql
CREATE TABLE usage_events (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    client_id       BIGINT NOT NULL REFERENCES clients(id),
    licence_key     VARCHAR(64) NOT NULL,
    event_type      ENUM('ai_mark','poe_generate','portal_mark') NOT NULL,
    source          ENUM('moodle_plugin','web_portal') NOT NULL,
    learner_ref     VARCHAR(100),
    assignment_ref  VARCHAR(100),
    tokens_input    INT DEFAULT 0,
    tokens_output   INT DEFAULT 0,
    credits_charged INT DEFAULT 1,
    mock_mode       TINYINT DEFAULT 0,
    api_response_id VARCHAR(100),
    signature       VARCHAR(64),
    billed          TINYINT DEFAULT 0,
    invoice_id      BIGINT,
    created_at      TIMESTAMP
);
```

#### credit_balances
```sql
CREATE TABLE credit_balances (
    id                  BIGINT PRIMARY KEY AUTO_INCREMENT,
    client_id           BIGINT NOT NULL REFERENCES clients(id),
    monthly_allowance   INT NOT NULL DEFAULT 25,
    credits_remaining   INT NOT NULL DEFAULT 25,
    topup_credits       INT NOT NULL DEFAULT 0,
    reset_date          DATE NOT NULL,
    updated_at          TIMESTAMP
);
```

#### invoices
```sql
CREATE TABLE invoices (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    client_id       BIGINT NOT NULL REFERENCES clients(id),
    invoice_number  VARCHAR(50) NOT NULL UNIQUE,
    period_start    DATE NOT NULL,
    period_end      DATE NOT NULL,
    subtotal_cents  INT NOT NULL DEFAULT 0,
    vat_cents       INT NOT NULL DEFAULT 0,
    total_cents     INT NOT NULL DEFAULT 0,
    status          ENUM('draft','sent','paid','overdue') DEFAULT 'draft',
    sent_at         TIMESTAMP,
    paid_at         TIMESTAMP,
    created_at      TIMESTAMP
);
```

#### invoice_line_items
```sql
CREATE TABLE invoice_line_items (
    id              BIGINT PRIMARY KEY AUTO_INCREMENT,
    invoice_id      BIGINT NOT NULL REFERENCES invoices(id),
    description     VARCHAR(255) NOT NULL,
    quantity        INT NOT NULL DEFAULT 1,
    unit_price_cents INT NOT NULL DEFAULT 0,
    total_cents     INT NOT NULL DEFAULT 0,
    line_type       ENUM('hosting','admin_fee','ai_marks','poe_credits',
                         'plugin_licence','topup','once_off')
);
```

### 5.4 API Endpoints (received from Moodle plugins)

#### POST /api/v1/usage — Log a billable event
```json
Request:
{
    "licence_key":   "abc123...",
    "event_type":    "ai_mark",
    "domain":        "https://client-moodle.co.za",
    "learner_id":    "moodle_user_42",
    "assignment_id": "moodle_assign_17",
    "tokens_input":  3200,
    "tokens_output": 850,
    "timestamp":     1711234567,
    "signature":     "hmac_sha256_of_payload"
}

Response:
{
    "status":            "ok",
    "credits_remaining": 44,
    "event_id":          12345
}
```

#### POST /api/v1/licence/validate — Check licence is active
```json
Request:  { "licence_key": "abc123...", "domain": "https://..." }
Response: { "valid": true, "plan": "hosted_growth", "credits_remaining": 44 }
```

#### POST /api/v1/mark — Web portal AI marking (no Moodle needed)
```json
Request (multipart/form-data):
- licence_key     (string)
- memo_pdf        (file)
- submission_pdf  (file)
- learner_name    (string)
- module_title    (string)

Response:
{
    "status":       "success",
    "result":       { ...AI marking JSON... },
    "annotated_pdf":"base64 encoded annotated PDF",
    "credits_remaining": 43
}
```

---

## 6. Web Portal UI — Specification

### 6.1 Purpose
A browser-based interface at `ajananova.co.za/portal` that allows any SDP — even those without Moodle — to upload a marking guide and learner submission, trigger AI marking, review results and download an annotated PDF.

### 6.2 Portal Pages

#### /portal/mark — Main marking interface
```
┌─────────────────────────────────────────────────────┐
│  AjanaNova AI Marking Portal                             │
│  Credits remaining: 43  [Top up]                    │
├─────────────────────────────────────────────────────┤
│                                                     │
│  Step 1: Upload marking guide / memo                │
│  [ Choose PDF file ]  memo_us_114076.pdf ✓          │
│                                                     │
│  Step 2: Upload learner submission                  │
│  [ Choose PDF file ]  john_dlamini_q1.pdf ✓         │
│                                                     │
│  Step 3: Learner details                            │
│  Name: [________________]                           │
│  Module/Unit Standard: [________________]           │
│                                                     │
│  ⚡ [Mark with AI]   (costs 1 credit)               │
│                                                     │
│  ⚠️  MOCK MODE ACTIVE — no credits consumed         │
└─────────────────────────────────────────────────────┘
```

#### /portal/results — Review and sign off
```
┌─────────────────────────────────────────────────────┐
│  AI Marking Results — John Dlamini                  │
│  Confidence: HIGH   Recommendation: NOT YET COMP.   │
├──────┬──────────────────────┬─────────┬─────────────┤
│  Q   │  Question            │ Verdict │  AI Comment │
├──────┼──────────────────────┼─────────┼─────────────┤
│  1   │  Define skills dev.. │  ✓ C    │  Good but.. │
│  2   │  Role of a SETA      │  ✗ NYC  │  Incomplete │
│  3   │  POE process         │  ⚠ FLAG │  Ambiguous  │
├──────┴──────────────────────┴─────────┴─────────────┤
│  Assessor override: [dropdown per question]         │
│  Final decision: [COMPETENT] [NOT YET COMPETENT]   │
│  Assessor name: [________________]                  │
│  [Sign off & Download annotated PDF]                │
└─────────────────────────────────────────────────────┘
```

### 6.3 Integration with Moodle Plugin
The web portal and Moodle plugin share:
- The same `AnthropicService` class (via central platform API)
- The same `PdfAnnotationService`
- The same credit system and billing logic
- The same client account and licence key

An SDP can use the Moodle plugin for most marking and the web portal for ad-hoc submissions — all usage appears on the same invoice.

---

## 7. Client Onboarding — Manual & Self-Service

### 7.1 Manual Onboarding (Admin triggers)
For AjanaNova-hosted clients where you provision the Moodle site:

1. Admin logs into `ajananova.co.za/admin`
2. Creates client account (company name, email, plan)
3. System generates licence key automatically
4. Admin provisions AWS EC2 + RDS for client
5. Admin installs Moodle + AjanaNova plugin on new instance
6. Copies licence key into plugin settings
7. Client receives welcome email with login URL, licence key, and billing info

**Admin dashboard route:** `ajananova.co.za/admin/clients/create`

### 7.2 Self-Service Onboarding (Client signs up themselves)
For plugin-licence and portal-only clients:

1. Client visits `ajananova.co.za/register`
2. Enters company name, email, selects plan
3. Pays via PayFast (SA payment gateway) or EFT
4. Receives welcome email with:
   - Licence key
   - Plugin download link + installation guide
   - Web portal login URL
   - First invoice
5. No human intervention required from AjanaNova side

**Self-service registration route:** `ajananova.co.za/register`

### 7.3 Onboarding Email Content
```
Subject: Welcome to AjanaNova — Your licence key and next steps

Hi [Contact Name],

Your AjanaNova account is ready.

Licence key:     AJANA-XXXX-XXXX-XXXX-XXXX
Plan:            Hosted Growth (R1,299/month)
AI marks:        200 per month
Moodle URL:      https://[client].ajananova.co.za
Admin login:     [email]
Temp password:   [auto-generated]

Web portal:      https://ajananova.co.za/portal
(Use same login)

Plugin install guide: https://ajananova.co.za/docs/plugin-install

Your first invoice will be generated on 1 [next month].

Welcome to AjanaNova — built by an SDP, for SDPs.
```

---

## 8. PDF Annotation — Implementation Notes

### 8.1 Libraries
- **Primary:** `smalot/pdfparser` for text extraction
- **Annotation:** `setasign/fpdi` + `tecnickcom/tcpdf` for stamping marks onto PDFs
- **OCR fallback:** Tesseract CLI (for scanned PDFs)

### 8.2 Annotation Logic
```php
// classes/pdf/annotator.php
// For each question result, calculate approximate Y position on page
// Stamp appropriate symbol:
//   COMPETENT      → green tick (✓) + comment text
//   NOT_YET_COMP   → red cross (✗) + comment text  
//   FLAGGED        → amber flag (⚑) + comment text + "ASSESSOR REVIEW REQUIRED"
// Add header banner: "AI PRE-MARKED — Awaiting assessor sign-off"
// Add footer: assessor name + signature line + date
```

### 8.3 Scan Quality Handling
```php
// If pdfparser returns < 100 characters from a multi-page PDF:
//   → Assume scanned document
//   → Attempt Tesseract OCR
//   → If OCR confidence < 60%: return status 'poor_quality'
//   → Show message to assessor: "PDF quality too low for AI marking.
//      Please resubmit a clearer scan or use manual marking."
```

---

## 9. Build Order / Recommended Sequence

Build in this order to have a testable, income-generating product as fast as possible:

### Phase 1 — Core AI Marking (Moodle plugin, mock mode first)
1. `db/install.xml` — create the three tables
2. `settings.php` — mock_mode toggle, API key field, central URL
3. `classes/ai/mock_client.php` — returns hardcoded mock response
4. `classes/ai/prompt_builder.php` — system + user message builders
5. `classes/ai/anthropic_client.php` — real API call (test with mock first)
6. `classes/ai/marking_engine.php` — orchestrator
7. `templates/marking_review.mustache` — assessor review UI
8. Test end-to-end in mock mode ← **no tokens consumed at this stage**
9. Switch mock_mode off, test with real API on one submission
10. `classes/pdf/annotator.php` — PDF annotation

### Phase 2 — Billing Infrastructure
11. `classes/billing/credit_manager.php` — credit check/deduct
12. `classes/billing/usage_logger.php` — log events + phone home
13. `db/events.php` + `classes/event_observer.php` — submission event hook
14. Central platform Laravel app (minimal: usage endpoint + client table)

### Phase 3 — Web Portal
15. Laravel web portal UI (`/portal/mark` and `/portal/results`)
16. `Api/MarkingController.php` — mark via API for portal
17. Shared `AnthropicService` between portal and plugin

### Phase 4 — Full Central Platform
18. Client self-service registration + PayFast integration
19. Admin dashboard (founder view)
20. Monthly billing job + PDF invoice generation
21. Client portal (usage, top-ups, invoice history)

---

## 10. Environment Variables & Configuration

### Moodle Plugin Settings (settings.php)
| Key | Description | Default |
|-----|-------------|---------|
| `local_ajananova\|mock_mode` | Enable mock AI responses | 1 (on) |
| `local_ajananova\|anthropic_api_key` | Anthropic API key | empty |
| `local_ajananova\|central_platform_url` | AjanaNova Central Platform URL | empty |
| `local_ajananova\|licence_key` | This client's licence key | empty |
| `local_ajananova\|ai_mark_cost_credits` | Credits per AI mark | 1 |
| `local_ajananova\|poe_cost_credits` | Credits per POE export | 1 |

### Central Platform .env (Laravel)
```
APP_NAME=AjanaNova
APP_URL=https://platform.ajananova.co.za

DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=ajananova_central

ANTHROPIC_API_KEY=sk-ant-...
ANTHROPIC_MODEL=claude-sonnet-4-20250514

PAYFAST_MERCHANT_ID=...
PAYFAST_MERCHANT_KEY=...
PAYFAST_PASSPHRASE=...

AJANANOVA_HMAC_SECRET=... (shared with all plugin instances for signature verification)

MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=af-south-1
AWS_BUCKET=ajananova-documents
```

---

## 11. Testing Checklist (Before Going Live)

### Mock Mode Tests (zero cost)
- [ ] Plugin installs on fresh Moodle without errors
- [ ] Mock mode banner displays correctly
- [ ] Assessor can trigger "Mark with AI" on a submission with a rubric
- [ ] Mock response displays correctly in review screen
- [ ] Assessor can override any question verdict
- [ ] Assessor can sign off and result flows into POE
- [ ] Credits NOT deducted in mock mode
- [ ] Usage NOT logged to central platform in mock mode
- [ ] Manual marking still works when AI button is skipped
- [ ] Credits exhausted screen displays correctly (set credits to 0 to test)

### Real API Tests (uses tokens — test with short documents)
- [ ] Real Anthropic API call succeeds
- [ ] JSON response parses correctly
- [ ] PDF annotation produces readable output
- [ ] Credit deducted correctly after successful mark
- [ ] Usage event logged to central platform
- [ ] Usage event visible in founder admin dashboard
- [ ] Monthly invoice includes the test event

### Web Portal Tests
- [ ] PDF upload works for both memo and submission
- [ ] AI marking returns results correctly
- [ ] Annotated PDF downloadable
- [ ] Same credit balance reflected in both portal and plugin

---

## 11b. Compliance Research Document

Full research on SAQA / QCTO / SETA regulatory framework, assessment workflow, POE requirements, digital signature legality, data retention, and feature gap analysis is stored in:

**`local/COMPLIANCE_RESEARCH.md`**

Key findings:
- Platform must support two tracks: legacy SETA (until June 2027) and new QCTO occupational qualifications
- 28-step compliance workflow from enrolment to certification
- Digital signatures are legally valid under ECTA for all skills development documents
- Major feature gaps identified — assessor/moderator registration gating, VACS checking, moderation workflow, SOR generation

---

## 12. Key Contacts & Resources

| Resource | URL |
|----------|-----|
| Anthropic API docs | https://docs.anthropic.com |
| Moodle plugin dev docs | https://moodledev.io |
| Moodle local plugin template | https://github.com/moodlehq/moodle-local_plugintemplate |
| FPDI PDF library | https://www.setasign.com/products/fpdi/about/ |
| smalot/pdfparser | https://github.com/smalot/pdfparser |
| PayFast integration | https://developers.payfast.co.za |
| MICT SETA certification page | https://www.mict.org.za/certification/ |

---

## 13. Notes for Claude Code

- **Start with Phase 1, mock mode first.** Do not write any real API calls until mock mode works end-to-end.
- The existing Moodle plugin already has facilitation, assessment and moderation built. Do not modify those. Build the AI marking as a new feature layer on top.
- All new tables must be created via `db/install.xml` — never raw SQL.
- Follow Moodle coding standards: `snake_case` for functions, `frankenstyle` naming (`local_ajananova_`).
- Use Moodle's `\core\http_client` for HTTP calls where possible.
- The central platform API must verify the HMAC signature on every incoming usage event before processing it.
- Never log usage events from mock mode submissions.
- The assessor must always be able to override the AI verdict — this is a hard compliance requirement.
- When in doubt about MICT SETA document requirements, refer to `mict.org.za/certification/` for the current certification process.

---

## 14. Build Progress Log — Claude Code Sessions

### Session 1 — 2026-03-30 (Phase 1 complete, live on Moodle)

#### Rename
- Plugin was originally named AjanaNova. Renamed to **AjanaNova Grader** (`local_ajananova`).
- Handover doc renamed from `ajananova_HANDOVER.md` to `AJANANOVA_HANDOVER.md`.

#### Plugin slug / identifiers
| Item | Value |
|------|-------|
| Component | `local_ajananova` |
| Directory | `local/ajananova/` |
| Namespace | `local_ajananova\` |
| DB table prefix | `ajananova_` |
| Plugin name (user-facing) | AjanaNova Grader |

#### Files built
```
local/ajananova/
├── version.php                          ✓ installed
├── lib.php                              ✓ installed
├── settings.php                         ✓ installed — all 6 settings confirmed in Moodle UI
├── mark.php                             ✓ installed — entry point for AI marking
├── composer.json                        ✓ — setasign/fpdi, smalot/pdfparser, tcpdf
├── vendor/                              ✓ — composer install run locally, uploaded to server
├── db/
│   └── install.xml                      ✓ installed — 3 tables created in DB
├── classes/ai/
│   ├── mock_client.php                  ✓ — hardcoded 3-question mock response
│   ├── prompt_builder.php               ✓ — NQF-aligned system + user prompts
│   ├── anthropic_client.php             ✓ — real API wrapper (not tested yet)
│   └── marking_engine.php               ✓ — orchestrator (mock-safe)
├── classes/billing/
│   ├── credit_manager.php               ✓ — has_credits / deduct_credit / sync_from_central
│   └── usage_logger.php                 ✓ — local insert + phone-home to central platform
├── classes/pdf/
│   ├── extractor.php                    ✓ — pdfparser + Tesseract OCR fallback
│   └── annotator.php                    ✓ — FPDI/TCPDF stamp ticks/crosses/flags
├── classes/output/
│   └── marking_review.php               ✓ — renderable/templatable context builder
├── templates/
│   ├── marking_review.mustache          ✓ — assessor review UI confirmed rendering
│   └── credits_exhausted.mustache       ✓ — shown when credits = 0
└── lang/en/
    └── local_ajananova.php              ✓ — all strings, no AjanaNova references remain
```

#### Mock mode test results (confirmed live on Moodle)
- ✅ Plugin installs cleanly, settings page renders correctly
- ✅ `mark.php?submissionid=X` loads without errors
- ✅ Mock mode banner displays
- ✅ 3-question results table renders (green/red/amber rows)
- ✅ Verdicts: Competent ✓ / Not Yet Competent ✗ / Flagged ⚑
- ✅ Marks (8/10, 3/10, 0/10) display correctly
- ✅ AI comments display correctly
- ✅ Assessor override dropdowns working
- ✅ Final decision radio buttons (Competent / Not Yet Competent) working
- ✅ Assessor name field present
- ✅ Sign off & download button present
- ✅ HIGH confidence badge (green) top-right
- ✅ Credits remaining: 0 shown correctly
- ✅ Credits exhausted screen works (shown when mock_mode=0 and credits=0)

#### Known deviations from spec
- `$submission->file_path` and `$assignment->memo_file_path` are **not real Moodle columns** — Moodle stores files via the File API. PDF extraction and annotation are **skipped in mock mode** and will need proper Moodle file API integration before real mode PDF annotation works.
- `annotated_pdf_fileid` is saved as `null` — File API integration is a Phase 1 Step 10 task still to complete for real mode.

#### Bugs fixed during session
1. Credit check ran before mock mode flag was read → showed "credits exhausted" on fresh install
2. `$mockmode` declared twice in `marking_engine.php` → deduplicated
3. Dynamic mustache string key `confidence_badge_class_{{confidence}}` → replaced with pre-computed PHP value `confidence_badge_class`
4. Stale `ajananova_` exception string keys in `anthropic_client.php`, `extractor.php`, `annotator.php`, `mark.php` → renamed to `ajananova_`

---

#### Where to pick up next session

**Immediate next step: Real API test (Phase 1 Step 9)**
1. User needs to add their Anthropic API key in Moodle → Site Admin → AjanaNova Grader settings
2. Uncheck Mock mode
3. Hit `mark.php?submissionid=X` — this will hit the real Anthropic API

**Before real mode works fully, two things are needed:**
- **PDF file resolution** — replace `$submission->file_path` with proper Moodle File API calls to get the actual submitted file. The `assign_submission` table doesn't have a `file_path` column; files are in `mdl_files` linked via `component='assignsubmission_file'`.
- **Memo file resolution** — assignments don't have a `memo_file_path` column either. Need to decide where the marking guide/memo is stored (assignment intro attachment? custom field? separate upload in plugin settings per assignment?).

**After real mode works: Phase 2**
- `db/events.php` + `classes/event_observer.php` — hook into Moodle assignment submission events to surface the "Mark with AI" button automatically in the assignment grading UI (currently accessed via direct URL only)
- `classes/billing/credit_manager.php` + `usage_logger.php` are already built — just need the central Laravel platform stood up to receive the phone-home calls

---

---

### Session 2 — 2026-03-31 (Phase 1 Step 9 unblocked)

#### Rename pass
- All remaining `ZEAL` / `zeal_` / `local_zeal` / `zealskills.co.za` references in this document replaced with `AjanaNova` / `ajananova_` / `local_ajananova` / `ajananova.co.za`.

#### Memo / criteria design decision
Decided NOT to use upload-at-mark-time (would require re-uploading per learner). Instead:
1. **Primary:** read criteria from Moodle's advanced grading (rubric or marking guide) — zero extra UX burden for assessors who already configured grading.
2. **Fallback:** memo PDF uploaded once per assignment in a new "AjanaNova AI Marking" section of the assignment create/edit form.
3. **Block:** if neither is present, show a clear error directing the assessor to configure one.

#### Files changed / created
| File | Change |
|------|--------|
| `lib.php` | Added `local_ajananova_coursemodule_standard_elements` — injects "AjanaNova AI Marking" file manager section into assignment form |
| `lib.php` | Added `local_ajananova_coursemodule_edit_post_actions` — saves memo PDF into Moodle file store (`local_ajananova / memo / $assignid`) |
| `classes/grading/criteria_reader.php` | **New.** Reads rubric or marking guide from `grading_areas` + `grading_definitions` + `gradingform_rubric_criteria/levels` + `gradingform_guide_criteria`. Returns formatted plain text for the AI prompt. Returns `''` if no advanced grading configured. |
| `classes/pdf/extractor.php` | Added `extract_from_stored_file(\stored_file)` — copies stored file to temp path, extracts, cleans up. |
| `classes/ai/marking_engine.php` | Real mode step 3 rewritten: Moodle File API for submission PDF, criteria_reader as primary memo source, memo PDF fallback, `ajananova_no_criteria` error if neither present. Annotator temp-file path fixed. |
| `lang/en/local_ajananova.php` | Added 6 new strings for form section and real-mode errors. |

#### Plugin slug table (unchanged)
| Item | Value |
|------|-------|
| Component | `local_ajananova` |
| Directory | `local/ajananova/` |
| Namespace | `local_ajananova\` |
| DB table prefix | `ajananova_` |
| Plugin name (user-facing) | AjanaNova Grader |

#### Known: version.php bump required
After any `lib.php` change that adds new callbacks, bump `$plugin->version` in `version.php` and run Moodle upgrade to clear the callback cache.

#### Where to pick up next session

**Phase 1 Step 9 — Real API test (prerequisites now met)**
1. Bump `version.php`, reinstall plugin on Moodle.
2. Add Anthropic API key: Site Admin → AjanaNova Grader settings.
3. Uncheck Mock mode.
4. Open an assignment with a rubric/marking guide + at least one learner PDF submission.
5. Hit `mark.php?submissionid=X` — calls the real Anthropic API.
6. Verify JSON result, annotated PDF, credit deduction, usage log.

**Phase 1 Step 10 — COMPLETE**
- Annotated PDF now saved into `assignfeedback_editpdf / download / $grade->id` automatically after real AI marking.
- POE export plugin (`local_poeexport`) picks it up with zero changes — appears as `XX - Annotated.pdf` in the learner ZIP.
- `annotated_pdf_fileid` in `ajananova_marking_results` now populated with the stored file id.

---

### Session 2 continued — additional changes (2026-03-31)

#### Gear menu approach (replaced broken form injection)
The `coursemodule_standard_elements` form callback would not fire despite correct implementation — likely a PHP OPcache issue on Afrihost WHM that could not be cleared. Replaced with `extend_settings_navigation` which adds two links to the assignment gear menu (⚙):

| Link | Destination |
|------|-------------|
| **AjanaNova: Upload marking guide** | `local/ajananova/memo.php?cmid=X` |
| **AjanaNova: Mark with AI** | `mod/assign/view.php?id=X&action=grading` (grading table) |

#### New file: memo.php
Standalone page using a proper `moodleform` class to handle the memo PDF upload. Uses Moodle File API (`local_ajananova / memo / $assignid`). Shows an info banner if a rubric/marking guide is already configured.

#### Assessor name — auto-populated
Removed the manual assessor name text field. Name now comes from `fullname($USER)` automatically — passed as read-only display text + hidden field in the sign-off form.

#### Settings.php fix
Default `central_platform_url` was still pointing to `zealskills.co.za` — fixed to `platform.ajananova.co.za`.

#### ModSecurity
Turned OFF on Afrihost WHM for testing. **Must be re-enabled with Moodle whitelist rules before going live with client data.** Add whitelist for `/mod/assign/view.php` and `/local/ajananova/` paths.

#### Files changed this session
| File | Change |
|------|--------|
| `lib.php` | Replaced form callbacks with `extend_settings_navigation` gear menu links |
| `memo.php` | **New.** Standalone memo PDF upload page (moodleform) |
| `templates/memo.mustache` | Created (not used — moodleform renders directly) |
| `settings.php` | Fixed default central platform URL |
| `classes/output/marking_review.php` | Added `assessor_name` from `fullname($USER)` |
| `templates/marking_review.mustache` | Assessor name now read-only display + hidden field |
| `classes/ai/marking_engine.php` | Step 10: `save_annotated_pdf()` helper saves to `assignfeedback_editpdf` |
| `classes/pdf/annotator.php` | Comment fix (ZEAL → AjanaNova) |
| `lang/en/local_ajananova.php` | Added strings for gear menu, memo page, real-mode errors |

#### Where to pick up next session

**Immediate — Phase 1 Step 9: Real API test**

Prerequisites are ALL met:
- ✅ Submission PDF: Moodle File API
- ✅ Memo/criteria: rubric reader → memo PDF fallback → clear error if neither
- ✅ Annotated PDF: saved to `assignfeedback_editpdf` (POE export compatible)
- ✅ Assessor name: auto from `$USER`
- ✅ Gear menu: Upload marking guide + Mark with AI links working
- ✅ Mock mode: confirmed working on live Moodle

**Steps to test real API:**
1. Sign up at `console.anthropic.com`, add credit balance (~$10), create API key
2. Site Admin → AjanaNova Grader → paste API key → **uncheck Mock mode** → Save
3. Find a submission ID via phpMyAdmin SQL (see Session 2 notes above)
4. Ensure that assignment has a rubric/marking guide, OR upload memo via gear menu
5. Hit `https://your-moodle/local/ajananova/mark.php?submissionid=X`
6. Verify: results table, annotated PDF saved, credit deducted, usage logged to DB

**After real API confirmed — Phase 2**
- `db/events.php` + `classes/event_observer.php` — inject "Mark with AI" button directly into the Moodle assignment grading panel per learner (so assessors don't need to construct the URL manually)
- Stand up minimal Laravel central platform to receive phone-home usage events from `usage_logger.php`

---

## 15. Planned Future Feature — Quiz Essay AI Marking

**Owner request:** Add AI marking support for Moodle quiz essay questions (mod_quiz), in addition to the current mod_assign integration.

### Why it's different from assignment marking
- No PDF to extract — learner answers are plain text already stored in `question_attempt_steps` (simpler extraction)
- Hook point is the quiz manual grading interface, not `mark.php`
- Criteria source is the question text itself + any quiz rubric configured on the essay question

### What stays the same
- Same AI marking engine (`marking_engine.php`)
- Same Anthropic API client
- Same credit / billing system
- Same JSON verdict format (COMPETENT / NOT_YET_COMPETENT / FLAGGED)

### Suggested build order (when ready)
1. `classes/quiz/answer_extractor.php` — fetch essay answer text from `question_attempt_steps` by attempt id
2. `classes/quiz/quiz_marking_engine.php` — thin wrapper around the existing `marking_engine` using answer text instead of PDF
3. Hook into quiz manual grading UI to surface "Mark with AI" button per essay answer
4. Reuse existing credit deduction and usage logging

### Build after
Phase 2 (central platform) is complete — do not start this until the assignment integration is fully live and tested.

---

---

### Session 3 — 2026-04-02 (Real API confirmed working)

#### Real API test — CONFIRMED
- First real Anthropic API call succeeded
- KM-01 assignment marked COMPETENT, 17 questions, all verdicts accurate
- Credits deducted correctly, usage logged to DB

#### Bugs fixed
| Bug | Fix |
|-----|-----|
| `context_module` class not found | Added `\` prefix — global namespace |
| JSON parse error | Prefill assistant turn with `{` to force JSON output; strip code fences |
| "Composer dependencies not installed" | vendor path was `../../../../` (wrong), fixed to `../../` in both extractor.php and annotator.php |
| OCR blocking marks on Praesignis workbooks | pdfparser returns empty on these PDFs; OCR not on Afrihost; now falls back to placeholder text so API call proceeds |

#### UI improvements
- Score summary card: total marks, percentage, progress bar
- Modern override dropdowns with colour feedback on change
- Sign-off button renamed "Confirm sign-off & save" (no browser download — POE export handles PDF at end of learnership)

#### Key architectural note — PDF extraction
Praesignis workbooks (and likely most client PDFs) render answer boxes as image/form layers that pdfparser cannot extract. Current workaround: placeholder text. **Planned fix: Vision API upgrade** — send PDF pages as images directly to Claude instead of extracting text. This also removes Tesseract dependency and handles handwritten submissions natively.

#### GitHub
Repo live: https://github.com/PhillaneT/ajananova (private)
All session changes committed and pushed.

#### Where to pick up next session

**Option A — Vision API upgrade (recommended)**
Replace pdfparser text extraction with Claude Vision (send PDF pages as images). This fixes the Praesignis workbook extraction issue permanently and makes PM (practical) assignments markable too.

**Option B — Phase 2: Event observer**
Add `db/events.php` + `classes/event_observer.php` to inject "Mark with AI" button directly into Moodle grading panel per learner (currently accessed via direct URL only).

**Option C — Auto-deploy setup**
✅ DONE (Session 4) — VS Code SFTP extension configured. Save any file → auto-uploads. sftp.json at `local/.vscode/sftp.json`.

---

### Session 4 additions (2026-04-02)

#### What was completed
- VS Code SFTP auto-deploy configured (deploy@edusignis.com, remotePath="/")
- Cached results in mark.php — revisiting AI marking page no longer re-runs the AI or costs credits. Cached results load from `ajananova_marking_results`. Add `?force=1` to URL to trigger a fresh run.
- `temperature: 0` added to Anthropic API call — results are now deterministic
- "Re-run AI marking" button added to review UI (only shown on cached results, includes credit warning)
- "Mark with AI" gear menu link now goes directly to mark.php?submissionid=X when assessor is on a specific student's grading page
- "View annotated PDF" button added to review UI card header
- Annotated PDF confirmed working — Session 4 PDF shows correct per-question annotations

#### Known issues fixed this session
- SFTP remotePath was `/public_html/moodle45/local` — should be `/` (FTP account root is already the local folder). Accidentally created `local/public_html/` on server — **delete this folder via cPanel File Manager**.
- `platform.ajananova.co.za` URL blocked warning — fix: Site admin → Security → HTTP security → cURL allowed list → add `platform.ajananova.co.za`

#### PDF annotation style — PENDING (SAQA requirement)
**Current behaviour:** The annotator writes the question number + full AI comment text as a small annotation bubble on the PDF.

**Required change:** For SAQA/MICT SETA compliance, the PDF should show only a **red tick** (✓) or **red cross** (✗) at the relevant answer box — no comment text on the PDF. The full AI comments remain in the Moodle marking review table only.

**Why:** SAQA assessors reviewing physical/printed POEs should see the assessor's mark decision only, not the AI's reasoning. The AI commentary is for the assessor's internal use.

**Implementation notes:**
- Change `annotator.php` to draw a simple tick/cross glyph instead of a text annotation box
- Tick = COMPETENT or PARTIAL verdict, Cross = NOT_YET_COMPETENT or FLAGGED
- Red colour for assessor marks (green for moderator when moderation workflow is added)
- Position: top-right of each answer box (current question_number position logic can stay)
- Remove the `ai_comment` text from the PDF annotation entirely

#### Where to pick up next session

**Priority 1 — Fix PDF annotation style (SAQA)**
Change annotator.php to draw tick/cross glyphs only (no text). See notes above.

**Priority 2 — Vision API upgrade**
Replace pdfparser text extraction with Claude Vision (send PDF pages as images). Fixes Praesignis workbook extraction and makes PM practical assignments markable.

**Priority 3 — Phase 2: Grading panel integration**
Add `db/events.php` + `classes/event_observer.php` to inject AI marking results summary banner directly into Moodle grading panel after marking.

---

### Session 5 — 2026-04-03 (Product strategy decisions)

#### Competitive landscape review
Reviewed global and South African AI grading tools. Key findings:
- **EduFlare** and **Siyavula** are the only commercial SA AI marking products — both target schools (CAPS/IEB), not SDPs or learnerships. Not direct competitors.
- **No commercial product exists** for SETA-aligned AI grading, POE management, or SDP compliance workflow in South Africa. The market gap is real and unoccupied.
- Global tools (Graide, CoGrader, EssayGrader, Marking.ai etc.) are cheap because they mark school essays with no compliance context, no hosting, no workflow, no LMS integration. Competing on price with them is not the strategy.

#### Product strategy decisions

**Decision 1 — Hosted Moodle tiers: PARKED**
The "Hosted Starter / Growth / Pro" tiers (AWS-managed Moodle per client) are parked indefinitely. Do not build billing or onboarding around these tiers until explicitly revisited.

**Decision 2 — Web portal scope: FULL ASSESSOR + MODERATOR WORKFLOW**
The web portal is a standalone SDP compliance platform for clients who have no Moodle. It is NOT a cheap essay-upload tool and is NOT trying to recreate government systems (QCTO/SAQA enrolment, SETA registration etc.). Full scope:
- AI marking (same engine as Moodle plugin)
- Assessor review and sign-off
- Moderation workflow
- POE auto-compilation per learner
- SOR generation (ready for manual QCTO submission)

**Decision 3 — POE generation added to web portal**
POE export will be available in the web portal as a paid-per-learner feature. Gives no-Moodle SDPs a complete paperless assessment workflow:

```
Upload submission → AI marks → Assessor signs off → Moderator signs off → POE compiled → SOR generated → Download
```

**Decision 4 — Refined app definition and scope**
AjanaNova Grader covers the ~80% of SDP labour that happens *before* government system submission. It does not replace QCTO, SAQA, or SETA portals. SDPs continue to submit to those systems manually — AjanaNova just makes everything they need ready to submit.

See `COMPLIANCE_RESEARCH.md` for full regulatory research, VACS justification for AI marking, and the in-scope vs out-of-scope feature table.

#### Revised product map

| Product | Target | Status |
|---------|--------|--------|
| Moodle Plugin (`local_ajananova`) | SDPs on Moodle (self-hosted or any host) | Active — Phase 1 complete |
| Web Portal (Laravel) | SDPs with no Moodle | Full assessor/moderator workflow — Phase 3 build |
| Hosted Moodle tiers | — | Parked |

#### Pricing
Deferred until all features are complete. Do not finalise pricing mid-build.

---

#### Where to pick up next session

**Build priorities (in order):**

1. ✅ **PDF annotation style fix (SAQA compliance)** — `classes/pdf/annotator.php`
   - PDF shows only red ✓ (COMPETENT/PARTIAL) or red ✗ (NYC/FLAGGED) + question number. No comment text on PDF.
   - AI feedback per question now stored in `assignfeedback_comments` — visible and editable in native Moodle grader view (`action=grader&userid=x`). Editable after appeals.
   - Annotated PDF also saved to `assignfeedback_file/feedback_files` — shows as download link in grading table (`action=grading`).
   - `COLOUR_COMPETENT/NYC/PARTIAL/FLAGGED` replaced with single `COLOUR_MARK` (red). `truncate()` and `COMMENT_SIZE` removed.

2. **Vision API upgrade** — replace `classes/pdf/extractor.php` pdfparser extraction with Claude Vision (send PDF pages as images). Fixes Praesignis workbook extraction permanently.

3. **Moderation workflow** — new feature layer:
   - Moderator sign-off per assessment
   - 25% formative coverage tracking
   - 100% summative gate before SOR generation

4. **SOR generation** — generate QCTO-format Statement of Results PDF per learner, ready for manual QCTO submission

5. **Phase 2 — Grading panel integration** — `db/events.php` + `classes/event_observer.php`

---

---

### Session 6 — 2026-04-03 (PDF annotation, Moodle feedback integration, sign-off fix)

#### Strategy and research completed this session
- Full SETA/SAQA/QCTO compliance framework researched and documented in `COMPLIANCE_RESEARCH.md`
- Competitive landscape confirmed: no commercial SDP-focused AI marking product exists in SA
- App scope refined: AjanaNova covers the assessor/moderator workflow only — not government submission systems (QCTO, SAQA portals). This is ~80% of the SDP's labour.
- AI marking under VACS confirmed legally defensible: assessor sign-off is the legal anchor; AI is a tool
- Web portal scope expanded to full SETA workflow + POE export (not a cheap essay-upload tool)
- Hosted Moodle tiers parked — pricing deferred until all features complete
- Deleted accidentally created `local/public_html/` folder on server (from Session 4 SFTP issue)
- SFTP auto-deploy issue identified: Claude Code edits don't trigger VS Code save events — manual upload via cPanel required. SFTP config is at `local/.vscode/sftp.json` but needs to be at workspace root `.vscode/sftp.json` for Ctrl+Shift+P commands to work.

#### PDF annotation — rebuilt (annotator.php)
**Old behaviour:** ZapfDingbats font symbols + full AI comment text in right margin. Broke on Praesignis PDFs (wrong characters rendered), Q prefix on question IDs produced `QKM-01-KT01.2`.

**New behaviour:**
- All marks drawn **geometrically** (no font dependency — always renders correctly)
- Red ✓ per mark awarded, red ✗ per mark not awarded — one glyph per mark
- Fraction label below glyphs (e.g. `3/5`)
- Thin separator line between questions
- Cover page (page 1) skipped — marks distributed from page 2 onward
- **No comment text on PDF** — SAQA/MICT SETA compliance standard

**Known limitation (pending Vision API):** Without Vision API, question placement on pages is estimated by distributing evenly across content pages. Marks will not align precisely with answer boxes until Vision API upgrade detects exact page positions.

#### Moodle feedback integration (marking_engine.php)
Three new private methods added:

| Method | What it does |
|---|---|
| `save_feedback_comment()` | Writes compact AI summary to `assignfeedback_comments` |
| `save_feedback_file()` | Saves annotated PDF to `assignfeedback_file/feedback_files` — download link in grading table |
| `build_feedback_html()` | Builds compact summary: result + confidence + score + link to mark.php |

**Feedback comments column** (`action=grading`) now shows:
```
AjanaNova AI Pre-Mark
Result: COMPETENT
Confidence: HIGH
Score: 102/107 marks
View full AI feedback & sign off →
```
Full per-question AI table lives on `mark.php` only — not crammed into the Moodle column.

#### Sign-off bug fix (mark.php + marking_review.mustache)
**Bug:** `required_param('final_verdict')` crashed if assessor clicked sign-off without selecting a radio button.

**Fixes:**
- Radio buttons now **pre-select the AI recommendation** — assessor only changes if they disagree
- JS validation (`ajananovaValidateSignoff()`) blocks form submission with inline error if no verdict selected
- Server-side: switched to `optional_param` + redirect with error message instead of crash
- `assessor_name` falls back to `fullname($USER)` server-side if missing
- New lang string: `signoff_verdict_required`

#### Files changed this session
| File | Change |
|---|---|
| `classes/pdf/annotator.php` | Geometric tick/cross glyphs, marks per mark, cover page skip, no text |
| `classes/ai/marking_engine.php` | `save_feedback_comment()`, `save_feedback_file()`, `build_feedback_html()` |
| `mark.php` | `optional_param` for `final_verdict`, server-side verdict guard |
| `templates/marking_review.mustache` | Pre-selected radio, JS validation, inline error message |
| `lang/en/local_ajananova.php` | Added `signoff_verdict_required` string |
| `AJANANOVA_HANDOVER.md` | Session 5 + Session 6 notes |
| `COMPLIANCE_RESEARCH.md` | New file — full SETA/QCTO/SAQA compliance research |

#### Where to pick up next session

**Priority 1 — Vision API upgrade** (`classes/pdf/extractor.php` + `classes/ai/marking_engine.php`)
Replace pdfparser text extraction with Claude Vision — send PDF pages as base64 images directly to the API. This fixes Praesignis workbook extraction (answer boxes are image layers pdfparser cannot read) AND fixes PDF annotation placement (Vision can identify which page each question answer appears on).

**Priority 2 — Moderation workflow** (new feature)
- Moderator sign-off per assessment
- 25% formative coverage tracking  
- 100% summative gate before SOR generation

**Priority 3 — SOR generation**
Generate QCTO-format Statement of Results PDF per learner, ready for manual QCTO submission.

**Priority 4 — Phase 2: Grading panel integration**
`db/events.php` + `classes/event_observer.php` — inject AI results summary into Moodle grading panel automatically after marking.

---

*End of AJANANOVA_HANDOVER.md — Updated 2026-04-03 Session 6*
*Built by an SDP, for SDPs.*
