<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PublicStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_serves_duitnow_file_from_public_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('duitnow/1/test.png', 'fake-png-bytes');

        $this->get('/media/duitnow/1/test.png')
            ->assertOk();
    }

    public function test_rejects_path_outside_duitnow_prefix(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('secret.txt', 'nope');

        $this->get('/media/secret.txt')->assertNotFound();
    }

    public function test_rejects_path_traversal(): void
    {
        $this->get('/media/duitnow/../.env')->assertNotFound();
    }
}
