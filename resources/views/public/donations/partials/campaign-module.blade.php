@php
    use App\Models\DonationCampaign;
    use App\Models\Donation;
    use App\Services\Donations\DonationMetricsService;
    use Illuminate\Support\Str;

    $embedded = $embedded ?? false;
    $metrics = $metrics
        ?? app(DonationMetricsService::class)->forCampaign($campaign);

    $displayOnly = $campaign->payment_mode === DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT
        && $campaign->direct_payment_behavior === DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY;

    $tracked = ! $displayOnly;

    $paymentMethods = $campaign->relationLoaded('paymentMethods')
        ? $campaign->paymentMethods->where('enabled', true)
        : $campaign->paymentMethods()->where('enabled', true)->get();

    $donorWall = $campaign->donor_wall_enabled
        ? $campaign->donations()
            ->where('status', Donation::STATUS_COMPLETED)
            ->where('public_display_consent', true)
            ->latest('completed_at')
            ->limit(12)
            ->get()
        : collect();

    $gallery = collect($campaign->gallery_image_paths ?? [])->filter();

    $showProgress = ! $displayOnly && (
        $campaign->show_goal
        || $campaign->show_amount_raised
        || $campaign->show_percentage
        || $campaign->show_donor_count
    );
@endphp

<section class="donation-module {{ $embedded ? 'donation-module-embedded' : '' }}" aria-labelledby="donation-module-heading">
    <div class="donation-module-grid">
        <article class="donation-module-main">
            <p class="donation-module-kicker">{{ $embedded ? 'Support This Event' : 'Campaign' }}</p>
            <h2 id="donation-module-heading" class="donation-module-title">{{ $campaign->title }}</h2>

            @if ($campaign->description)
                <div class="donation-module-description">{{ $campaign->description }}</div>
            @endif

            @if ($showProgress)
                <div class="donation-progress-grid">
                    @if ($campaign->show_amount_raised)
                        <div class="donation-stat">
                            <span>Amount Raised</span>
                            <strong>{{ $campaign->currency }} {{ number_format((float) $metrics['amount_raised']) }}</strong>
                        </div>
                    @endif

                    @if ($campaign->show_goal && $metrics['goal_amount'] !== null)
                        <div class="donation-stat">
                            <span>Goal</span>
                            <strong>{{ $campaign->currency }} {{ number_format((float) $metrics['goal_amount']) }}</strong>
                        </div>
                    @endif

                    @if ($campaign->show_percentage && $metrics['percentage'] !== null)
                        <div class="donation-stat">
                            <span>Progress</span>
                            <strong>{{ rtrim(rtrim(number_format((float) $metrics['percentage'], 2, '.', ''), '0'), '.') }}%</strong>
                        </div>
                    @endif

                    @if ($campaign->show_donor_count)
                        <div class="donation-stat">
                            <span>Donors</span>
                            <strong>{{ number_format((int) $metrics['donor_count']) }}</strong>
                        </div>
                    @endif
                </div>
            @endif

            @if ($gallery->isNotEmpty())
                <div class="donation-gallery">
                    @foreach ($gallery as $imagePath)
                        @php
                            $imageUrl = Str::startsWith($imagePath, ['http://', 'https://'])
                                ? $imagePath
                                : (
                                    Str::startsWith($imagePath, ['storage/', '/storage/'])
                                        ? asset(ltrim($imagePath, '/'))
                                        : asset('storage/' . ltrim($imagePath, '/'))
                                );
                        @endphp

                        <a href="{{ $imageUrl }}" target="_blank" rel="noopener">
                            <img src="{{ $imageUrl }}" alt="{{ $campaign->title }} campaign image" loading="lazy">
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($campaign->donor_wall_enabled && $donorWall->isNotEmpty())
                <div class="donation-donor-wall">
                    <p class="donation-module-kicker">Supporters</p>
                    <h3>Donor Wall</h3>
                    <div class="donation-donor-grid">
                        @foreach ($donorWall as $donation)
                            <div class="donation-donor">
                                <strong>{{ $donation->is_anonymous ? 'Anonymous' : ($donation->donor_name ?: 'Supporter') }}</strong>
                                <span>{{ $campaign->currency }} {{ number_format((float) $donation->amount) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </article>

        <aside class="donation-module-side">
            @if ($displayOnly)
                <p class="donation-module-kicker">How to contribute</p>
                <h3>Payment Instructions</h3>

                @forelse ($paymentMethods as $method)
                    <article class="donation-payment-method">
                        <strong>{{ $method->provider_name }}</strong>

                        @if ($method->account_name)
                            <p><b>Account Name:</b> {{ $method->account_name }}</p>
                        @endif

                        @if ($method->account_number_or_phone)
                            <p><b>Account / Phone:</b> {{ $method->account_number_or_phone }}</p>
                        @endif

                        @if ($method->instructions)
                            <p>{{ $method->instructions }}</p>
                        @endif
                    </article>
                @empty
                    <p class="donation-note">Payment instructions have not been published yet.</p>
                @endforelse

                <p class="donation-note">
                    Funds are sent directly using the organizer's published payment instructions.
                </p>
            @else
                <p class="donation-module-kicker">Make a donation</p>
                <h3>Support this campaign</h3>

                @if (session('status'))
                    <p class="donation-success">{{ session('status') }}</p>
                @endif

                @if ($errors->any())
                    <div class="donation-error">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                @if (! empty($campaign->suggested_amounts))
                    <div class="donation-suggested">
                        @foreach ($campaign->suggested_amounts as $amount)
                            <button type="button" class="donation-amount-choice" data-donation-amount="{{ (float) $amount }}">
                                {{ $campaign->currency }} {{ number_format((float) $amount) }}
                            </button>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('public.donations.store', ['campaign' => $campaign->slug]) }}" class="donation-form">
                    @csrf

                    <label>
                        Amount ({{ $campaign->currency }})
                        <input
                            type="number"
                            name="amount"
                            value="{{ old('amount') }}"
                            min="{{ $campaign->minimum_amount ?: 1 }}"
                            step="0.01"
                            {{ $campaign->allow_custom_amount ? '' : 'readonly' }}
                            required
                            data-donation-amount-input
                        >
                    </label>

                    <label>
                        Name
                        <input type="text" name="donor_name" value="{{ old('donor_name') }}" maxlength="255">
                    </label>

                    <label>
                        Phone
                        <input type="text" name="donor_phone" value="{{ old('donor_phone') }}" maxlength="30">
                    </label>

                    <label>
                        Email
                        <input type="email" name="donor_email" value="{{ old('donor_email') }}" maxlength="255">
                    </label>

                    <label class="donation-check">
                        <input type="checkbox" name="is_anonymous" value="1" {{ old('is_anonymous') ? 'checked' : '' }}>
                        Donate anonymously
                    </label>

                    @if ($campaign->donor_wall_enabled)
                        <label class="donation-check">
                            <input type="checkbox" name="public_display_consent" value="1" {{ old('public_display_consent') ? 'checked' : '' }}>
                            Allow this donation to appear on the public donor wall
                        </label>
                    @endif

                    <button type="submit" class="donation-submit">Continue Donation</button>
                </form>

                @if ($campaign->payment_mode === DonationCampaign::PAYMENT_MODE_HYBRID && $paymentMethods->isNotEmpty())
                    <div class="donation-direct-options">
                        <p class="donation-module-kicker">Direct payment options</p>
                        @foreach ($paymentMethods as $method)
                            <article class="donation-payment-method">
                                <strong>{{ $method->provider_name }}</strong>
                                @if ($method->account_name)
                                    <p><b>Account Name:</b> {{ $method->account_name }}</p>
                                @endif
                                @if ($method->account_number_or_phone)
                                    <p><b>Account / Phone:</b> {{ $method->account_number_or_phone }}</p>
                                @endif
                                @if ($method->instructions)
                                    <p>{{ $method->instructions }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            @endif
        </aside>
    </div>
</section>

<script>
    document.addEventListener('click', function (event) {
        const choice = event.target.closest('[data-donation-amount]');

        if (!choice) {
            return;
        }

        const module = choice.closest('.donation-module');
        const input = module ? module.querySelector('[data-donation-amount-input]') : null;

        if (input) {
            input.value = choice.dataset.donationAmount;
        }
    });
</script>
