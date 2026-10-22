<?php

namespace Tests;

use App\Events\PublicRoomDirectoryChanged;
use App\Events\RoomRealtimePinged;
use App\Events\RoomViewerCountChanged;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // HTTP/domain tests assert dispatch contracts without requiring a running websocket server.
        Event::fake([
            RoomRealtimePinged::class,
            PublicRoomDirectoryChanged::class,
            RoomViewerCountChanged::class,
        ]);
    }
}
