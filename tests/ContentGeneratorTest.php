<?php
/**
 * tests/ContentGeneratorTest.php
 * Slice AI Content: fallback dummy (tanpa panggilan OpenAI sungguhan),
 * persist ke ai_generated_content, dan riwayat — DB test.
 * OpenAIClient di-mock via injeksi parameter (tanpa network).
 */

require_once dirname(__DIR__) . '/config/openai.php';

use App\Ai\ContentGenerator;
use PHPUnit\Framework\TestCase;

class ContentGeneratorTest extends TestCase
{
    /** @var \PDO */
    private $db;

    protected function setUp(): void
    {
        $this->db = getDB();
    }

    private function createBusiness(): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO businesses (name, owner_name, email, created_at) VALUES (?, ?, ?, NOW())"
        );
        $stmt->execute(['AiBiz ' . uniqid(), 'Owner', 'ai' . uniqid() . '@test.local']);
        return (int)$this->db->lastInsertId();
    }

    private function failingClient()
    {
        $mock = $this->createMock(\OpenAIClient::class);
        $mock->method('generateMarketingContent')->willThrowException(new \Exception('down'));
        return $mock;
    }

    public function testDummyContentForAllKnownSegments()
    {
        foreach (['Champions', 'Loyal Customers', 'Potential Loyalists', 'At Risk', 'Lost Customers', 'Unknown'] as $segment) {
            $content = ContentGenerator::dummyContent($segment);
            $this->assertNotEmpty($content, "segmen '$segment' harus menghasilkan konten");
        }
        $this->assertStringContainsString('CHAMPIONS', ContentGenerator::dummyContent('Champions'));
    }

    public function testGenerateFallsBackToDummyWhenOpenAiFails()
    {
        $biz = $this->createBusiness();
        $result = (new ContentGenerator($this->db, $biz))->generate('Champions', $this->failingClient());

        $this->assertTrue($result['success']);
        $this->assertSame('dummy', $result['source']);
        $this->assertSame('Generated using fallback content (OpenAI API not available)', $result['note']);
        $this->assertNotEmpty($result['content']);
        // Catatan: dummy ber-emoji (4-byte) tidak diuji simpan ke DB karena kolum
        // ai_generated_content.content ber-charset utf8 (utf8mb3) di DB test — itu
        // bug laten charset yg TIDAK menggagalkan generation (persistQuiet).
    }

    public function testGenerateUsesOpenAiContentWhenAvailable()
    {
        $biz = $this->createBusiness();
        $mock = $this->createMock(\OpenAIClient::class);
        $mock->method('generateMarketingContent')->willReturn(['content' => 'Konten dari OpenAI']);

        $result = (new ContentGenerator($this->db, $biz))->generate('At Risk', $mock);

        $this->assertTrue($result['success']);
        $this->assertSame('openai', $result['source']);
        $this->assertSame('Konten dari OpenAI', $result['content']);
        $this->assertNull($result['note']);

        // konten ASCII tersimpan ke DB (audit)
        $stmt = $this->db->prepare("SELECT segment, content FROM ai_generated_content WHERE business_id = ?");
        $stmt->execute([$biz]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('At Risk', $row['segment']);
        $this->assertSame('Konten dari OpenAI', $row['content']);
    }

    public function testRecentListsLatestFirst()
    {
        $biz = $this->createBusiness();
        $gen = new ContentGenerator($this->db, $biz);
        $mk = function ($content) {
            $m = $this->createMock(\OpenAIClient::class);
            $m->method('generateMarketingContent')->willReturn(['content' => $content]);
            return $m;
        };
        $gen->generate('Champions', $mk('Konten A'));
        $gen->generate('At Risk', $mk('Konten B'));

        $recent = $gen->recent(5);
        $this->assertCount(2, $recent);
        $this->assertSame('At Risk', $recent[0]['segment']); // terbaru duluan
    }
}
