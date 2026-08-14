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

## v0.2.0 — Hardening, Tests & CI ✅

Shipped: rate limiter hardened against X-Forwarded-For spoofing (trusted proxies);
1:1 lib→test coverage; CI on `develop` with PHP 8.1/8.2/8.3 matrix; README and docs
aligned with current code.

Tagged: `v0.2.0`.

## v0.3.0 — Retrieval v2 ✅

Shipped: Reciprocal Rank Fusion (linear 0.7/0.3 kept as config fallback);
structure-aware chunking (headings and code fences) with configurable chunk sizes;
metadata / multi-tenancy pre-filtering (tags, slug) via SQL.

Tagged: `v0.3.0`.

## v0.4.0 — Ops & Observability ✅

Shipped: telemetry read endpoint + periodic pruning of `rag_telemetry`;
secure sync webhook triggered from GitHub Actions; tech-debt batch (dead
`prompt_build` params, consistent mock-response, embeddings 5xx retry,
message length validation).

Tagged: `v0.4.0`.

## v0.5.0 — Advanced RAG 📋

- Source citations with file/heading/line traceability (API + UI).
- Multi-query / HyDE expansion with query-embedding cache.
- Vector quantization (Int8/Float16) to shrink SQLite BLOBs.
- Conversation-driven self-learning into `community_notes`.

## Non-Goals

- No Composer, `vendor/`, or third-party PHP packages (hard product constraint).
- No sessions, auth, CSRF, or persistent server-side conversation state (stateless JSON API).
- No dedicated vector DB / Python runtime — must run on shared PHP hosting.