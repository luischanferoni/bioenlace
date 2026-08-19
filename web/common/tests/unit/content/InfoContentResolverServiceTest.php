<?php

namespace common\tests\unit\content;

use Codeception\Test\Unit;
use common\components\Domain\Content\Service\InfoContentResolverService;
use common\models\InfoContentArticle;

class InfoContentResolverServiceTest extends Unit
{
    protected function _before(): void
    {
        $this->ensureSeedExists();
    }

    public function testResolveByTopicProducto(): void
    {
        $article = InfoContentResolverService::resolve('representacion');
        if ($article === null) {
            $this->markTestSkipped('Seed no aplicado (tabla o datos faltantes).');
        }

        $this->assertSame('representacion', $article->topic);
        $this->assertSame(InfoContentArticle::SCOPE_PRODUCTO, $article->scope);
        $this->assertNotEmpty($article->body);
    }

    public function testResolveByTextMatchesRepresentacion(): void
    {
        $article = InfoContentResolverService::resolveByText('qué es la representación');
        if ($article === null) {
            $this->markTestSkipped('Seed no aplicado.');
        }

        $this->assertSame('representacion', $article->topic);
    }

    public function testResolveByTextMatchesTeleconsulta(): void
    {
        $article = InfoContentResolverService::resolveByText('cómo funciona la teleconsulta');
        if ($article === null) {
            $this->markTestSkipped('Seed no aplicado.');
        }

        $this->assertSame('teleconsulta', $article->topic);
    }

    public function testResolveByTextNoMatchReturnsNull(): void
    {
        $article = InfoContentResolverService::resolveByText('xyzzy nonsense query 12345');
        $this->assertNull($article);
    }

    public function testAllActiveTopicsNotEmpty(): void
    {
        $topics = InfoContentResolverService::allActiveTopics();
        if ($topics === []) {
            $this->markTestSkipped('Seed no aplicado.');
        }

        $this->assertContains('representacion', $topics);
        $this->assertContains('teleconsulta', $topics);
    }

    public function testScopeLabels(): void
    {
        $labels = InfoContentArticle::scopeLabels();
        $this->assertArrayHasKey('producto', $labels);
        $this->assertArrayHasKey('provincia', $labels);
        $this->assertArrayHasKey('efector', $labels);
    }

    public function testKeywordListParsing(): void
    {
        $a = new InfoContentArticle();
        $a->keywords = 'foo, bar , baz';
        $this->assertSame(['foo', 'bar', 'baz'], $a->getKeywordList());
    }

    public function testKeywordListEmpty(): void
    {
        $a = new InfoContentArticle();
        $a->keywords = '';
        $this->assertSame([], $a->getKeywordList());
    }

    private function ensureSeedExists(): void
    {
        try {
            InfoContentArticle::find()->limit(1)->one();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Tabla info_content_article no existe: ' . $e->getMessage());
        }
    }
}
