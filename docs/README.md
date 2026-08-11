# PocketRAG documentation

Human-facing reference for the PocketRAG hybrid RAG engine. Product overview and quick start live in the root [`README.md`](../README.md). Agent working rules live in [`AGENTS.md`](../AGENTS.md).

| Document | Contents |
|---|---|
| [Architecture](architecture.md) | Goals, constraints, high-level design, request flow |
| [Components](components.md) | Modules under `lib/`, CLI, and entrypoint responsibilities |
| [How it works](how-it-works.md) | End-to-end pipeline: reformulation → retrieval → generation |
| [API](api.md) | HTTP contract for `index.php` |
| [Knowledge base](knowledge.md) | Markdown layout, frontmatter, chunking rules |
| [Data model](data-model.md) | SQLite schema, vector packing, telemetry |
| [Configuration](configuration.md) | `config.php` keys and runtime defaults |
| [Operations](ops.md) | Local run, sync, verification, telemetry |
| [Deploy](deploy.md) | Shared hosting / PHP drop-in deployment |
| [Contributing](contributing.md) | Conventions, zero-deps policy, PR expectations |

Planning artifacts (when present) live under `_bmad-output/` and are not part of this tree. Do not treat `foundation/` as product documentation.
