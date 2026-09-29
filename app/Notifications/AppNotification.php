<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base de todas las notificaciones del sistema (ADR-008).
 *
 * - Siempre se guarda en BD (notificación interna); el correo solo si shouldMail() lo decide.
 * - Se encola y se despacha DESPUÉS del commit: nunca se avisa de algo que se revirtió.
 * - Guarda solo datos primitivos (texto y URL) calculados al crearla, para que el job en cola
 *   no dependa de que los modelos sigan existiendo.
 * - Agregar un canal nuevo = modificar via(); los Services no cambian.
 * - Ante fallos transitorios (red, SMTP) se reintenta con espera progresiva.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** Intentos máximos por canal antes de registrar el job en failed_jobs. */
    public int $tries = 3;

    /**
     * Segundos de espera entre reintentos.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(
        protected readonly string $category,
        protected readonly string $title,
        protected readonly string $message,
        protected readonly string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * Decide por destinatario si el evento amerita correo. Por defecto, no.
     */
    protected function shouldMail(object $notifiable): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $this->shouldMail($notifiable) ? ['database', 'mail'] : ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting("Hola, {$notifiable->name}")
            ->line($this->message)
            ->action('Ver en el sistema', $this->url)
            ->line('Recibes este correo porque participas en el proyecto.');
    }
}
