<!DOCTYPE html>
<html lang="de" class="h-100" data-theme="dark">
<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Wartung | Vorübergehend nicht erreichbar (503)</title>
    <meta name="robots" content="noindex" />
    <link rel="icon" href="/favicon.ico" />
    <style>
        :root {
            --bg: #0d1117;
            --bg-accent: #161b22;
            --bg-accent2: #1f2733;
            --fg: #f0f3f8;
            --fg-dim: #9aa4b1;
            --primary: #2f81f7;
            --primary-rgb: 47,129,247;
            --danger: #ff5470;
            --gradient: linear-gradient(135deg,#2f81f7 0%,#6c2ff7 55%,#b92ff7 100%);
        }
        @media (prefers-color-scheme: light) {
            :root {
                --bg: #f5f7fb;
                --bg-accent: #ffffff;
                --bg-accent2: #eef2f8;
                --fg: #1b2430;
                --fg-dim: #5a6573;
                --primary: #0b63e6;
                --primary-rgb: 11,99,230;
                --gradient: linear-gradient(135deg,#0b63e6 0%,#6a3ded 60%,#c236ef 100%);
            }
        }
        * { box-sizing: border-box; }
        html,body { height:100%; }
        body {
            margin:0; font-family:"Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", Arial, sans-serif;
            background: var(--bg);
            color: var(--fg);
            -webkit-font-smoothing: antialiased;
            display:flex; align-items:center; justify-content:center;
            padding: clamp(1.25rem, 3vw, 3rem);
            overflow:hidden;
        }
        .shell {
            position:relative; max-width: 880px; width:100%; isolation:isolate;
        }
        .panel {
            background: linear-gradient(140deg, var(--bg-accent) 0%, var(--bg-accent2) 100%);
            border: 1px solid rgba(255,255,255,0.05);
            backdrop-filter: blur(18px) saturate(170%);
            -webkit-backdrop-filter: blur(18px) saturate(170%);
            border-radius: 28px;
            padding: clamp(2rem, 4vw, 4rem) clamp(1.5rem, 3vw, 3.25rem) clamp(2.2rem, 4vw, 4rem);
            overflow:hidden;
            box-shadow: 0 10px 35px -5px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,0.03) inset;
        }
        h1 {
            font-size: clamp(2.4rem, 6vw, 4rem);
            line-height:1.05; margin:0 0 .85rem; font-weight:700; letter-spacing:-.02em;
            background: var(--gradient); -webkit-background-clip:text; color:transparent;
        }
        p.lead { font-size: clamp(1.05rem, 1.5vw, 1.35rem); line-height:1.5; margin:0 0 1.65rem; font-weight:500; color: var(--fg-dim); }
        .mono { font-family: "JetBrains Mono", Menlo, Consolas, monospace; font-size:.85rem; letter-spacing:.05em; text-transform:uppercase; font-weight:600; color:var(--primary); display:inline-flex; align-items:center; gap:.4em; }
        .code { display:inline-block; padding:.4em .75em; background:rgba(var(--primary-rgb),0.12); color:var(--primary); border:1px solid rgba(var(--primary-rgb),0.25); border-radius:10px; font-weight:600; font-size:.8rem; letter-spacing:.04em; }
        .actions { display:flex; flex-wrap:wrap; gap:.9rem; margin-top:1.2rem; }
        a.btn, button.btn {
            --btn-bg: var(--primary);
            position:relative; display:inline-flex; align-items:center; gap:.55em; text-decoration:none;
            background:var(--btn-bg); color:#fff; font-weight:600; padding:.95rem 1.35rem;
            font-size:.95rem; letter-spacing:.02em; border-radius:14px; border:1px solid rgba(255,255,255,0.15);
            box-shadow: 0 4px 18px -2px rgba(var(--primary-rgb), .55), 0 0 0 1px rgba(255,255,255,0.08) inset;
            transition: all .25s cubic-bezier(.4,.2,.2,1);
            cursor:pointer;
        }
        a.btn.secondary { --btn-bg: rgba(255,255,255,0.08); color: var(--fg); box-shadow: 0 2px 10px -2px rgba(0,0,0,.4); }
        a.btn.secondary:hover { background:rgba(255,255,255,0.13); }
        a.btn:hover, button.btn:hover { transform:translateY(-2px); box-shadow:0 6px 25px -4px rgba(var(--primary-rgb), .6); }
        a.btn:active, button.btn:active { transform:translateY(0); }
        footer { margin-top: clamp(2.2rem,4vw,3.2rem); font-size:.75rem; color:var(--fg-dim); display:flex; justify-content:space-between; flex-wrap:wrap; gap:.75rem; }
        footer span { opacity:.75; }
        .decor { position:absolute; inset:0; pointer-events:none; }
        .blob { position:absolute; width:480px; height:480px; background:radial-gradient(circle at 30% 30%, rgba(var(--primary-rgb),0.55), transparent 60%); filter:blur(60px); opacity:.4; animation: float 14s ease-in-out infinite; }
        .blob.b2 { top:auto; bottom:-120px; right:-160px; left:auto; background:radial-gradient(circle at 70% 70%, rgba(185,47,247,.55), transparent 65%); animation-duration:18s; animation-direction:reverse; }
        @keyframes float { 0%,100% { transform:translateY(-30px) rotate(0deg); } 50% { transform:translateY(25px) rotate(40deg); } }
        .spark { position:absolute; width:4px; height:4px; background:var(--primary); border-radius:50%; box-shadow:0 0 0 0 rgba(var(--primary-rgb),1); animation: pulse 3.2s ease-in-out infinite; opacity:0.8; }
        .spark.s2 { left:65%; top:25%; animation-delay:.9s; }
        .spark.s3 { left:20%; top:70%; animation-delay:1.8s; }
        @keyframes pulse { 0%,100% { transform:scale(.7); box-shadow:0 0 0 0 rgba(var(--primary-rgb),.9);} 50% { transform:scale(1.6); box-shadow:0 0 12px 4px rgba(var(--primary-rgb),0); } }
        .status-box { display:inline-flex; align-items:center; gap:.6rem; padding:.55rem .85rem; border:1px solid rgba(255,255,255,0.09); background:rgba(255,255,255,0.03); border-radius:12px; font-size:.75rem; letter-spacing:.08em; text-transform:uppercase; font-weight:600; color:var(--fg-dim); margin-bottom:1.4rem; }
        .status-dot { width:10px; height:10px; border-radius:50%; background:radial-gradient(circle at 30% 30%, var(--danger), #ff1355); box-shadow:0 0 0 4px rgba(255,84,112,.15), 0 0 14px -2px #ff5470; animation: dotpulse 2.8s ease-in-out infinite; }
        @keyframes dotpulse { 0%,100% { transform:scale(.85); filter:brightness(1); } 50% { transform:scale(1.4); filter:brightness(1.4); } }
        .tips { margin-top:2.2rem; display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); }
        .tip { background:linear-gradient(145deg, rgba(255,255,255,0.04), rgba(255,255,255,0.01)); border:1px solid rgba(255,255,255,0.07); border-radius:16px; padding:.95rem 1rem 1rem; font-size:.78rem; line-height:1.35; color:var(--fg-dim); position:relative; overflow:hidden; }
        .tip:before { content:""; position:absolute; inset:0; background:linear-gradient(120deg, rgba(var(--primary-rgb),0.12), transparent 70%); opacity:0; transition:.5s; }
        .tip:hover:before { opacity:1; }
        .tip strong { color:var(--fg); font-weight:600; display:block; margin-bottom:.35rem; font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; }
        .sep { display:inline-block; width:2px; height:14px; background:linear-gradient(to bottom, transparent, rgba(255,255,255,0.3), transparent); }
        .brand { display:flex; align-items:center; gap:.85rem; margin:0 0 1.4rem; }
        .brand img { height:56px; width:auto; display:block; filter:drop-shadow(0 4px 12px rgba(0,0,0,.35)); }
        .brand .appname { font-weight:600; letter-spacing:.04em; font-size:.9rem; text-transform:uppercase; color:var(--fg-dim); }
        .explain { font-size:.8rem; line-height:1.45; margin:-.6rem 0 1.4rem; color:var(--fg-dim); }
        .reload-info { font-size:.7rem; letter-spacing:.08em; text-transform:uppercase; color:var(--fg-dim); margin-top:.4rem; }
        .reload-info strong { color:var(--primary); }
        .support-hint { font-size:.65rem; margin-top:1.5rem; opacity:.65; }
        @media (max-width:640px) { footer { flex-direction:column; align-items:flex-start; } }
    </style>
</head>
<body>
<div class="shell">
    <div class="panel" role="alert" aria-labelledby="err-title" aria-describedby="err-desc">
        <div class="decor">
            <div class="blob"></div>
            <div class="blob b2"></div>
            <div class="spark"></div>
            <div class="spark s2"></div>
            <div class="spark s3"></div>
        </div>

        <div class="brand">
            <img src="/img/logo.png" alt="Logo" loading="lazy" />
            <span class="appname">{{ config('app.name', 'Anwendung') }}</span>
        </div>

        <div class="status-box">
            <div class="status-dot" aria-hidden="true"></div>
            <span>Service Unterbrochen</span>
            <span class="sep"></span>
            <span>503</span>
        </div>

        <h1 id="err-title">Wir sind gleich zurück.</h1>
        <p class="explain">HTTP 503 bedeutet, dass der Server deine Anfrage zwar erhalten hat, sie aber gerade nicht bearbeiten kann – meist wegen Wartung oder kurzfristiger Überlastung. Das ist vorübergehend und kein Datenverlust-Problem.</p>
        <p class="lead" id="err-desc">
            @php($message = (isset($exception) && $exception->getMessage()) ? $exception->getMessage() : 'Der Dienst ist vorübergehend nicht erreichbar oder befindet sich im Wartungsmodus. Bitte versuche es in Kürze erneut.')
            {{ $message }}
        </p>

        @if(!empty(request()->header('Retry-After')))
            <p class="mono">Retry-After: <span class="code">{{ request()->header('Retry-After') }}</span></p>
        @endif

        <div class="actions">
            <button class="btn" onclick="window.location.reload()" aria-label="Seite neu laden">Neu laden</button>
            <a class="btn secondary" href="/" aria-label="Zur Startseite">Zur Startseite</a>
        </div>
        <div class="reload-info" id="reload-info" aria-live="polite"></div>

        <div class="tips" aria-label="Tipps">
            <div class="tip"><strong>Status prüfen</strong>Wenn das Problem länger anhält, frage dein internes IT-Team oder prüfe interne Statuskanäle.</div>
            <div class="tip"><strong>Cache leeren</strong>Lösche Browser-Cache oder nutze ein privates Fenster, falls du kürzlich Änderungen deployt hast.</div>
            <div class="tip"><strong>Geplante Wartung</strong>Möglicherweise führen wir ein Update oder Sicherheitspatch durch. Kurz warten hilft oft.</div>
        </div>

        <footer>
            <span>&copy; {{ date('Y') }} – Anwendung vorübergehend nicht verfügbar.</span>
            <span>Request ID: <span class="code">{{ substr(hash('sha256', microtime(true).'|'.request()->ip()),0,12) }}</span></span>
        </footer>
        <div class="support-hint">Wenn dieser Zustand länger anhält als erwartet, notiere die Request ID und sende sie dem Support.</div>
    </div>
</div>
<noscript style="position:fixed;bottom:8px;left:50%;transform:translateX(-50%);background:#ff5470;color:#fff;padding:6px 12px;border-radius:8px;font:12px/1.2 system-ui">Hinweis: JavaScript ist deaktiviert. Automatisches Neu-Laden funktioniert nicht.</noscript>
<script>
(function() {
    const seconds = 60; // Intervall bis Auto-Reload
    let remaining = seconds;
    const el = document.getElementById('reload-info');
    function tick(){
        if(!el) return;
        el.innerHTML = 'Automatischer Reload in <strong>' + remaining + 's</strong>';
        remaining--;
        if(remaining < 0) { window.location.reload(); }
        else setTimeout(tick, 1000);
    }
    tick();
})();
</script>
</body>
</html>
