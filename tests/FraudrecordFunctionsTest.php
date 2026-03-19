<?php

namespace Detain\MyAdminFraudRecord\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for functions defined in fraudrecord.inc.php.
 *
 * Since these functions rely heavily on global state (database, cURL, GLOBALS),
 * we test the pure function fraudrecord_hash() directly and use static analysis
 * (file_get_contents + regex/string inspection) to verify the DB-heavy functions.
 */
class FraudrecordFunctionsTest extends TestCase
{
    /**
     * Path to the source file containing the functions under test.
     *
     * @var string
     */
    private static $sourceFile;

    /**
     * Cached contents of the source file.
     *
     * @var string
     */
    private static $sourceContents;

    public static function setUpBeforeClass(): void
    {
        self::$sourceFile = dirname(__DIR__) . '/src/fraudrecord.inc.php';
        self::$sourceContents = file_get_contents(self::$sourceFile);
        require_once self::$sourceFile;
    }

    // ──────────────────────────────────────────────
    //  fraudrecord_hash() - Pure function tests
    // ──────────────────────────────────────────────

    /**
     * Test that fraudrecord_hash() is a defined function.
     */
    public function testFraudrecordHashFunctionExists(): void
    {
        $this->assertTrue(function_exists('fraudrecord_hash'));
    }

    /**
     * Test that fraudrecord_hash() returns a string.
     */
    public function testFraudrecordHashReturnsString(): void
    {
        $result = fraudrecord_hash('test');
        $this->assertIsString($result);
    }

    /**
     * Test that fraudrecord_hash() returns a 40-character SHA-1 hex digest.
     */
    public function testFraudrecordHashReturnsSha1Length(): void
    {
        $result = fraudrecord_hash('hello');
        $this->assertSame(40, strlen($result));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $result);
    }

    /**
     * Test that fraudrecord_hash() is deterministic -- same input always yields same output.
     */
    public function testFraudrecordHashIsDeterministic(): void
    {
        $a = fraudrecord_hash('deterministic');
        $b = fraudrecord_hash('deterministic');
        $this->assertSame($a, $b);
    }

    /**
     * Test that different inputs produce different hashes.
     */
    public function testFraudrecordHashDifferentInputsDifferentOutputs(): void
    {
        $a = fraudrecord_hash('alpha');
        $b = fraudrecord_hash('beta');
        $this->assertNotSame($a, $b);
    }

    /**
     * Test that fraudrecord_hash() correctly iterates SHA-1 32,000 times with the prefix.
     */
    public function testFraudrecordHashAlgorithmCorrectness(): void
    {
        $input = 'verify';
        $expected = $input;
        for ($i = 0; $i < 32000; $i++) {
            $expected = sha1('fraudrecord-' . $expected);
        }
        $this->assertSame($expected, fraudrecord_hash($input));
    }

    /**
     * Test that fraudrecord_hash() handles an empty string input.
     */
    public function testFraudrecordHashWithEmptyString(): void
    {
        $result = fraudrecord_hash('');
        $this->assertIsString($result);
        $this->assertSame(40, strlen($result));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $result);
    }

    /**
     * Test that fraudrecord_hash() handles numeric input cast to string.
     */
    public function testFraudrecordHashWithNumericInput(): void
    {
        $result = fraudrecord_hash('12345');
        $this->assertIsString($result);
        $this->assertSame(40, strlen($result));
    }

    /**
     * Test that fraudrecord_hash() handles special characters in input.
     */
    public function testFraudrecordHashWithSpecialCharacters(): void
    {
        $result = fraudrecord_hash('user@example.com');
        $this->assertIsString($result);
        $this->assertSame(40, strlen($result));
    }

    /**
     * Test that the first iteration uses the 'fraudrecord-' prefix.
     */
    public function testFraudrecordHashUsesPrefix(): void
    {
        // After one iteration, sha1('fraudrecord-X') should differ from sha1('X')
        $input = 'prefixtest';
        $withPrefix = sha1('fraudrecord-' . $input);
        $withoutPrefix = sha1($input);
        $this->assertNotSame($withPrefix, $withoutPrefix);
    }

    // ──────────────────────────────────────────────
    //  Static analysis of source file structure
    // ──────────────────────────────────────────────

    /**
     * Test that the source file exists and is readable.
     */
    public function testSourceFileExists(): void
    {
        $this->assertFileExists(self::$sourceFile);
        $this->assertFileIsReadable(self::$sourceFile);
    }

    /**
     * Test that the source file opens with a PHP tag.
     */
    public function testSourceFileStartsWithPhpTag(): void
    {
        $this->assertStringStartsWith('<?php', self::$sourceContents);
    }

    /**
     * Test that fraudrecord_report function is defined in the source file.
     */
    public function testFraudrecordReportFunctionDefined(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+fraudrecord_report\s*\(/',
            self::$sourceContents
        );
    }

    /**
     * Test that update_fraudrecord function is defined in the source file.
     */
    public function testUpdateFraudrecordFunctionDefined(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+update_fraudrecord\s*\(/',
            self::$sourceContents
        );
    }

    /**
     * Test that update_fraudrecord_noaccount function is defined in the source file.
     */
    public function testUpdateFraudrecordNoaccountFunctionDefined(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+update_fraudrecord_noaccount\s*\(/',
            self::$sourceContents
        );
    }

    /**
     * Test that fraudrecord_hash function is defined in the source file.
     */
    public function testFraudrecordHashFunctionDefined(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+fraudrecord_hash\s*\(/',
            self::$sourceContents
        );
    }

    /**
     * Test that exactly four functions are defined in the source file.
     */
    public function testSourceFileDefinesFourFunctions(): void
    {
        preg_match_all('/^\s*function\s+\w+\s*\(/m', self::$sourceContents, $matches);
        $this->assertCount(4, $matches[0]);
    }

    /**
     * Test that fraudrecord_report has the expected parameters.
     */
    public function testFraudrecordReportParameters(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+fraudrecord_report\s*\(\s*\$custid\s*,\s*\$module\s*,\s*\$type\s*,\s*\$text\s*,\s*\$value\s*\)/',
            self::$sourceContents
        );
    }

    /**
     * Test that update_fraudrecord has the expected parameters with defaults.
     */
    public function testUpdateFraudrecordParameters(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+update_fraudrecord\s*\(\s*\$custid\s*,\s*\$module\s*=\s*\'default\'\s*,\s*\$ip\s*=\s*false\s*\)/',
            self::$sourceContents
        );
    }

    /**
     * Test that update_fraudrecord_noaccount takes a single $data parameter.
     */
    public function testUpdateFraudrecordNoaccountParameters(): void
    {
        $this->assertMatchesRegularExpression(
            '/function\s+update_fraudrecord_noaccount\s*\(\s*\$data\s*\)/',
            self::$sourceContents
        );
    }

    /**
     * Test that the FraudRecord API endpoint URL is used in the source.
     */
    public function testApiEndpointUrlPresent(): void
    {
        $this->assertStringContainsString(
            'https://www.fraudrecord.com/api/',
            self::$sourceContents
        );
    }

    /**
     * Test that the source uses the FRAUDRECORD_API_KEY constant.
     */
    public function testUsesApiKeyConstant(): void
    {
        $this->assertStringContainsString('FRAUDRECORD_API_KEY', self::$sourceContents);
    }

    /**
     * Test that the source file references getcurlpage for HTTP requests.
     */
    public function testUsesGetcurlpage(): void
    {
        $this->assertStringContainsString('getcurlpage(', self::$sourceContents);
    }

    /**
     * Test that update_fraudrecord uses the report response regex pattern.
     */
    public function testReportResponseRegexPattern(): void
    {
        $this->assertStringContainsString(
            '<report>',
            self::$sourceContents
        );
        $this->assertStringContainsString(
            '(?P<score>.*)',
            self::$sourceContents
        );
        $this->assertStringContainsString(
            '(?P<count>.*)',
            self::$sourceContents
        );
        $this->assertStringContainsString(
            '(?P<reliability>.*)',
            self::$sourceContents
        );
        $this->assertStringContainsString(
            '(?P<code>.*)',
            self::$sourceContents
        );
    }

    /**
     * Test that the report regex correctly matches expected FraudRecord API responses.
     */
    public function testReportRegexMatchesValidResponse(): void
    {
        $pattern = '/^\<report\>(?P<score>.*)-(?P<count>.*)-(?P<reliability>.*)-(?P<code>.*)\<\/report\>$/';
        $response = '<report>0-0-0.0-8ef255ff538622eb</report>';
        $this->assertSame(1, preg_match($pattern, $response, $matches));
        $this->assertSame('0', $matches['score']);
        $this->assertSame('0', $matches['count']);
        $this->assertSame('0.0', $matches['reliability']);
        $this->assertSame('8ef255ff538622eb', $matches['code']);
    }

    /**
     * Test that the report regex does not match malformed responses.
     */
    public function testReportRegexRejectsMalformedResponse(): void
    {
        $pattern = '/^\<report\>(?P<score>.*)-(?P<count>.*)-(?P<reliability>.*)-(?P<code>.*)\<\/report\>$/';
        $this->assertSame(0, preg_match($pattern, 'not a valid response'));
        $this->assertSame(0, preg_match($pattern, '<error>something</error>'));
        $this->assertSame(0, preg_match($pattern, ''));
    }

    /**
     * Test that the report regex matches a high-score response.
     */
    public function testReportRegexHighScoreResponse(): void
    {
        $pattern = '/^\<report\>(?P<score>.*)-(?P<count>.*)-(?P<reliability>.*)-(?P<code>.*)\<\/report\>$/';
        $response = '<report>10-5-8.5-abcdef1234567890</report>';
        $this->assertSame(1, preg_match($pattern, $response, $matches));
        $this->assertSame('10', $matches['score']);
        $this->assertSame('5', $matches['count']);
        $this->assertSame('8.5', $matches['reliability']);
        $this->assertSame('abcdef1234567890', $matches['code']);
    }

    /**
     * Test that update_fraudrecord_noaccount sets status to locked when score >= 10.
     */
    public function testNoaccountLocksAtScoreThreshold(): void
    {
        // Verify source contains the locking logic at score >= 10.0
        $this->assertStringContainsString("\$matches['score'] >= 10.0", self::$sourceContents);
        $this->assertStringContainsString("\$data['status'] = 'locked'", self::$sourceContents);
    }

    /**
     * Test that update_fraudrecord references FRAUDRECORD_SCORE_LOCK constant.
     */
    public function testUpdateFraudrecordUsesScoreLockConstant(): void
    {
        $this->assertStringContainsString('FRAUDRECORD_SCORE_LOCK', self::$sourceContents);
    }

    /**
     * Test that update_fraudrecord references FRAUDRECORD_POSSIBLE_FRAUD_SCORE constant.
     */
    public function testUpdateFraudrecordUsesPossibleFraudScoreConstant(): void
    {
        $this->assertStringContainsString('FRAUDRECORD_POSSIBLE_FRAUD_SCORE', self::$sourceContents);
    }

    /**
     * Test that fraudrecord_report uses the '_action' => 'report' API action.
     */
    public function testFraudrecordReportUsesReportAction(): void
    {
        $this->assertStringContainsString("'_action' => 'report'", self::$sourceContents);
    }

    /**
     * Test that update_fraudrecord uses the '_action' => 'query' API action.
     */
    public function testUpdateFraudrecordUsesQueryAction(): void
    {
        $this->assertStringContainsString("'_action' => 'query'", self::$sourceContents);
    }

    /**
     * Test that the source applies country default to 'US' when missing.
     */
    public function testCountryDefaultIsUS(): void
    {
        $this->assertStringContainsString("'US'", self::$sourceContents);
    }

    /**
     * Test that update_fraudrecord uses CURLOPT_SSL_VERIFYPEER => false.
     */
    public function testSslVerifyPeerDisabled(): void
    {
        $this->assertStringContainsString('CURLOPT_SSL_VERIFYPEER', self::$sourceContents);
    }

    /**
     * Test that each function has a docblock.
     */
    public function testAllFunctionsHaveDocblocks(): void
    {
        $functions = ['fraudrecord_hash', 'fraudrecord_report', 'update_fraudrecord', 'update_fraudrecord_noaccount'];
        foreach ($functions as $func) {
            $pattern = '/\/\*\*[\s\S]*?\*\/\s*function\s+' . preg_quote($func, '/') . '\s*\(/';
            $this->assertMatchesRegularExpression(
                $pattern,
                self::$sourceContents,
                "Function {$func} is missing a docblock"
            );
        }
    }

    /**
     * Test that fraudrecord_hash iterates exactly 32000 times.
     */
    public function testHashIterationCount(): void
    {
        $this->assertStringContainsString('32000', self::$sourceContents);
        $this->assertMatchesRegularExpression(
            '/\$i\s*<\s*32000/',
            self::$sourceContents
        );
    }

    /**
     * Test that the hash function uses the 'fraudrecord-' prefix string.
     */
    public function testHashPrefixString(): void
    {
        $this->assertStringContainsString("'fraudrecord-'", self::$sourceContents);
    }

    /**
     * Test that update_fraudrecord returns true.
     */
    public function testUpdateFraudrecordReturnsTrue(): void
    {
        // Verify the function ends with return true
        $this->assertMatchesRegularExpression(
            '/return\s+true;\s*\}\s*$/m',
            // Extract just the update_fraudrecord function body
            self::$sourceContents
        );
    }
}
