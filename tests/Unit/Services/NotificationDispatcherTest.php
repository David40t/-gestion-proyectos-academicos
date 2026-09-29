<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Repositories\Contracts\ProjectMemberRepositoryInterface;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Mockery;
use Tests\TestCase;

class NotificationDispatcherTest extends TestCase
{
    public function test_it_removes_duplicates_nulls_and_the_actor(): void
    {
        NotificationFacade::fake();
        $actor = (new User)->forceFill(['id' => 1]);
        $a = (new User)->forceFill(['id' => 2]);
        $b = (new User)->forceFill(['id' => 3]);
        $duplicateOfA = (new User)->forceFill(['id' => 2]);

        $notification = new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }
        };

        (new NotificationDispatcher(Mockery::mock(ProjectMemberRepositoryInterface::class)))
            ->send([$a, $duplicateOfA, null, $b, $actor], $notification, $actor);

        NotificationFacade::assertCount(2);
        NotificationFacade::assertSentToTimes($a, $notification::class, 1);
        NotificationFacade::assertSentToTimes($b, $notification::class, 1);
        NotificationFacade::assertNotSentTo($actor, $notification::class);
    }

    public function test_nothing_is_sent_when_only_the_actor_would_receive_it(): void
    {
        NotificationFacade::fake();
        $actor = (new User)->forceFill(['id' => 1]);

        (new NotificationDispatcher(Mockery::mock(ProjectMemberRepositoryInterface::class)))
            ->send($actor, new class extends Notification {}, $actor);

        NotificationFacade::assertNothingSent();
    }
}
