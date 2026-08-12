# Roadmap

PocketRAG follows [Semantic Versioning](https://semver.org) and a git-flow workflow
(`main` stable, `develop` integration, `feature/*`, `release/*`, `hotfix/*`).
This file tracks planned work by milestone. See [issues](../../issues)
for the granular task lists. Suggestions are welcome via new issues.

> Status legend: ✅ shipped · 🚧 in progress · 📋 planned

## v0.1.0 — Baseline MVP ✅

Shipped: hybrid search (BM25 + cosine, 0.7/0.3), SQLite vector store (`pack('f*')`),
conversational memory (query reformulation), graceful BM25 fallback, auto-sync freshness
gate, multi-provider LLM (Groq/Ollama/OpenAI), Gemini key rotation, SQLite rate limiter,
telemetry, zero-dependency web UI, zero-composer guard in CI.

Tagged: `v0.1.0`.

## v0.2.0 — Hardening, Tests & CI 📋

- Harden rate limiter against X-Forwarded-For spoofing (trusted proxies).
- Test suite to 1:1 lib→test coverage (retrieval, sync, embeddings, db, http, prompt,
  rate_limit, telemetry, llm, groq, ollama, openai).
- CI runs on `develop`; PHP matrix 8.1/8.2/8.3.
- Sync README and docs with current code.

## v0.3.0 — Retrieval v2 📋

- Reciprocal Rank Fusion (replace linear 0.7/0.3 blend); keep linear as config fallback.
- Structure-aware chunking (respect headings and code fences); configurable chunk sizes.
- Metadata / multi-tenancy pre-filtering (tags, slug) via SQL.

## v0.4.0 — Ops & Observability 📋

- Telemetry read endpoint + periodic pruning of `rag_telemetry`.
- Secure sync webhook triggered from GitHub Actions.
- Tech-debt batch: dead `prompt_build` params, consistent mock-response, embeddings 5xx
  retry, message length validation.

## v0.5.0 — Advanced RAG 📋

- Source citations with file/heading/line traceability (API + UI).
- Multi-query / HyDE expansion with query-embedding cache.
- Vector quantization (Int8/Float16) to shrink SQLite BLOBs.
- Conversation-driven self-learning into `community_notes`.

## Non-Goals

- No Composer, `vendor/`, or third-party PHP packages (hard product constraint).
- No sessions, auth, CSRF, or persistent server-side conversation state (stateless JSON API).
- No dedicated vector DB / Python runtime — must run on shared PHP hosting.