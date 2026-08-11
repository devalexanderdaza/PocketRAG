<?php
/**
 * Query Expansion dictionary for Spanish and English.
 */

declare(strict_types=1);

require_once __DIR__ . '/bm25.php';

function synonyms_map(): array
{
    return [
        'experiencia'  => 'profile experience Nivelics Cambio Validation CV',
        'habilidades'  => 'skills stack technologies CrewAI Mastra NestJS RAG',
        'tecnologias'  => 'stack architecture NestJS TypeScript Python Docker',
        'perfil'       => 'profile Alexander Daza engineer AI full stack',
        'contacto'     => 'email WhatsApp Telegram GitHub LinkedIn alexanderdaza',
        'stack'        => 'skills technologies CrewAI Mastra MCP RAG NestJS TypeScript',
        'rag'          => 'retrieval MCP agents embeddings vector knowledge',
    ];
}

function synonyms_phrases(): array
{
    return [
        'quien es'            => 'profile Alexander Daza engineer AI full stack',
        'quien es alexander'  => 'profile Alexander Daza engineer AI full stack',
        'que hace'            => 'profile engineer builds AI full stack LLM agents',
        'experiencia laboral' => 'experience Nivelics Cambio employer works',
    ];
}

function synonyms_expand(string $query): string
{
    $expanded = [];
    $normalized = trim((string) preg_replace('/[^a-z0-9 ]+/', ' ', bm25_fold($query)));
    $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

    foreach (synonyms_phrases() as $phrase => $terms) {
        if ($normalized !== '' && str_contains($normalized, $phrase)) {
            $expanded[] = $terms;
        }
    }

    $map  = synonyms_map();
    $seen = [];

    foreach (bm25_tokenize($query) as $term) {
        if (!isset($map[$term]) || isset($seen[$term])) {
            continue;
        }
        $seen[$term] = true;
        $expanded[]  = $map[$term];
    }

    return $expanded === [] ? $query : $query . ' ' . implode(' ', $expanded);
}
