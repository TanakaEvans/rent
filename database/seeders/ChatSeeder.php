<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use App\Services\ChatService;
use Illuminate\Database\Seeder;

/**
 * Demo chat data so all three parties can try the feature immediately:
 *  - a direct thread between the demo tenant and owner about a listing;
 *  - a support thread from the tenant that a staff member has answered.
 */
class ChatSeeder extends Seeder
{
    public function run(): void
    {
        $chat = app(ChatService::class);

        $owner = User::where('username', 'owner')->first();
        $tenant = User::where('username', 'tenant')->first();
        $staff = User::where('username', 'staff')->first();

        if (! $owner || ! $tenant) {
            return;
        }

        $property = Property::where('owner_id', $owner->id)->where('status', 'available')->first()
            ?? Property::where('owner_id', $owner->id)->first();

        if ($property) {
            $direct = $chat->startDirect($tenant, $property);
            $chat->postMessage($direct, $tenant, 'Hi, is this property still available? I would like to arrange a viewing this week.');
            $chat->postMessage($direct, $owner, 'Hello! Yes it is available. I am free on Saturday morning — does that work for you?');
        }

        $support = $chat->startSupport($tenant);
        $chat->postMessage($support, $tenant, 'Hi ZimRent, how do I get the verified badge on my profile?');

        if ($staff) {
            $chat->postMessage($support, $staff, 'Thanks for reaching out! Upload your ID under My Profile and our team will review it within 48 hours.');
        }
    }
}
