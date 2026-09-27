<?php

declare(strict_types=1);

namespace CarMoneyLab\Tests\Unit;

use CarMoneyLab\Domain\DecisionEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecisionEngineTest extends TestCase
{
    private DecisionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new DecisionEngine(
            ['approve_max' => 60.0, 'review_max' => 85.0],
            400000,
        );
    }

    #[DataProvider('ltvValues')]
    public function testDecidesByLtv(float $ltv, int $mileage, string $expected): void
    {
        self::assertSame($expected, $this->engine->decide($ltv, $mileage));
    }

    /** @return array<string,array{float,int,string}> */
    public static function ltvValues(): array
    {
        return [
            'низкий LTV' => [28.5, 100000, DecisionEngine::APPROVE],
            'середина зелёной зоны' => [45.0, 100000, DecisionEngine::APPROVE],
            'серая зона' => [72.3, 100000, DecisionEngine::REVIEW],
            'верхняя граница серой зоны' => [85.0, 100000, DecisionEngine::REVIEW],
            'сразу за верхней границей' => [85.01, 100000, DecisionEngine::REJECT],
            'высокий LTV' => [120.0, 100000, DecisionEngine::REJECT],
            'LTV 59.9 — низкая зона approve' => [59.9, 100000, DecisionEngine::APPROVE],
            'LTV 60.0 — серая зона review' => [60.0, 100000, DecisionEngine::REVIEW],
            'пробег 1 — нижняя граница зоны «не меняет решение»' => [50.0, 1, DecisionEngine::APPROVE],
            'пробег 399999 — порог минус один' => [50.0, 399999, DecisionEngine::APPROVE],
            'пробег 400000 — порог включительно' => [50.0, 400000, DecisionEngine::APPROVE],
            'пробег 400001 — понижение из approve' => [50.0, 400001, DecisionEngine::REVIEW],
            'пробег 500000 — верх зоны правила' => [50.0, 500000, DecisionEngine::REVIEW],
            'пробег 0 — нет данных' => [50.0, 0, DecisionEngine::REVIEW],
            'пробег 400001 + LTV 95 — приоритет reject' => [95.0, 400001, DecisionEngine::REJECT],
            'пробег 400001 + LTV 75 — review остаётся review' => [75.0, 400001, DecisionEngine::REVIEW],
        ];
    }
}
