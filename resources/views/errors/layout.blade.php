<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#161943">
    <title>@yield('title') | eLive Events</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; min-height: 100dvh; display: flex; flex-direction: column; background: #f8fafc; color: #161943; font: 16px/1.6 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        header { padding: 22px clamp(20px, 5vw, 64px); border-bottom: 1px solid #e2e8f0; background: #fff; }
        .brand { display: inline-flex; align-items: center; gap: 12px; color: #161943; text-decoration: none; font-weight: 700; font-size: 20px; }
        .brand img { width: 44px; height: 44px; object-fit: contain; }
        main { flex: 1; display: grid; place-items: center; padding: 48px 20px; }
        .panel { width: 100%; max-width: 560px; text-align: center; }
        .symbol { display: grid; place-items: center; width: 68px; height: 68px; margin: 0 auto 20px; border-radius: 20px; background: #e6f4fa; color: #007ab2; }
        .code { margin: 0; color: #007ab2; font-size: 13px; font-weight: 700; letter-spacing: .12em; }
        h1 { font-size: clamp(28px, 5vw, 40px); letter-spacing: -.035em; line-height: 1.2; margin: 12px 0 16px; }
        .message { margin: 0 auto 28px; max-width: 460px; color: #475569; }
        .actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 10px 20px; border: 1px solid #cbd5e1; border-radius: 9px; color: #161943; background: #fff; text-decoration: none; font-weight: 600; }
        .primary { background: #161943; color: #fff; border-color: #161943; }
        a:focus-visible { outline: 3px solid #007ab2; outline-offset: 4px; }
        footer { text-align: center; padding: 20px; color: #64748b; font-size: 13px; }
        @media (max-width: 420px) { .actions { flex-direction: column; } .button { width: 100%; } }
    </style>
</head>
<body>
    <header>
        <a href="/" class="brand"><img src="/eLive-Logo.png" alt="" width="44" height="44"> <span>eLive Events</span></a>
    </header>
    <main>
        <section class="panel" aria-labelledby="error-title">
            <div class="symbol" aria-hidden="true">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/></svg>
            </div>
            <p class="code">ERROR @yield('code')</p>
            <h1 id="error-title">@yield('title')</h1>
            <p class="message">@yield('message')</p>
            <div class="actions">
                <a class="button primary" href="/">Back to homepage</a>
                <a class="button" href="mailto:info@elive.co.tz">Contact support</a>
            </div>
        </section>
    </main>
    <footer>eLive Events &middot; eLive Company Limited</footer>
</body>
</html>
