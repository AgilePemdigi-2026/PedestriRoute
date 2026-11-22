<?php

namespace Tests\Feature;

use App\Models\Warga;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlaceSearchTest extends TestCase
{
    public function test_address_search_requires_authentication(): void
    {
        Http::preventStrayRequests();
        $this->getJson('/api/v1/places/search?q=Sutorejo')->assertUnauthorized();
        Http::assertNothingSent();
    }

    public function test_address_search_validates_query_before_contacting_geocoder(): void
    {
        Sanctum::actingAs(new Warga);
        Http::preventStrayRequests();

        $this->getJson('/api/v1/places/search?q=ab')->assertUnprocessable()->assertJsonValidationErrors('q');
        Http::assertNothingSent();
    }

    public function test_addresses_return_coordinates_and_labels_and_repeated_searches_are_cached(): void
    {
        Sanctum::actingAs(new Warga);
        Http::preventStrayRequests();
        Http::fake(['https://photon.komoot.io/api/*' => Http::response(['features' => [
            ['geometry' => ['type' => 'Point', 'coordinates' => [112.795, -7.266]],
                'properties' => ['street' => 'Jalan Sutorejo', 'housenumber' => '12', 'city' => 'Surabaya']],
            ['geometry' => ['type' => 'LineString', 'coordinates' => [[0, 0], [1, 1]]]],
        ]])]);

        $url = '/api/v1/places/search?q=Jalan%20Sutorejo%2012';
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'places')
            ->assertJsonPath('places.0.label', 'Jalan Sutorejo 12, Surabaya')
            ->assertJsonPath('places.0.lat', -7.266)->assertJsonPath('places.0.lng', 112.795);
        $this->getJson($url)->assertOk();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request['q'] === 'Jalan Sutorejo 12' && $request['bbox'] === '112.75,-7.31,112.85,-7.22');
    }

    public function test_geocoder_outage_returns_recommendation_guidance_instead_of_fake_coordinates(): void
    {
        Sanctum::actingAs(new Warga);
        Http::preventStrayRequests();
        Http::fake(['https://photon.komoot.io/api/*' => Http::response([], 503)]);

        $this->getJson('/api/v1/places/search?q=Alamat%20Saya')->assertStatus(503)
            ->assertJsonPath('error.code', 'PLACE_SEARCH_UNAVAILABLE')->assertJsonMissingPath('places');
        Http::assertSentCount(1);
    }
}
