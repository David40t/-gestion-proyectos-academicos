<?php

namespace App\Enums;

/**
 * Estados del proyecto y sus transiciones permitidas.
 * Único punto de cambio si se agregan o modifican estados (ADR-004b).
 */
enum ProjectStatus: string
{
    case Planeacion = 'planeacion';
    case EnProgreso = 'en_progreso';
    case EnRevision = 'en_revision';
    case Finalizado = 'finalizado';
    case Cancelado = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Planeacion => 'Planeación',
            self::EnProgreso => 'En progreso',
            self::EnRevision => 'En revisión',
            self::Finalizado => 'Finalizado',
            self::Cancelado => 'Cancelado',
        };
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Planeacion => [self::EnProgreso, self::Cancelado],
            self::EnProgreso => [self::EnRevision, self::Cancelado],
            self::EnRevision => [self::EnProgreso, self::Finalizado],
            self::Finalizado, self::Cancelado => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->allowedTransitions(), true);
    }

    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Estados considerados "activos" en el dashboard.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status) => ! $status->isFinal()));
    }
}
