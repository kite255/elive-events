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

<style>
    .donation-module {
        width: 100%;
    }

    .donation-module-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 24px;
        align-items: start;
    }

    .donation-module-main,
    .donation-module-side {
        background: #FFFFFF;
        border: 1px solid #E6E8EF;
        border-radius: 20px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
    }

    .donation-module-main {
        padding: 28px;
    }

    .donation-module-side {
        padding: 24px;
        position: sticky;
        top: 98px;
    }

    .donation-module-kicker {
        margin: 0 0 8px;
        color: #FF9800;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }

    .donation-module-title {
        margin: 0;
        color: #161943;
        font-size: 28px;
        line-height: 1.15;
        letter-spacing: -.025em;
    }

    .donation-module-side h3,
    .donation-donor-wall h3 {
        margin: 0;
        color: #161943;
        font-size: 20px;
    }

    .donation-module-description {
        margin-top: 14px;
        color: #475569;
        font-size: 15px;
        line-height: 1.75;
        white-space: pre-line;
    }

    .donation-progress-grid,
    .donation-donor-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
        margin-top: 20px;
    }

    .donation-stat,
    .donation-donor {
        padding: 14px;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        background: #F8FAFC;
    }

    .donation-stat span,
    .donation-donor span {
        display: block;
        color: #64748B;
        font-size: 12px;
    }

    .donation-stat strong,
    .donation-donor strong {
        display: block;
        margin-top: 5px;
        color: #161943;
        font-size: 17px;
    }

    .donation-gallery {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
        margin-top: 22px;
    }

    .donation-gallery a {
        overflow: hidden;
        display: block;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        background: #F8FAFC;
    }

    .donation-gallery img {
        width: 100%;
        height: auto;
        display: block;
    }

    .donation-donor-wall,
    .donation-direct-options {
        margin-top: 26px;
        padding-top: 22px;
        border-top: 1px solid #E8EDF4;
    }

    .donation-payment-method {
        margin-top: 12px;
        padding: 15px;
        border: 1px solid #E2E8F0;
        border-radius: 14px;
        background: #F8FAFC;
    }

    .donation-payment-method > strong {
        color: #161943;
        font-size: 15px;
    }

    .donation-payment-method p {
        margin: 6px 0;
        color: #475569;
        font-size: 13px;
        line-height: 1.55;
    }

    .donation-note,
    .donation-success,
    .donation-error {
        margin-top: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        font-size: 13px;
        line-height: 1.55;
    }

    .donation-note {
        background: #FFF7ED;
        color: #9A3412;
    }

    .donation-success {
        background: #F0FDF4;
        color: #166534;
    }

    .donation-error {
        background: #FEF2F2;
        color: #991B1B;
    }

    .donation-error p {
        margin: 0;
    }

    .donation-error p + p {
        margin-top: 4px;
    }

    .donation-suggested {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 16px;
    }

    .donation-amount-choice {
        min-height: 38px;
        padding: 0 12px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        background: #FFFFFF;
        color: #161943;
        font: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
    }

    .donation-amount-choice:hover {
        border-color: #007AB2;
        color: #007AB2;
    }

    .donation-form {
        display: grid;
        gap: 14px;
        margin-top: 18px;
    }

    .donation-form label {
        display: grid;
        gap: 6px;
        color: #334155;
        font-size: 13px;
        font-weight: 700;
    }

    .donation-form input[type="text"],
    .donation-form input[type="email"],
    .donation-form input[type="number"] {
        width: 100%;
        min-height: 44px;
        padding: 0 12px;
        border: 1px solid #CBD5E1;
        border-radius: 10px;
        background: #FFFFFF;
        color: #0F172A;
        font: inherit;
        font-weight: 500;
    }

    .donation-check {
        display: flex !important;
        grid-template-columns: none !important;
        align-items: flex-start;
        gap: 9px !important;
        font-weight: 600 !important;
    }

    .donation-check input {
        margin-top: 2px;
    }

    .donation-submit {
        min-height: 46px;
        border: 0;
        border-radius: 11px;
        background: #161943;
        color: #FFFFFF;
        font: inherit;
        font-size: 14px;
        font-weight: 800;
        cursor: pointer;
    }

    .donation-submit:hover {
        background: #007AB2;
    }

    @media (max-width: 900px) {
        .donation-module-grid {
            grid-template-columns: 1fr;
        }

        .donation-module-side {
            position: static;
        }
    }

    @media (max-width: 640px) {
        .donation-module-main,
        .donation-module-side {
            padding: 20px;
        }

        .donation-progress-grid,
        .donation-donor-grid,
        .donation-gallery {
            grid-template-columns: 1fr;
        }
    }
</style>

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
