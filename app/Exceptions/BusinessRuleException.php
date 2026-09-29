<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violación de una regla de negocio detectada en la capa de Services
 * (p. ej. "no se puede retirar al líder"). Se muestra al usuario como mensaje de error
 * y, al lanzarse dentro de DB::transaction, revierte la operación completa.
 */
class BusinessRuleException extends RuntimeException {}
