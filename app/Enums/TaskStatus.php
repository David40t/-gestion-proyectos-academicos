<?php

namespace App\Enums;

/**
 * Estados de una tarea. "Vencida" solo la asigna el sistema (ver docs/03, §4).
 */
enum TaskStatus: string
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Completada = 'completada';
    case Vencida = 'vencida';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProgreso => 'En progreso',
            self::Completada => 'Completada',
            self::Vencida => 'Vencida',
        };
    }

    /**
     * Estados que un usuario puede seleccionar manualmente.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Pendiente, self::EnProgreso, self::Completada];
    }

    /**
     * Estados de una tarea aún no terminada.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pendiente, self::EnProgreso, self::Vencida];
    }
}
