<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ArnelAiService
{
    public function isConfigured(): bool
    {
        return (bool) (config('services.arnel.api_key') ?: env('OPENAI_API_KEY'));
    }

    public function cachedDashboardInsights(array $stats, array $charts, array $attentionItems): array
    {
        return Cache::remember('arnel:dashboard:insights:' . sha1(json_encode([$stats, $charts, $attentionItems])), now()->addMinutes(15), fn () => $this->dashboardInsights($stats, $charts, $attentionItems));
    }

    public function cachedContractInsights(array $contracts): array
    {
        return Cache::remember('arnel:contracts:insights:' . sha1(json_encode($contracts)), now()->addMinutes(15), fn () => $this->contractInsights($contracts));
    }

    public function cachedLegalCaseInsights(array $cases): array
    {
        return Cache::remember('arnel:legal:insights:' . sha1(json_encode($cases)), now()->addMinutes(15), fn () => $this->legalCaseInsights($cases));
    }

    public function cachedRetentionInsights(array $assignments, array $documents = []): array
    {
        return Cache::remember('arnel:retention:insights:' . sha1(json_encode([$assignments, $documents])), now()->addMinutes(15), fn () => $this->retentionInsights($assignments, $documents));
    }

    public function cachedVisitorInsights(array $visits): array
    {
        return Cache::remember('arnel:visitors:insights:' . sha1(json_encode($visits)), now()->addMinutes(15), fn () => $this->visitorInsights($visits));
    }

    /** @return array{summary:string,notifications:list<array>,insights:list<string>} */
    public function dashboardInsights(array $stats, array $charts, array $attentionItems): array
    {
        $fallback = [
            'summary' => sprintf(
                'Today there are %d pending reservations, %d active contracts, and %d open legal cases. %d item%s require attention.',
                $stats['pending_reservations'] ?? 0,
                $stats['active_contracts'] ?? 0,
                $stats['active_legal_cases'] ?? 0,
                count($attentionItems),
                count($attentionItems) === 1 ? '' : 's'
            ),
            'notifications' => array_map(static fn (array $item) => [
                'title' => ($item['module'] ?? 'Module') . ': ' . ($item['title'] ?? 'Action required'),
                'detail' => $item['meta'] ?? ($item['status'] ?? 'Review this item.'),
                'severity' => in_array($item['tone'] ?? '', ['red', 'amber', 'yellow'], true) ? 'high' : 'normal',
                'href' => $item['href'] ?? null,
            ], array_slice($attentionItems, 0, 6)),
            'insights' => $this->fallbackInsights($stats, $charts),
        ];

        $prompt = "Create an administrative dashboard brief from this JSON. Return concise, factual output only.\n" . json_encode([
            'stats' => $stats,
            'charts' => $charts,
            'attention_items' => array_slice($attentionItems, 0, 8),
        ], JSON_UNESCAPED_SLASHES);

        $result = $this->jsonRequest(
            'You are ARNEL AI, an administrative records assistant. Never invent numbers or actions. Use only the supplied data.',
            $prompt,
            [
                'summary' => ['type' => 'string'],
                'notifications' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string'], 'detail' => ['type' => 'string'], 'severity' => ['type' => 'string'], 'href' => ['type' => ['string', 'null']],
                ], 'required' => ['title', 'detail', 'severity', 'href'], 'additionalProperties' => false]],
                'insights' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'dashboard_insights'
        );

        return is_array($result) ? array_replace($fallback, $result) : $fallback;
    }

    /** @return array{classification:string,summary:string,keywords:list<string>,metadata:array,confidence:float}|null */
    public function analyzeDocument(string $text, string $filename, string $title, string $description = ''): ?array
    {
        $text = trim(Str::limit($text, 30000, ''));
        if ($text === '' && $title === '' && $description === '') {
            return null;
        }

        $cleanText = trim((string) preg_replace('/\s+/', ' ', $text));
        $fallbackSummary = trim($description);
        if ($fallbackSummary === '' && $cleanText !== '') $fallbackSummary = 'This ' . strtolower($this->classifyFromText($title . ' ' . $filename . ' ' . $description . ' ' . $text)) . ' titled “' . ($title ?: pathinfo($filename, PATHINFO_FILENAME)) . '” contains: ' . Str::limit($cleanText, 360, '…');
        if ($fallbackSummary === '') $fallbackSummary = 'Document “' . ($title ?: pathinfo($filename, PATHINFO_FILENAME)) . '” was uploaded to the administrative archive. Additional source text is needed for a fuller summary.';
        $fallback = [
            'classification' => $this->classifyFromText($title . ' ' . $filename . ' ' . $description . ' ' . $text),
            'summary' => $fallbackSummary,
            'keywords' => $this->keywordsFromText($title . ' ' . $filename . ' ' . $text),
            'metadata' => [
                'document_title' => $title ?: pathinfo($filename, PATHINFO_FILENAME),
                'reference_number' => $this->extractPattern($text, '/\b(?:REF|PR|PO|INV|DOC|CNTR)[-\s]?\d[\w-]*\b/i'),
                'date' => $this->extractPattern($text, '/\b\d{4}[-\/]\d{2}[-\/]\d{2}\b/'),
                'supplier_or_organization' => null,
                'department' => null,
            ],
            'confidence' => $text !== '' ? 0.62 : 0.35,
        ];

        $result = $this->jsonRequest(
            'You are ARNEL AI for document management. Extract only evidence present in the supplied document. Use null for unknown metadata and concise language.',
            "Filename: {$filename}\nTitle: {$title}\nDescription: {$description}\nDocument text:\n{$text}",
            [
                'classification' => ['type' => 'string'], 'summary' => ['type' => 'string'],
                'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                'metadata' => ['type' => 'object', 'properties' => [
                    'document_title' => ['type' => ['string', 'null']], 'department' => ['type' => ['string', 'null']], 'date' => ['type' => ['string', 'null']],
                    'reference_number' => ['type' => ['string', 'null']], 'supplier_or_organization' => ['type' => ['string', 'null']], 'document_type' => ['type' => ['string', 'null']],
                ], 'required' => ['document_title', 'department', 'date', 'reference_number', 'supplier_or_organization', 'document_type'], 'additionalProperties' => false],
                'confidence' => ['type' => 'number'],
            ],
            'document_analysis'
        );

        if (is_array($result)) {
            $merged = array_replace_recursive($fallback, $result);
            if (trim((string) ($merged['summary'] ?? '')) === '') $merged['summary'] = $fallbackSummary;
            if (empty($merged['keywords'])) $merged['keywords'] = $fallback['keywords'];
            return $merged;
        }
        return $fallback;
    }

    public function contractInsights(array $contracts): array
    {
        $expiring = array_values(array_filter($contracts, static function (array $contract): bool {
            if (empty($contract['end_date']) || ! in_array($contract['status'] ?? '', ['active', 'renewed'], true)) return false;
            $days = (int) floor((strtotime($contract['end_date']) - time()) / 86400);
            return $days >= 0 && $days <= 90;
        }));
        usort($expiring, static fn ($a, $b) => strtotime($a['end_date']) <=> strtotime($b['end_date']));
        $fallback = ['summary' => count($expiring) . ' active contract(s) expire within the next 90 days.', 'expiring' => array_map(static fn ($c) => ['title' => $c['title'] ?? 'Contract', 'counterparty' => $c['counterparty_name'] ?? 'Unknown party', 'end_date' => $c['end_date'], 'days' => max(0, (int) floor((strtotime($c['end_date']) - time()) / 86400)), 'priority' => ((int) floor((strtotime($c['end_date']) - time()) / 86400)) <= 7 ? 'high' : (((int) floor((strtotime($c['end_date']) - time()) / 86400)) <= 30 ? 'medium' : 'informational')], array_slice($expiring, 0, 8))];
        $result = $this->jsonRequest('You are ARNEL AI monitoring contracts. Never invent dates or values.', 'Summarize these contracts and flag renewals using only this JSON: ' . json_encode(array_slice($contracts, 0, 40)), ['summary' => ['type' => 'string'], 'expiring' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['title' => ['type' => 'string'], 'counterparty' => ['type' => 'string'], 'end_date' => ['type' => 'string'], 'days' => ['type' => 'integer'], 'priority' => ['type' => 'string']], 'required' => ['title', 'counterparty', 'end_date', 'days', 'priority'], 'additionalProperties' => false]]], 'contract_insights');
        return is_array($result) ? array_replace($fallback, $result) : $fallback;
    }

    public function legalCaseInsights(array $cases): array
    {
        $active = array_values(array_filter($cases, static fn ($c) => in_array($c['status'] ?? '', ['open', 'in_progress', 'on_hold'], true)));
        $caseNames = array_values(array_filter(array_map(static fn ($c) => trim((string) ($c['title'] ?? $c['case_number'] ?? '')), $active)));
        $fallback = ['summary' => count($active) . ' active legal matter(s) are being monitored.' . (count($caseNames) ? ' Current matter(s): ' . implode(', ', array_slice($caseNames, 0, 4)) . '.' : ''), 'deadlines' => [], 'related_documents' => []];
        foreach ($active as $case) {
            foreach (['next_deadline' => 'Deadline', 'hearing_date' => 'Hearing'] as $field => $label) if (! empty($case[$field])) $fallback['deadlines'][] = ['case' => $case['title'] ?? 'Case', 'type' => $label, 'date' => $case[$field], 'priority' => strtotime($case[$field]) <= strtotime('+7 days') ? 'high' : 'medium'];
        }
        $result = $this->jsonRequest('You are ARNEL AI for legal case administration. Use only supplied case facts and do not provide legal advice.', 'Analyze these cases for a concise operational summary, deadlines, and related-document suggestions: ' . json_encode(array_slice($cases, 0, 40)), ['summary' => ['type' => 'string'], 'deadlines' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['case' => ['type' => 'string'], 'type' => ['type' => 'string'], 'date' => ['type' => 'string'], 'priority' => ['type' => 'string']], 'required' => ['case', 'type', 'date', 'priority'], 'additionalProperties' => false]], 'related_documents' => ['type' => 'array', 'items' => ['type' => 'string']]], 'legal_case_insights');
        return is_array($result) ? array_replace($fallback, $result) : $fallback;
    }

    /** Heuristic conflict result is deterministic; the LLM supplies only the human-readable recommendation. */
    public function reservationRecommendation(array $request, bool $conflict, ?string $suggestedFacility): array
    {
        $fallback = ['recommendation' => $conflict ? 'Choose an available facility or adjust the requested time.' : 'Requested facility and schedule have no detected conflict.', 'rationale' => $conflict ? 'An existing pending or approved booking overlaps the requested window.' : 'No overlapping pending or approved booking was found.'];
        $result = $this->jsonRequest('You are ARNEL recommendation AI for facilities scheduling. Do not approve or create reservations. Use only supplied facts.', 'Give a concise scheduling recommendation from this JSON: ' . json_encode(['request' => $request, 'conflict' => $conflict, 'suggested_facility' => $suggestedFacility]), ['recommendation' => ['type' => 'string'], 'rationale' => ['type' => 'string']], 'reservation_recommendation');
        return is_array($result) ? array_replace($fallback, $result) : $fallback;
    }

    public function retentionInsights(array $assignments, array $documents = []): array
    {
        $suggestions = [];
        foreach ($documents as $doc) {
            $text = strtolower(($doc['title'] ?? '') . ' ' . ($doc['ai_classification'] ?? ''));
            $category = str_contains($text, 'invoice') || str_contains($text, 'purchase') ? 'Financial Record' : (str_contains($text, 'contract') ? 'Legal Record' : (str_contains($text, 'memorandum') ? 'Administrative Record' : null));
            if ($category) $suggestions[] = ['document' => $doc['title'] ?? 'Document', 'category' => $category];
        }
        $due = array_values(array_filter($assignments, static fn ($a) => in_array($a['status'] ?? '', ['due_for_review', 'active'], true) && ! empty($a['calculated_disposal_date']) && strtotime($a['calculated_disposal_date']) <= strtotime('+30 days')));
        $fallback = ['summary' => count($due) . ' record(s) are due for archival or retention review within 30 days.', 'suggestions' => array_slice($suggestions, 0, 8), 'archive_candidates' => array_slice(array_map(static fn ($a) => $a['document_title'] ?? 'Record', $due), 0, 8)];
        $result = $this->jsonRequest('You are ARNEL retention AI. Suggest categories and tags, but never authorize disposal. Use only supplied document facts.', 'Suggest retention categories and tags from this JSON: ' . json_encode(['documents' => array_slice($documents, 0, 30), 'assignments' => array_slice($assignments, 0, 30)]), ['summary' => ['type' => 'string'], 'suggestions' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['document' => ['type' => 'string'], 'category' => ['type' => 'string']], 'required' => ['document', 'category'], 'additionalProperties' => false]], 'archive_candidates' => ['type' => 'array', 'items' => ['type' => 'string']]], 'retention_suggestions');
        return is_array($result) ? array_replace($fallback, $result) : $fallback;
    }

    public function visitorInsights(array $visits): array
    {
        $today = date('Y-m-d'); $todayVisits = array_values(array_filter($visits, static fn ($v) => str_starts_with((string) ($v['scheduled_at'] ?? $v['check_in_time'] ?? $v['created_at'] ?? ''), $today)));
        $counts = [];
        foreach ($visits as $visit) { $name = trim((string) ($visit['visitor_name'] ?? 'Unknown visitor')); $counts[$name] = ($counts[$name] ?? 0) + 1; }
        arsort($counts); $frequent = array_slice($counts, 0, 5, true);
        $overstaying = array_values(array_filter($visits, static function ($v) {
            if (($v['status'] ?? '') !== 'checked_in') return false;
            if (! empty($v['badge_expires_at']) && strtotime($v['badge_expires_at']) < time()) return true;
            $hours = (int) ($v['expected_duration_hours'] ?? 8);
            return ! empty($v['check_in_time']) && strtotime($v['check_in_time']) < strtotime("-{$hours} hours");
        }));
        $fallback = [
            'summary' => count($todayVisits) . ' visitor(s) are recorded for today' . (count($overstaying) ? '; ' . count($overstaying) . ' may be overstaying.' : '.'),
            'frequent_visitors' => array_map(static fn ($name, $count) => ['name' => $name, 'visits' => $count], array_keys($frequent), array_values($frequent)),
            'anomalies' => array_values(array_map(static fn ($name, $count) => ['name' => $name, 'visits' => $count], array_keys(array_filter($counts, static fn ($count) => $count >= 5)), array_values(array_filter($counts, static fn ($count) => $count >= 5)))),
            'overstaying' => array_map(static fn ($v) => ['name' => $v['visitor_name'] ?? 'Visitor', 'check_in_time' => $v['check_in_time'] ?? null], array_slice($overstaying, 0, 8)),
            'peak_hint' => 'Review visitor peak hours on the Visitors dashboard widgets.',
        ];
        return $fallback;
    }

    /** @return array{renewal:string|null,termination:string|null,confidentiality:string|null,payment_terms:string|null} */
    public function extractContractClauses(string $text, string $title = ''): array
    {
        $text = trim(\Illuminate\Support\Str::limit($text, 25000, ''));
        $fallback = [
            'renewal' => $this->clauseSnippet($text, ['renew', 'auto-renew', 'renewal']),
            'termination' => $this->clauseSnippet($text, ['terminat', 'cancel']),
            'confidentiality' => $this->clauseSnippet($text, ['confidential', 'non-disclosure', 'nda']),
            'payment_terms' => $this->clauseSnippet($text, ['payment', 'invoice', 'consideration', 'fee']),
        ];
        if ($text === '') {
            return $fallback;
        }
        $result = $this->jsonRequest(
            'You are ARNEL AI for contract clause extraction. Quote or paraphrase only clauses evidenced in the text. Use null when absent.',
            "Contract title: {$title}\nText:\n{$text}",
            [
                'renewal' => ['type' => ['string', 'null']],
                'termination' => ['type' => ['string', 'null']],
                'confidentiality' => ['type' => ['string', 'null']],
                'payment_terms' => ['type' => ['string', 'null']],
            ],
            'contract_clauses'
        );

        return is_array($result) ? array_replace($fallback, $result) : $fallback;
    }

    private function clauseSnippet(string $text, array $needles): ?string
    {
        if ($text === '') {
            return null;
        }
        $lower = strtolower($text);
        foreach ($needles as $needle) {
            $pos = strpos($lower, $needle);
            if ($pos === false) {
                continue;
            }
            $start = max(0, $pos - 40);
            return trim(\Illuminate\Support\Str::limit(substr($text, $start, 220), 200, '…'));
        }

        return null;
    }

    public function groupNotifications(iterable $notifications): array
    {
        $groups = ['high' => [], 'medium' => [], 'informational' => []];
        foreach ($notifications as $notification) {
            $text = strtolower(($notification->title ?? '') . ' ' . ($notification->body ?? ''));
            $priority = str_contains($text, 'expire') || str_contains($text, 'deadline') || str_contains($text, 'urgent') || str_contains($text, 'required') ? 'high' : (str_contains($text, 'review') || str_contains($text, 'approval') || str_contains($text, 'reminder') ? 'medium' : 'informational');
            $groups[$priority][] = $notification;
        }
        return $groups;
    }

    public function assistantReply(string $question, array $context, array $history = []): array
    {
        $fallback = ['answer' => 'I could not find enough matching ANI-FAMS records to answer that yet.', 'sources' => []];
        $q = strtolower($question);
        if (str_contains($q, 'reservation')) $fallback = ['answer' => sprintf('There are %d pending reservation request(s) in the current accessible snapshot.', $context['pending_reservations'] ?? 0), 'sources' => ['/reservations']];
        elseif (str_contains($q, 'contract') || str_contains($q, 'expire')) $fallback = ['answer' => sprintf('There are %d active contract(s) in the current accessible snapshot. Review the Contracts module for expiration priorities.', $context['active_contracts'] ?? 0), 'sources' => ['/contracts']];
        elseif (str_contains($q, 'document')) $fallback = ['answer' => sprintf('There are %d active document record(s) in the current accessible snapshot.', $context['active_documents'] ?? 0), 'sources' => ['/documents']];
        elseif (str_contains($q, 'legal') || str_contains($q, 'case')) $fallback = ['answer' => sprintf('There are %d active legal case(s) in the current accessible snapshot.', $context['active_legal_cases'] ?? 0), 'sources' => ['/legal']];

        $result = $this->jsonRequest('You are ARNEL AI, an administrative assistant. Answer only from the supplied ANI-FAMS context. Do not invent records, dates, or legal conclusions.', "Recent conversation:\n" . json_encode(array_slice($history, -8), JSON_UNESCAPED_SLASHES) . "\nQuestion: {$question}\nContext: " . json_encode($context, JSON_UNESCAPED_SLASHES), ['answer' => ['type' => 'string'], 'sources' => ['type' => 'array', 'items' => ['type' => 'string']]], 'arnel_chat');
        return is_array($result) ? array_replace($fallback, $result) : $fallback;
    }

    private function jsonRequest(string $system, string $prompt, array $properties, string $schemaName): ?array
    {
        $key = config('services.arnel.api_key') ?: env('OPENAI_API_KEY');
        $base = rtrim(config('services.arnel.api_base') ?: env('OPENAI_API_BASE', 'https://api.openai.com/v1'), '/');
        if (! $key) return null;

        try {
            $response = Http::withToken($key)->timeout(45)->post($base . '/chat/completions', [
                'model' => config('services.arnel.model', env('AI_MODEL', 'gpt-5-mini')),
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => $schemaName, 'strict' => true, 'schema' => ['type' => 'object', 'properties' => $properties, 'required' => array_keys($properties), 'additionalProperties' => false]]],
                'max_completion_tokens' => 1200,
            ]);
            if (! $response->successful()) {
                Log::warning('arnel.request_failed', ['schema' => $schemaName, 'status' => $response->status(), 'body' => Str::limit($response->body(), 500)]);
                return null;
            }
            $content = data_get($response->json(), 'choices.0.message.content');
            $decoded = is_string($content) ? json_decode($content, true) : null;
            return is_array($decoded) ? $decoded : null;
        } catch (\Throwable $e) {
            Log::warning('arnel.request_failed', ['schema' => $schemaName, 'exception' => $e::class, 'message' => $e->getMessage()]);
            return null;
        }
    }

    private function fallbackInsights(array $stats, array $charts): array
    {
        $insights = [];
        foreach (($charts['document_categories'] ?? []) as $name => $count) { $insights[] = "{$name} is the largest document category with {$count} record(s)."; break; }
        $insights[] = sprintf('%d active document(s) are currently visible in the archive.', $stats['active_documents'] ?? 0);
        if (($stats['pending_disposals'] ?? 0) > 0) $insights[] = 'Retention reviews are pending and should be prioritized for compliance.';
        return $insights;
    }

    private function classifyFromText(string $text): string
    {
        foreach (['purchase request' => 'Purchase Request', 'invoice' => 'Invoice', 'memorandum' => 'Memorandum', 'contract' => 'Contract', 'delivery receipt' => 'Delivery Receipt'] as $needle => $label) if (stripos($text, $needle) !== false) return $label;
        return 'Administrative Document';
    }

    private function keywordsFromText(string $text): array
    {
        $words = preg_split('/[^A-Za-z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['the','and','for','with','from','this','that','document','file','request'];
        $counts = array_count_values(array_filter($words, fn ($word) => strlen($word) > 3 && ! in_array($word, $stop, true)));
        arsort($counts);
        return array_slice(array_keys($counts), 0, 8);
    }

    private function extractPattern(string $text, string $pattern): ?string
    {
        return preg_match($pattern, $text, $matches) ? ($matches[0] ?? null) : null;
    }
}
