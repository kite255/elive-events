<?php

namespace Tests\Feature\Communications;

use App\Models\Event;
use Tests\TestCase;

class BrandedEmailCampaignPosterTest extends TestCase
{
    public function test_existing_email_layout_renders_campaign_poster_and_spotify_action(): void
    {
        $html = view('emails.elive', [
            'subject' => 'The Revived Way Thank You',
            'event' => new Event(['name' => 'The Revived Way Album Launch Concert']),
            'attendee' => null,
            'messageBody' => 'Thank you for attending.',
            'emailLabel' => 'Event Communication',
            'campaignPosterUrl' => 'https://events.elive.co.tz/storage/event-campaign-posters/thank-you.jpg',
            'actionUrl' => 'https://open.spotify.com/album/13nND3Cb2ZIek3XEPdH8Jr',
            'actionLabel' => 'Listen on Spotify',
            'actionButtonColor' => '#1DB954',
            'actionPlayIcon' => true,
        ])->render();

        $this->assertStringContainsString('The Revived Way Album Launch Concert', $html);
        $this->assertStringContainsString('storage/event-campaign-posters/thank-you.jpg', $html);
        $this->assertStringContainsString('#1DB954', $html);
        $this->assertStringContainsString('Listen on Spotify', $html);
        $this->assertStringContainsString('mailto:info@elive.co.tz', $html);
        $this->assertStringContainsString('tel:+255745939140', $html);
    }

    public function test_standard_email_does_not_show_album_poster_or_spotify_button(): void
    {
        $html = view('emails.elive', [
            'subject' => 'Ticket Confirmed',
            'event' => new Event(['name' => 'Another Event']),
            'attendee' => null,
            'messageBody' => 'Your ticket has been confirmed.',
            'emailLabel' => 'Event Communication',
        ])->render();

        $this->assertStringNotContainsString('event-campaign-posters/thank-you.jpg', $html);
        $this->assertStringNotContainsString('Listen on Spotify', $html);
        $this->assertStringContainsString('Another Event', $html);
    }
}
