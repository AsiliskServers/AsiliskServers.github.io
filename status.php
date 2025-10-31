<?php
// --- 1. domaines à vérifier ---
$domains = [
    "app.asilisk.fr",
    "asilisk.fr",
    "auth.asilisk.fr",
    "cloud.asilisk.fr",
    "dash.asilisk.fr",
    "prx2.asilisk.fr",
    "shell.asilisk.fr",
    "streaming.asilisk.fr",
    "torrent.asilisk.fr",
    "vault.asilisk.fr",
    "wiki.asilisk.fr",
];

// --- 2. fonctions de check ---
function http_check_curl($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_NOBODY         => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HEADER         => true,
    ]);
    $start = microtime(true);
    $res = curl_exec($ch);
    $time = round((microtime(true) - $start) * 1000);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return [$code, $time, $err];
}

function http_check_fallback($url) {
    $start = microtime(true);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'timeout' => 5,
            'ignore_errors' => true,
        ]
    ]);
    $res = @file_get_contents($url, false, $ctx);
    $time = round((microtime(true) - $start) * 1000);

    if (!isset($http_response_header[0])) {
        return [0, $time, "Unreachable"];
    }
    if (preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) {
        return [(int)$m[1], $time, ""];
    }
    return [0, $time, "Unknown"];
}

function check_host($host) {
    $schemes = ["https", "http"];
    foreach ($schemes as $scheme) {
        $url = $scheme . "://" . $host . "/";

        if (function_exists('curl_init')) {
            [$code, $time, $err] = http_check_curl($url);
        } else {
            [$code, $time, $err] = http_check_fallback($url);
        }

        if ($code > 0) {
            $up = ($code >= 200 && $code < 400);
            if ($code >= 500) {
                $up = false;
            }
            return [
                'host' => $host,
                'url' => $url,
                'up' => $up,
                'code' => $code,
                'time_ms' => $time,
                'error' => $up ? '' : "HTTP $code"
            ];
        }
    }

    return [
        'host' => $host,
        'url' => null,
        'up' => false,
        'code' => 0,
        'time_ms' => null,
        'error' => 'Unreachable'
    ];
}

// --- 3. exécution des checks ---
$results = [];
foreach ($domains as $d) {
    $results[] = check_host($d);
}

// --- 4. trier : d'abord les DOWN, ensuite les UP ---
usort($results, function($a, $b) {
    // les DOWN d'abord
    if ($a['up'] === $b['up']) {
        return strcmp($a['host'], $b['host']);
    }
    return $a['up'] ? 1 : -1; // a UP → va après
});

$all_up = true;
foreach ($results as $r) {
    if (!$r['up']) {
        $all_up = false;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Status • asilisk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- refresh auto -->
    <meta http-equiv="refresh" content="30">
    <style>
        :root {
            --bg: #0c0c0d;
            --card: rgba(255,255,255,0.02);
            --line: rgba(255,255,255,.04);
            --muted: rgba(255,255,255,.45);
            --danger: #ff453a;
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #0c0c0d;
            color: #fff;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .page {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 16px 64px;
        }
        header {
            display: flex;
            justify-content: space-between;
            gap: 1.5rem;
            align-items: center;
            margin-bottom: 28px;
        }
        .head-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 14px;
            border: 1px solid rgba(255,255,255,.04);
            background: rgba(255,255,255,.01);
            display: grid;
            place-items: center;
            font-weight: 600;
            font-size: .8rem;
        }
        .titles small {
            display: block;
            font-size: .6rem;
            letter-spacing: .04em;
            color: var(--muted);
            text-transform: uppercase;
        }
        .titles h1 {
            margin: 2px 0 0;
            font-size: 1.05rem;
            font-weight: 600;
        }
        .status-global {
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,.04);
            padding: 5px 12px 5px 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .68rem;
        }
        .dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: #fff;
        }
        .dot.down {
            background: var(--danger);
        }
        .when {
            text-align: right;
            font-size: .62rem;
            color: var(--muted);
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 14px;
        }
        @media (min-width: 780px) {
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        .card {
            border: 1px solid rgba(255,255,255,.015);
            background: radial-gradient(circle at top, rgba(255,255,255,0.03) 0%, rgba(12,12,13,0) 60%);
            background-color: rgba(255,255,255,0.01);
            border-radius: var(--radius);
            padding: 16px 16px 14px;
            min-height: 140px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .card.down {
            background: radial-gradient(circle at top, rgba(255,69,58,0.28) 0%, rgba(12,12,13,0) 65%);
            background-color: rgba(255,69,58,0.12);
            border: 1px solid rgba(255,69,58,.4);
            box-shadow: 0 10px 50px rgba(255,69,58,.12);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }
        .host {
            font-weight: 600;
            font-size: .9rem;
        }
        .badge-state {
            border-radius: 999px;
            padding: 3px 11px 2px;
            font-size: .64rem;
            display: flex;
            gap: 5px;
            align-items: center;
            border: 1px solid rgba(255,255,255,.08);
        }
        .badge-state.up {
            background: rgba(255,255,255,.01);
        }
        .badge-state.down {
            background: rgba(0,0,0,.25);
            border-color: rgba(255,255,255,.12);
        }
        .details {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .pill {
            background: rgba(0,0,0,.25);
            border: 1px solid rgba(255,255,255,.02);
            border-radius: 999px;
            padding: 3px 9px 2px;
            font-size: .6rem;
            color: rgba(255,255,255,.6);
        }
        .url {
            display: block;
            font-size: .58rem;
            color: rgba(255,255,255,.35);
            word-break: break-all;
        }
        .error {
            font-size: .62rem;
            color: #fff;
            background: rgba(0,0,0,.25);
            border: 1px solid rgba(0,0,0,.15);
            border-radius: 10px;
            padding: 5px 8px 4px;
        }
        footer {
            margin-top: 30px;
            text-align: center;
            font-size: .6rem;
            color: var(--muted);
        }
        @media (max-width: 520px) {
            header { flex-direction: column; align-items: flex-start; }
            .when { text-align: left; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="page">
    <header>
        <div class="head-left">
            <div class="avatar">A</div>
            <div class="titles">
                <small>Asilisk • supervision</small>
                <h1>État des domaines</h1>
            </div>
            <div class="status-global">
                <span class="dot <?php echo $all_up ? '' : 'down'; ?>"></span>
                <?php echo $all_up ? "Tout est opérationnel" : "Des incidents sont en cours"; ?>
            </div>
        </div>
        <div class="when">
            Mis à jour : <?php echo date('Y-m-d H:i:s'); ?><br>
            <span>rafraîchit toutes les 30 s</span>
        </div>
    </header>

    <div class="grid">
        <?php foreach ($results as $r): ?>
            <div class="card <?php echo $r['up'] ? '' : 'down'; ?>">
                <div class="card-header">
                    <div class="host"><?php echo htmlspecialchars($r['host']); ?></div>
                    <div class="badge-state <?php echo $r['up'] ? 'up' : 'down'; ?>">
                        <span class="dot <?php echo $r['up'] ? '' : 'down'; ?>" style="width:6px;height:6px;"></span>
                        <span><?php echo $r['up'] ? 'Disponible' : 'Indisponible'; ?></span>
                    </div>
                </div>
                <div class="details">
                    <span class="pill">Code <strong><?php echo $r['code'] ?: '—'; ?></strong></span>
                    <span class="pill">Latence <strong><?php echo $r['time_ms'] ? $r['time_ms'].' ms' : '—'; ?></strong></span>
                </div>
                <?php if ($r['url']): ?>
                    <span class="url"><?php echo htmlspecialchars($r['url']); ?></span>
                <?php endif; ?>
                <?php if (!$r['up']): ?>
                    <div class="error">
                        <?php
                        if ($r['code'] == 503) {
                            echo "503 — le domaine répond mais le service derrière est KO (NGINX/backend).";
                        } elseif (!empty($r['error'])) {
                            echo htmlspecialchars($r['error']);
                        } else {
                            echo "Hôte injoignable.";
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <footer>
        statut.php — <?php echo count($results); ?> domaines surveillés — généré côté serveur en direct
    </footer>
</div>
</body>
</html>
