# 0020: Content and the knowledge base

**Status:** accepted (A5.3a, 22 November 2026)

## Context

REOS sells courses (qualifying, CE, post-license, exam prep, electives) in several states, in English and Spanish, and its AI answers must quote REOS's own lessons with their source. Lessons are text, slide decks, worksheets and long videos. The market leaders host video and sell courses, but none lets a learner search *inside* lessons and land on the slide or the moment in the video. Sprint 5 decisions: Titan Text Embeddings V2 on Bedrock (us-east-2, 1,024 dimensions) and Mux for video.

## Decision

1. **Model.** `courses` › `course_modules` › `lessons`, each lesson with `lesson_media` (video, slides, document, captions). Courses carry `states` (null: every state; the `StateTagged` scope) and, for CE and qualifying courses, the regulator's approval number per state. A course without an approval number in a state it is tagged to can't be Live ("no labels without approval", screen 63). Statuses: draft, in review, live, hidden. Content is REOS's for now (files under `platform/`); school content (Sprint 7) adds an owner and lives under `tenants/<id>/`.
2. **Uploads never pass through the app server.** The browser asks for a short-lived signed PUT link (S3 in staging and production; SeaweedFS or the local disk elsewhere), uploads, then says it's done. REOS checks the size and the file's first bytes against its extension, then queues it. Allowed types per kind with size limits (video 5 GB, slides and documents 100 MB, captions 2 MB).
3. **Virus scan first.** Every file is streamed to ClamAV (`clamd` INSTREAM, `CLAMAV_HOST`) before anything reads it. Infected files are deleted; if the scanner can't be reached the job waits and tries again. Production with no scanner refuses files; local machines and CI accept them unscanned.
4. **Video on Mux** (Settings › Integrations › Mux Video): Mux copies the file from a signed S3 link, plays it with signed playback (RS256 tokens from the signing key) and writes captions in the lesson's language. Mux's signed webhook (`/webhooks/mux`, five-minute tolerance, each event once) marks the video ready and delivers captions, which are fetched and read. Off: the original file plays. The Simulator (outside production) is ready at once. An uploaded captions file (.vtt) takes precedence over generated captions.
5. **The knowledge base** (`kb_passages`). A queued pipeline (`content` queue) splits each lesson into passages that make sense alone: text by heading (about 900 characters), slides and PDF pages one at a time (PowerPoint with speaker notes, Word with its headings), captions in 45-second windows. Each passage keeps its heading, page or second, so a result opens at the right place, and carries the course's states and the lesson's language. Rebuilds keep unchanged passages (matched by content hash) and their embeddings.
6. **Hybrid search.** Keyword search is PostgreSQL full text in the passage's language (English or Spanish stemming, a stored `tsvector` with a GIN index). Meaning search compares Titan embeddings with pgvector (`vector(1024)`, HNSW, cosine; iterative scans on pgvector 0.8 so filters don't starve results). The two lists are mixed with reciprocal rank fusion (k = 60). Only live courses, not deleted, in the person's states and language are searched. Without Titan keys, search is keyword only; `reos:knowledge:embed` (hourly) embeds anything still missing once Titan is on.
7. **Only course content is embedded or sent to Mux**, never personal data.
8. **Review before live (A5.3b, screen 63).** `ContentChecks` runs on a course's passages: missing approval numbers and legal flags (`LegalRule`: guarantees, pass-rate claims, risk-free, approval claims not on record, unprovable superlatives, in English and Spanish, after TREC's advertising and education rules). An open flag blocks Live. A reviewer can clear a line that's fine in context; the clearance is tied to the passage's content hash, so an edit flags it again.
9. **Learners open a lesson at its place.** Search results carry the page, second or heading; the web lesson page and the phone lesson screen start the video there (Mux with a short-lived signed token, or the original file), show the page or heading first, and keep the transcript in step.

## Consequences

- Workers must listen on the `content` queue; S3 buckets need CORS for PUT from REOS's domains; pgvector must be installed by the database owner (Aurora parameter group in the AWS sprint, `docker/postgres/init.sql` locally, CI creates it in `template1`).
- Mux writes captions in the spoken language only. Spanish captions for English videos (and the reverse) need a translation step; it arrives with AI course drafting (Sprint 7).
- Scanned PDFs (images of text) aren't read yet; Textract arrives in B10.3.
- Access by purchase or sponsored seat joins the learner filters with B5.1. The full course player (check-ins, progress, speed, downloads) is Sprint 8.
