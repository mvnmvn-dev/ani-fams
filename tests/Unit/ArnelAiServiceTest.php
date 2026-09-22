<?php

namespace Tests\Unit;

use App\Services\ArnelAiService;
use Tests\TestCase;

class ArnelAiServiceTest extends TestCase
{
    public function test_document_analysis_has_deterministic_fallback_without_api_key(): void
    {
        config(['services.arnel.api_key' => null]);
        putenv('OPENAI_API_KEY=');

        $result = app(ArnelAiService::class)->analyzeDocument(
            'Invoice INV-2026-001 dated 2026-09-22 for office supplies.',
            'invoice.txt',
            'September invoice'
        );

        $this->assertSame('Invoice', $result['classification']);
        $this->assertStringContainsString('September invoice', $result['summary']);
        $this->assertContains('invoice', $result['keywords']);
        $this->assertSame('INV-2026-001', $result['metadata']['reference_number']);
        $this->assertGreaterThan(0, $result['confidence']);
    }

    public function test_chat_fallback_returns_deep_linked_source(): void
    {
        config(['services.arnel.api_key' => null]);
        putenv('OPENAI_API_KEY=');

        $result = app(ArnelAiService::class)->assistantReply('Which contracts expire?', [
            'active_contracts' => 2,
        ]);

        $this->assertSame(['/contracts'], $result['sources']);
        $this->assertStringContainsString('2 active contract', $result['answer']);
    }
}
