<?php

namespace Tests\Unit;

use App\Services\Verification\T00006AudioAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class T00006AudioAnalyzerTest extends TestCase
{
    #[Test]
    public function it_parses_normal_webm_format_duration(): void
    {
        $json = json_encode(['format' => ['duration' => '10.020000']], JSON_THROW_ON_ERROR);

        $this->assertSame(10.02, T00006AudioAnalyzer::parseWebmDuration($json));
    }

    #[Test]
    public function it_rejects_missing_webm_format_duration(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ffprobe_failed');

        T00006AudioAnalyzer::parseWebmDuration('{"format":{"duration":"N/A"}}');
    }

    #[Test]
    public function it_derives_webm_duration_from_packet_end_time(): void
    {
        $json = json_encode([
            'packets' => [
                ['pts_time' => '9.993000', 'duration_time' => '0.002000'],
                ['pts_time' => '10.001000', 'duration_time' => '0.002000'],
                ['pts_time' => '10.003000', 'duration_time' => '0.002000'],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->assertSame(10.005, T00006AudioAnalyzer::parseWebmPacketDuration($json));
    }

    #[Test]
    public function it_rejects_packet_data_without_a_valid_end_time(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ffprobe_failed');

        T00006AudioAnalyzer::parseWebmPacketDuration('{"packets":[{"pts_time":"N/A","duration_time":"N/A"}]}');
    }
}
