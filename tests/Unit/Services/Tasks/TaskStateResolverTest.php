<?php

namespace Tests\Unit\Services\Tasks;

use App\Enums\TaskStatus;
use App\Exceptions\BusinessRuleException;
use App\Services\Tasks\TaskStateResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaskStateResolverTest extends TestCase
{
    private const TODAY = '2026-10-15';

    /**
     * @return array<string, array{TaskStatus, int, string, TaskStatus, int}>
     */
    public static function coherentStates(): array
    {
        return [
            'pendiente sin avance' => [TaskStatus::Pendiente, 0, '2026-10-20', TaskStatus::Pendiente, 0],
            'pendiente con avance pasa a en progreso' => [TaskStatus::Pendiente, 30, '2026-10-20', TaskStatus::EnProgreso, 30],
            'en progreso' => [TaskStatus::EnProgreso, 60, '2026-10-20', TaskStatus::EnProgreso, 60],
            'avance 100 completa la tarea' => [TaskStatus::EnProgreso, 100, '2026-10-20', TaskStatus::Completada, 100],
            'completada fija avance en 100' => [TaskStatus::Completada, 40, '2026-10-20', TaskStatus::Completada, 100],
            'vencida si la fecha pasó' => [TaskStatus::EnProgreso, 50, '2026-10-14', TaskStatus::Vencida, 50],
            'vence hoy aún no está vencida' => [TaskStatus::EnProgreso, 50, self::TODAY, TaskStatus::EnProgreso, 50],
            'entrega tardía se completa' => [TaskStatus::Completada, 50, '2026-10-01', TaskStatus::Completada, 100],
            'reabrir una completada' => [TaskStatus::EnProgreso, 80, '2026-10-20', TaskStatus::EnProgreso, 80],
        ];
    }

    #[DataProvider('coherentStates')]
    public function test_it_resolves_coherent_states(TaskStatus $requested, int $progress, string $due, TaskStatus $expectedStatus, int $expectedProgress): void
    {
        $result = (new TaskStateResolver)->resolve($requested, $progress, CarbonImmutable::parse($due), CarbonImmutable::parse(self::TODAY.' 15:30'));

        $this->assertSame($expectedStatus, $result['status']);
        $this->assertSame($expectedProgress, $result['progress']);
    }

    /**
     * @return array<string, array{TaskStatus, int}>
     */
    public static function invalidStates(): array
    {
        return [
            'en progreso sin avance' => [TaskStatus::EnProgreso, 0],
            'vencida manual' => [TaskStatus::Vencida, 10],
            'avance negativo' => [TaskStatus::EnProgreso, -1],
            'avance mayor a 100' => [TaskStatus::EnProgreso, 101],
        ];
    }

    #[DataProvider('invalidStates')]
    public function test_it_rejects_incoherent_states(TaskStatus $requested, int $progress): void
    {
        $this->expectException(BusinessRuleException::class);

        (new TaskStateResolver)->resolve($requested, $progress, CarbonImmutable::parse('2026-10-20'), CarbonImmutable::parse(self::TODAY));
    }
}
