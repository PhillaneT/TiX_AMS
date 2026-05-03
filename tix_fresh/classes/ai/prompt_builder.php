<?php
// This file is part of the ZEAL local plugin for Moodle.

namespace local_ajananova\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Builds the system prompt and user message sent to the Anthropic API.
 *
 * Keeping prompt construction here (rather than in the client or engine) means
 * the prompts can be unit-tested and evolved independently of the HTTP layer.
 */
class prompt_builder {

    /**
     * Returns the system prompt that governs AI marking behaviour.
     *
     * The prompt enforces NQF-aligned assessment principles and mandates a
     * strict JSON-only response format so the client can parse it reliably.
     *
     * @return string
     */
    public function build_system_prompt(): string {
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

INTERPRETATION RULE (NQF competence-based assessment):
Award marks where a learner demonstrates clear understanding of the required
concept, even if they use different terminology, phrasing or examples than
those listed in the memo. The memo is a guide to what knowledge should be
present — not a word-for-word answer key. Penalise only for genuinely missing
knowledge or understanding, not for missing specific words or the exact
examples listed. Where the learner's answer is substantively correct but
expressed differently, award the mark and note the difference in ai_comment.

// NOTE FOR DEVELOPERS:
// If SAQA or MICT SETA requires strict memo-only marking with no AI
// interpretation, remove the INTERPRETATION RULE block above and revert
// rule 1 to: "Evaluate ONLY and literally against the provided memo/marking
// guide — award marks only for points that exactly match the memo."
// See GitHub issue tracker for SAQA compliance discussion.

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
      "assessor_flag": true,
      "flag_reason": "reason if flagged, null otherwise"
    }
  ],
  "moderation_notes": "patterns or concerns for the moderator",
  "assessor_override_required": true
}
PROMPT;
    }

    /**
     * Builds the user message containing the memo and learner submission.
     *
     * @param  string $memotext        Full text extracted from the marking guide / memo PDF.
     * @param  string $submissiontext  Full text extracted from the learner's submission PDF.
     * @param  string $learnername     Full name of the learner.
     * @param  string $moduletitle     Module or unit standard title.
     * @param  string $submissiondate  Date of submission (Y-m-d).
     * @return string
     */
    public function build_user_message(
        string $memotext,
        string $submissiontext,
        string $learnername,
        string $moduletitle,
        string $submissiondate
    ): string {
        return <<<MSG
MARKING GUIDE / MEMO:
{$memotext}

---

LEARNER SUBMISSION:
Learner name: {$learnername}
Module / Unit Standard: {$moduletitle}
Submission date: {$submissiondate}

{$submissiontext}

---

Please mark this submission against the memo above and return
your response in the required JSON format only.
MSG;
    }
}
