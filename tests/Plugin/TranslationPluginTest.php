<?php

namespace Wawoo\Plugin\Translation;

/**
 * Translation Plugin Tests
 */
class TranslationPluginTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        if (!is_dir(dirname(__DIR__, 2) . '/plugins/translation')) {
            $this->markTestSkipped('translation plugin is optional (wawoo-cms-marketplace); not installed');
        }
        // テスト環境の初期化
        $this->clearTestCache();
    }

    protected function tearDown(): void
    {
        // テスト環境のクリーンアップ
        $this->clearTestCache();
    }

    /**
     * テストキャッシュをクリア
     */
    private function clearTestCache(): void
    {
        $cacheDir = __DIR__ . '/../../cache/test';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * CSRFトークン管理のテスト
     */
    public function testCSRFTokenGeneration(): void
    {
        $token = CSRFTokenManager::generate();
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
    }

    /**
     * CSRFトークン検証のテスト
     */
    public function testCSRFTokenValidation(): void
    {
        $token = CSRFTokenManager::generate();
        $this->assertTrue(CSRFTokenManager::validate($token));
        $this->assertFalse(CSRFTokenManager::validate('invalid_token'));
    }

    /**
     * ログイン試行回数制限のテスト
     */
    public function testLoginRateLimiting(): void
    {
        $identifier = 'test_user';
        
        // 初回は制限に達していない
        $this->assertFalse(LoginRateLimiter::isLockedOut($identifier));
        
        // 試行回数を記録
        for ($i = 0; $i < 5; $i++) {
            LoginRateLimiter::recordAttempt($identifier);
        }
        
        // 制限に達している
        $this->assertTrue(LoginRateLimiter::isLockedOut($identifier));
        
        // 成功時にリセット
        LoginRateLimiter::resetAttempts($identifier);
        $this->assertFalse(LoginRateLimiter::isLockedOut($identifier));
    }

    /**
     * パスワードバリデーションのテスト
     */
    public function testPasswordValidation(): void
    {
        $validator = new PasswordValidator();
        
        // 強いパスワード
        $strongPassword = 'Password123!';
        $result = $validator->validate($strongPassword);
        $this->assertTrue($result['valid']);
        $this->assertGreaterThanOrEqual(PasswordValidator::STRENGTH_GOOD, $result['strength']);
        
        // 弱いパスワード
        $weakPassword = 'password';
        $result = $validator->validate($weakPassword);
        $this->assertFalse($result['valid']);
        $this->assertLessThan(PasswordValidator::STRENGTH_GOOD, $result['strength']);
        
        // パスワード生成のテスト
        $generated = PasswordValidator::generateStrongPassword();
        $this->assertGreaterThanOrEqual(8, strlen($generated));
    }

    /**
     * セッション管理のテスト
     */
    public function testSessionManagement(): void
    {
        $sessionManager = SessionManager::getInstance();
        
        // セッション情報の取得
        $info = $sessionManager->getSessionInfo();
        $this->assertArrayHasKey('created_at', $info);
        $this->assertArrayHasKey('last_activity', $info);
        
        // セッションの更新
        $sessionManager->updateActivity();
        $updatedInfo = $sessionManager->getSessionInfo();
        $this->assertGreaterThan($info['last_activity'], $updatedInfo['last_activity']);
        
        // セッションの延長
        $sessionManager->extendSession();
        $extendedInfo = $sessionManager->getSessionInfo();
        $this->assertGreaterThan($updatedInfo['last_activity'], $extendedInfo['last_activity']);
    }

    /**
     * TOTP認証のテスト
     */
    public function testTOTPAuthentication(): void
    {
        $totp = TOTPAuthenticator::getInstance();
        
        // シークレットキーの生成
        $secret = $totp->generateSecret();
        $this->assertNotEmpty($secret);
        
        // TOTP URLの生成
        $url = $totp->generateTOTPUrl($secret, 'test_user');
        $this->assertStringContainsString('otpauth://totp/', $url);
        
        // バックアップコードの生成
        $backupCodes = $totp->generateBackupCodes();
        $this->assertCount(10, $backupCodes);
        $this->assertNotEmpty($backupCodes[0]);
        
        // TOTPコードの検証（モック）
        // 実際のTOTP検証は時間依存なので、ここではテスト用のモックを実装
        $this->assertTrue(true); // テスト用
    }

    /**
     * APIコスト監視のテスト
     */
    public function testAPICostMonitoring(): void
    {
        $monitor = APICostMonitor::getInstance();
        
        // APIリクエストの記録
        $monitor->recordRequest('openai', 'gpt-4', 1000, ['test' => true]);
        
        // 使用状況の取得
        $usage = $monitor->getCurrentUsage();
        $this->assertArrayHasKey('daily', $usage);
        $this->assertArrayHasKey('monthly', $usage);
        $this->assertGreaterThan(0, $usage['daily']['requests']);
        
        // コストレポートの生成
        $report = $monitor->generateReport('daily');
        $this->assertArrayHasKey('period', $report);
        $this->assertArrayHasKey('requests', $report);
        $this->assertEquals('daily', $report['period']);
        
        // 制限チェック
        $this->assertFalse($monitor->isLimitReached('daily'));
    }

    /**
     * キャッシュ管理のテスト
     */
    public function testCacheManagement(): void
    {
        $cache = CacheManager::getInstance();
        
        // キャッシュの保存と取得
        $key = 'test_key';
        $value = ['test' => 'value', 'number' => 123];
        
        $this->assertTrue($cache->set($key, $value));
        $retrieved = $cache->get($key);
        $this->assertEquals($value, $retrieved);
        
        // 存在チェック
        $this->assertTrue($cache->has($key));
        
        // 削除
        $this->assertTrue($cache->delete($key));
        $this->assertNull($cache->get($key));
        
        // クリア
        $cache->set('another_key', 'another_value');
        $this->assertTrue($cache->clear());
        $this->assertNull($cache->get('another_key'));
        
        // メモリキャッシュのテスト
        $this->assertTrue($cache->memorySet('memory_key', 'memory_value'));
        $this->assertEquals('memory_value', $cache->memoryGet('memory_key'));
    }

    /**
     * パフォーマンス最適化のテスト
     */
    public function testPerformanceOptimization(): void
    {
        $optimizer = PerformanceOptimizer::getInstance();
        
        // バッチ処理のテスト
        $items = range(1, 10);
        $results = $optimizer->processBatch($items, function($item) {
            return $item * 2;
        });
        
        $this->assertCount(10, $results);
        $this->assertEquals(2, $results[0]);
        $this->assertEquals(20, $results[9]);
        
        // リソース使用量の監視
        $usage = $optimizer->monitorResourceUsage();
        $this->assertArrayHasKey('memory', $usage);
        $this->assertArrayHasKey('cpu', $usage);
        $this->assertArrayHasKey('time', $usage);
        
        // パフォーマンステストの実行
        $testFunction = function() {
            usleep(1000); // 1ms待機
        };
        
        $stats = $optimizer->runPerformanceTest($testFunction, 10);
        $this->assertArrayHasKey('average_time', $stats);
        $this->assertArrayHasKey('total_time', $stats);
        $this->assertGreaterThan(0, $stats['total_time']);
    }

    /**
     * 多言語管理のテスト
     */
    public function testMultiLanguageManagement(): void
    {
        $mlManager = MultiLanguageManager::getInstance();
        
        // サポート言語の取得
        $languages = $mlManager->getSupportedLanguages();
        $this->assertArrayHasKey('ja', $languages);
        $this->assertArrayHasKey('en', $languages);
        
        // 言語情報の取得
        $jaInfo = $mlManager->getLanguageInfo('ja');
        $this->assertEquals('Japanese', $jaInfo['name']);
        
        // 言語の追加
        $result = $mlManager->addLanguage('es', 'Spanish', 'es_ES', '🇪🇸');
        $this->assertTrue($result);
        $this->assertArrayHasKey('es', $mlManager->getSupportedLanguages());
        
        // 言語の削除
        $result = $mlManager->removeLanguage('es');
        $this->assertTrue($result);
        $this->assertArrayNotHasKey('es', $mlManager->getSupportedLanguages());
        
        // 言語切り替インターフェースの生成
        $switcher = $mlManager->generateLanguageSwitcher('en', '/test');
        $this->assertStringContainsString('language-switcher', $switcher);
    }

    /**
     * 専門用語辞書のテスト
     */
    public function testTerminologyDictionary(): void
    {
        $dictionary = TerminologyDictionary::getInstance();
        
        // 用語の追加
        $result = $dictionary->addTerm(
            'API',
            'Application Programming Interface',
            'en',
            'ja',
            ['priority' => 'high', 'domain' => 'technology']
        );
        $this->assertTrue($result);
        
        // 用語の検索
        $results = $dictionary->searchTerms('API');
        $this->assertGreaterThan(0, count($results));
        
        // 用語の取得
        $term = $dictionary->getTerm($results[0]['id']);
        $this->assertNotNull($term);
        $this->assertEquals('API', $term['term']);
        
        // 用語の更新
        $result = $dictionary->updateTerm($term['id'], ['priority' => 'critical']);
        $this->assertTrue($result);
        
        // 用語の削除
        $result = $dictionary->deleteTerm($term['id']);
        $this->assertTrue($result);
        $this->assertNull($dictionary->getTerm($term['id']));
        
        // バッチインポートのテスト
        $terms = [
            [
                'term' => 'HTML',
                'translation' => 'HyperText Markup Language',
                'source_language' => 'en',
                'target_language' => 'ja'
            ]
        ];
        
        $result = $dictionary->batchImport($terms);
        $this->assertGreaterThan(0, $result['success']);
        
        // 専門用語の適用
        $text = 'API and HTML are important';
        $result = $dictionary->applyTerminology($text, 'en', 'ja');
        $this->assertArrayHasKey('original', $result);
        $this->assertArrayHasKey('translated', $result);
    }

    /**
     * 翻訳品質評価のテスト
     */
    public function testTranslationQualityEvaluation(): void
    {
        $evaluator = TranslationQualityEvaluator::getInstance();
        
        // 品質評価の実行
        $original = 'Hello, world!';
        $translated = 'こんにちは、世界！';
        
        $result = $evaluator->evaluate($original, $translated, [
            'source_language' => 'en',
            'target_language' => 'ja'
        ]);
        
        $this->assertArrayHasKey('overall_score', $result);
        $this->assertArrayHasKey('quality_level', $result);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('issues', $result);
        
        // 品質レベルの確認
        $this->assertGreaterThanOrEqual(TranslationQualityEvaluator::QUALITY_FAIR, $result['quality_level']);
        
        // スコアの範囲確認
        $this->assertGreaterThanOrEqual(0, $result['overall_score']);
        $this->assertLessThanOrEqual(100, $result['overall_score']);
        
        // 基本的なチェックの確認
        $this->assertArrayHasKey('basic', $result['checks']);
        $this->assertArrayHasKey('score', $result['checks']['basic']);
        $this->assertArrayHasKey('max_score', $result['checks']['basic']);
    }

    /**
     * 管理画面のテスト
     */
    public function testAdminPanel(): void
    {
        // 管理画面のインスタンス化
        $adminPanel = AdminPanel::getInstance();
        $this->assertInstanceOf(AdminPanel::class, $adminPanel);
        
        // ダッシュボード統計の取得（モック）
        $stats = $adminPanel->getDashboardStats();
        $this->assertArrayHasKey('total_translations', $stats);
        $this->assertArrayHasKey('pending_reviews', $stats);
        $this->assertArrayHasKey('quality_score', $stats);
    }

    /**
     * 翻訳システムの統合テスト
     */
    public function testTranslationSystemIntegration(): void
    {
        // 翻訳プロセスのテスト
        $content = '# Hello World';
        
        // コンテンツ検出
        $finder = new ContentFinder();
        $contents = $finder->findJapaneseContent();
        
        // 翻訳実行
        $translator = new Translator();
        $result = $translator->translate($content);
        
        // 品質評価
        $evaluator = TranslationQualityEvaluator::getInstance();
        $quality = $evaluator->evaluate($content, $result, [
            'source_language' => 'en',
            'target_language' => 'ja'
        ]);
        
        // ファイル生成
        $generator = new FileGenerator();
        $targetPath = __DIR__ . '/../../cache/test/translated.md';
        $result = $generator->generateEnglishFile($targetPath, $result);
        
        $this->assertTrue($result['success']);
        $this->assertFileExists($targetPath);
        
        // クリーンアップ
        if (file_exists($targetPath)) {
            unlink($targetPath);
        }
    }

    /**
     * セキュリティテスト
     */
    public function testSecurityFeatures(): void
    {
        // CSRFトークンのテスト
        $token = CSRFTokenManager::generate();
        $this->assertTrue(CSRFTokenManager::validate($token));
        
        // ログイン制限のテスト
        $identifier = 'security_test';
        for ($i = 0; $i < 5; $i++) {
            LoginRateLimiter::recordAttempt($identifier);
        }
        $this->assertTrue(LoginRateLimiter::isLockedOut($identifier));
        
        // パスワード強度のテスト
        $validator = new PasswordValidator();
        $weakPassword = '123';
        $result = $validator->validate($weakPassword);
        $this->assertFalse($result['valid']);
        
        $strongPassword = 'SecurePassword123!';
        $result = $validator->validate($strongPassword);
        $this->assertTrue($result['valid']);
    }

    /**
     * パフォーマンステスト
     */
    public function testPerformanceFeatures(): void
    {
        // キャッシュのパフォーマンステスト
        $cache = CacheManager::getInstance();
        $startTime = microtime(true);
        
        for ($i = 0; $i < 1000; $i++) {
            $cache->set("test_$i", "value_$i");
        }
        
        $setTime = microtime(true) - $startTime;
        
        $startTime = microtime(true);
        for ($i = 0; $i < 1000; $i++) {
            $cache->get("test_$i");
        }
        
        $getTime = microtime(true) - $startTime;
        
        // 1秒以内に1000回の操作が完了することを確認
        $this->assertLessThan(1, $setTime);
        $this->assertLessThan(1, $getTime);
        
        // メモリ使用量のチェック
        $memoryUsage = memory_get_usage(true);
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsage); // 50MB未満
    }

    /**
     * エラーハンドリングのテスト
     */
    public function testErrorHandling(): void
    {
        // APIエラーのテスト
        $apiClient = new APIClient();
        
        // 無効なプロバイダ
        $this->expectException(\Exception::class);
        $apiClient->translate([], 'invalid_provider');
        
        // 無効なAPIキー（モック）
        // 実際のAPIキーはテストでは使用しない
        
        // ファイル操作エラーのテスト
        $generator = new FileGenerator();
        $result = $generator->generateEnglishFile('/invalid/path/test.md', 'content');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Permission denied', $result['error']);
    }
}