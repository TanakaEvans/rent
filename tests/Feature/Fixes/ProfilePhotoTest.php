<?php

namespace Tests\Feature\Fixes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profile photos: upload, replace, remove, validation, per-user isolation,
 * the shared account settings page, and the serialized avatar_url prop.
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function owner(): User
    {
        return $this->user('owner@dzimba.local');
    }

    private function tenant(): User
    {
        return $this->user('tenant@dzimba.local');
    }

    private function admin(): User
    {
        return $this->user('admin@system.local');
    }

    public function test_upload_sets_avatar_path_and_stores_the_file(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)
            ->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('me.jpg')])
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('success');

        $owner->refresh();
        $this->assertNotNull($owner->avatar_path);
        $this->assertStringStartsWith('avatars/'.$owner->id.'/', $owner->avatar_path);
        Storage::disk('public')->assertExists($owner->avatar_path);
    }

    public function test_replacing_the_photo_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('one.png')]);
        $old = $owner->refresh()->avatar_path;

        $this->actingAs($owner)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('two.png')]);
        $new = $owner->refresh()->avatar_path;

        $this->assertNotSame($old, $new);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($new);
    }

    public function test_delete_removes_the_file_and_nulls_the_column(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('me.webp')]);
        $path = $owner->refresh()->avatar_path;
        Storage::disk('public')->assertExists($path);

        $this->actingAs($owner)
            ->delete(route('account.avatar.destroy'))
            ->assertRedirect(route('account.profile'))
            ->assertSessionHas('success');

        $this->assertNull($owner->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner())
            ->from(route('account.profile'))
            ->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->create('resume.pdf', 200, 'application/pdf')])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($this->owner()->refresh()->avatar_path);
    }

    public function test_upload_rejects_images_over_four_megabytes(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner())
            ->from(route('account.profile'))
            ->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('huge.jpg')->size(5000)])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($this->owner()->refresh()->avatar_path);
    }

    public function test_a_user_cannot_change_another_users_avatar(): void
    {
        Storage::fake('public');

        // The owner uploads; the tenant's photo must stay untouched.
        $this->actingAs($this->owner())->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('owner.jpg')]);

        $this->assertNotNull($this->owner()->refresh()->avatar_path);
        $this->assertNull($this->tenant()->refresh()->avatar_path);

        // The tenant deleting their own (absent) photo cannot affect the owner's file.
        $ownerPath = $this->owner()->refresh()->avatar_path;
        $this->actingAs($this->tenant())->delete(route('account.avatar.destroy'));

        Storage::disk('public')->assertExists($ownerPath);
        $this->assertSame($ownerPath, $this->owner()->refresh()->avatar_path);
    }

    public function test_account_profile_page_opens_for_each_role(): void
    {
        foreach ([$this->admin(), $this->owner(), $this->tenant()] as $user) {
            $this->actingAs($user)
                ->get(route('account.profile'))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->component('Account/Profile'));
        }
    }

    public function test_auth_user_json_exposes_avatar_url_after_upload(): void
    {
        Storage::fake('public');
        $owner = $this->owner();

        $this->actingAs($owner)->post(route('account.avatar.store'), ['avatar' => UploadedFile::fake()->image('me.jpg')]);

        $this->actingAs($owner)
            ->get(route('account.profile'))
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.avatar_url', fn ($url) => is_string($url) && $url !== '')
                ->where('auth.user.initials', fn ($initials) => is_string($initials) && $initials !== ''));
    }
}
