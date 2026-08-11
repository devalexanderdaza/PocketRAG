---
slug: base
type: system_guidelines
title: PHPoC-VeRAG Base Knowledge System
tags: rag, architecture, hybrid-search, system, developer, php
author: Alexander Daza
github: devalexanderdaza
website: www.alexanderdaza.com
priority: 10
---

# PHPoC-VeRAG: Pure PHP Serverless Hybrid RAG Engine

PHPoC-VeRAG (PHP Proof of Concept - Vector & Retrieval-Augmented Generation) is a lightweight, self-contained hybrid search engine designed to run on traditional PHP environments without requiring heavy frameworks or dedicated vector databases.

## Architecture Features

1. **Lexical Search (BM25):** 
   Utilizes a native Okapi BM25 implementation running directly over Markdown files in the `src/data/knowledge/` directory, integrated with a local English/Spanish synonym expansion dictionary.

2. **Vector Search (SQLite):**
   Computes cosine similarity based on embeddings retrieved from the Gemini API (using the `gemini-embedding-001` model at 768 dimensions). Vectors are compactly stored as binary blobs in SQLite using PHP's native `pack('f*')`.

3. **Hybrid Orchestration (Hybrid Scoring):**
   Merges normalized search scores using the formula:
   `Score = (0.7 * Cosine Similarity) + (0.3 * BM25)`
   If the embedding API times out (2-second threshold) or becomes unavailable, the engine gracefully and transparently downgrades to pure BM25 search.

4. **Automatic Synchronization (Freshness Check):**
   The engine performs a proactive check matching Markdown file modification times (`mtime`) against the SQLite database. If modified or new files are found, it triggers automatic vector synchronization during the next search request.

5. **Conversational RAG (Contextual Memory):**
   Accepts complete chat history and utilizes Groq LLM completions to reformulate follow-up queries into standalone search inputs, optimizing context retrieval for conversational flows.
