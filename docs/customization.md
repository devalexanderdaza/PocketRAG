# Customizing PocketRAG

PocketRAG is designed to be highly generic. You can customize the assistant's persona, expansion synonyms, and fallback behavior for your specific domain without modifying any PHP code.

## 1. Assistant Persona

By default, the assistant identifies itself as "PocketRAG Assistant". You can change this by creating a `data/persona.md` file.

The file supports optional YAML frontmatter for metadata, followed by the system instructions (rules) in the body.

**Example `data/persona.md`:**
```markdown
---
name: "TechCorp Support Bot"
description: "Tier 1 technical support assistant for TechCorp products."
---
You are TechCorp Support Bot, a helpful Tier 1 technical support assistant for TechCorp products.

Response Rules:
1. Respond accurately using ONLY the information from the RETRIEVED CONTEXT.
2. If the user asks about billing, instruct them to email billing@techcorp.com.
3. Be exceedingly polite and concise.
4. Do not hallucinate answers if the information is missing.
```

## 2. Query Expansion (Synonyms)

PocketRAG includes a BM25 lexical search component. To improve lexical matching, you can define domain-specific synonyms. When a user searches for a term, the query is expanded to include its synonyms before searching the index.

Create a `data/synonyms.json` file. You can define exact word synonyms and full phrase matches.

**Example `data/synonyms.json`:**
```json
{
  "words": {
    "pwd": "password credentials login",
    "db": "database sql sqlite postgres"
  },
  "phrases": {
    "cant log in": "password credentials login reset auth",
    "out of memory": "oom crash ram memory leak"
  }
}
```
*Note: Keys should be lowercase without accents or special characters.*

## 3. Fallback Documents

When a user's query yields a 0.0 score against all documents (no match), PocketRAG can fall back to a default set of documents to provide general context to the LLM.

You can configure which document slugs should be used as fallbacks in `config.php`:

```php
    // Will attempt to load 'welcome.md', 'about.md', or 'faq.md' (in order)
    'default_fallback_slugs' => ['welcome', 'about', 'faq'],
```
