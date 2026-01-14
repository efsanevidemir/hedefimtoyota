<?php
session_start();

// --- AYARLAR ---
$admin_sifresi = "1667"; // ŞİFRENİ BURAYA YAZ
$hedef_tutar = 1300000;
$veri_dosyasi = 'veri.json';
$gunluk_veri_dosyasi = 'gunluk_fiyatlar.json'; // İsim değişti, çünkü artık fiyatları tutacağız
$MANUEL_GRAM_ALTIN_TL = 3500;

// --- DOSYA İŞLEMLERİ ---

// Varlık dosyasını oluştur
if (!file_exists($veri_dosyasi)) {
    file_put_contents($veri_dosyasi, json_encode([
            'btc' => 0, 'eth' => 0, 'sol' => 0, 'bnb' => 0, 'goldTam' => 0, 'goldYarim' => 0, 'goldGram' => 0
    ]));
}
$kayitli_veriler = json_decode(file_get_contents($veri_dosyasi), true);

// Günlük veri dosyasını oluştur (ARTIK FİYATLARI TUTUYORUZ)
if (!file_exists($gunluk_veri_dosyasi)) {
    file_put_contents($gunluk_veri_dosyasi, json_encode([], JSON_PRETTY_PRINT));
}

function getBinancePrice($symbol)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.binance.com/api/v3/ticker/price?symbol=" . $symbol);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return isset($data['price']) ? floatval($data['price']) : 0;
}

function getGoldPrice($symbol)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://finance.truncgil.com/api/gold-rates");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $result = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($result, true);
    return isset($data['Rates'][$symbol]['Buying']) ? floatval($data['Rates'][$symbol]['Buying']) : 0;
}


// --- API VE DESTEK İŞLEMLERİ ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    if ($_GET['action'] == 'get_prices') {
        $btc = getBinancePrice("BTCTRY");
        $eth = getBinancePrice("ETHTRY");
        $sol = getBinancePrice("SOLTRY");
        $bnb = getBinancePrice("BNBTRY");
        $goldTam = getGoldPrice('TAMALTIN');
        $goldYarim = getGoldPrice('YARIMALTIN');
        $goldGram = getGoldPrice('GRA');
        $goldGumus = getGoldPrice('GUMUS');

        echo json_encode([
                'btc' => $btc,
                'eth' => $eth,
                'sol' => $sol,
                'bnb' => $bnb,
                'goldTam' => $goldTam,
                'goldYarim' => $goldYarim,
                'goldGram' => $goldGram,
                'goldGumus' => $goldGumus,
        ]);
        exit;
    }


// YENİ: Fiyatları Kaydet (aynı gün için çoklu zaman-damgası snapshot'lar)
    if ($_GET['action'] == 'record_daily_prices') {
        $input_prices = json_decode(file_get_contents('php://input'), true);
        $bugun = date('Y-m-d');

        $gunluk_veriler = json_decode(file_get_contents($gunluk_veri_dosyasi), true);
        if (!is_array($gunluk_veriler)) $gunluk_veriler = [];

        // Snapshot: zaman ve fiyatları sakla
        $snapshot = [
                'time' => time(),
                'prices' => $input_prices
        ];

        if (!isset($gunluk_veriler[$bugun]) || !is_array($gunluk_veriler[$bugun])) {
            $gunluk_veriler[$bugun] = [];
        }

        $gunluk_veriler[$bugun][] = $snapshot;

        // Her gün için maksimum snapshot sayısı (ör. son 192 snapshot) — aşırı dosya büyümesini önlemek için
        $maxSnapshotsPerDay = 1;
        if (count($gunluk_veriler[$bugun]) > $maxSnapshotsPerDay) {
            $gunluk_veriler[$bugun] = array_slice($gunluk_veriler[$bugun], -$maxSnapshotsPerDay, $maxSnapshotsPerDay);
        }

        // Son 7 günü tut
        ksort($gunluk_veriler);
        $gunluk_veriler = array_slice($gunluk_veriler, -7, 7, true);

        file_put_contents($gunluk_veri_dosyasi, json_encode($gunluk_veriler, JSON_PRETTY_PRINT));
        echo json_encode(['status' => 'record_saved']);
        exit;
    }

    if ($_GET['action'] == 'get_daily_data') {
        echo file_get_contents($gunluk_veri_dosyasi);
        exit;
    }

    if ($_GET['action'] == 'save_assets' && isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
        $input = json_decode(file_get_contents('php://input'), true);
        file_put_contents($veri_dosyasi, json_encode($input));
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($_GET['action'] == 'get_assets') {
        echo file_get_contents($veri_dosyasi);
        exit;
    }
}

// --- LOGIN/LOGOUT İŞLEMLERİ ---
$login_error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] == $admin_sifresi) {
        $_SESSION['admin'] = true;
    } else {
        $login_error = "Hatalı şifre!";
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
$is_admin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hedef: Toyota Corolla</title>
    <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">
    <link rel="shortcut icon" href="https://cdn-icons-png.flaticon.com/512/3061/3061341.png" type="image/png">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;700&display=swap');

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background-color: #f4f6f8;
            color: #333;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .main-content {
            display: flex;
            max-width: 1000px;
            width: 100%;
            gap: 20px;
        }

        .container {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            flex: 2;
            min-width: 0;
        }

        .sidebar {
            background: white;
            padding: 1.5rem;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            flex: 1;
            min-width: 300px;
        }

        /* --- MOBİL UYUMLULUK --- */
        @media (max-width: 900px) {
            body {
                padding: 10px;
            }

            .main-content {
                flex-direction: column;
            }

            .container, .sidebar {
                width: 100%;
                margin-right: 0;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .header h2 {
                font-size: 1.2rem;
            }

            .total-display h3 {
                font-size: 2rem;
            }
        }

        .header {
            display: flex;
    flex-direction: column;
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 2rem;
            margin: 0;
            font-weight: 300;
        }

        .header h2 {
            font-weight: 700;
            font-style: italic;
            margin: 5px 0 20px 0;
        }

        .car-image {
        align-self: center;
            max-height: 250px;
            object-fit: contain;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .progress-container {
            height: 40px;
            background-color: #ff3b30;
            border-radius: 5px;
            overflow: hidden;
            position: relative;
            margin-bottom: 10px;
            display: flex;
        }

        .progress-bar {
            height: 100%;
            background-color: #34c759;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: width 1s ease-in-out;
            min-width: 0px;
        }

        .progress-text-overlay {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.5);
            pointer-events: none;
            z-index: 2;
        }

        .total-display {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            background: #e3f2fd;
            border-radius: 10px;
            color: #1565c0;
            position: relative;
        }

        .total-display h3 {
            margin: 0;
            font-size: 2.5rem;
        }

        .timer-badge {
            background: rgba(255, 255, 255, 0.7);
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            color: #1565c0;
            display: inline-block;
            margin-top: 10px;
            font-weight: bold;
        }

        /* --- ASSET GRID & LOGO STYLES --- */
        .asset-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 20px;
        }

        .asset-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid #eee;
            text-align: center;
            font-size: 0.9rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
        }

        .asset-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .asset-icon {
            width: 40px;
            height: 40px;
            object-fit: contain;
            margin-bottom: 8px;
            border-radius: 50%;
            background: white;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .asset-card strong {
            display: block;
            font-size: 1.1rem;
            margin-bottom: 2px;
        }

        .total-asset-value {
            color: #28a745;
            font-weight: bold;
            font-size: 1rem;
            margin: 5px 0;
        }

        .price-tag {
            color: #888;
            font-size: 0.8rem;
            margin-top: 2px;
        }

        /* Sidebar Styles */
        .daily-pl-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 0.95rem;
        }

        .daily-pl-table th, .daily-pl-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .daily-pl-table th {
            background-color: #f7f7f7;
            font-weight: 700;
            color: #555;
        }

        .daily-pl-table tr:last-child td {
            border-bottom: none;
        }

        .profit {
            color: #28a745;
            font-weight: bold;
        }

        .loss {
            color: #dc3545;
            font-weight: bold;
        }

        .neutral {
            color: #6c757d;
        }

        /* Admin Panel Styles */
        .admin-panel {
            margin-top: 20px;
            border-top: 2px solid #eee;
            padding-top: 20px;
        }

        .input-group {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .input-group label {
            flex: 1;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .input-group input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 100%;
        }

        .btn {
            width: 100%;
            padding: 10px;
            background-color: #1565c0;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            margin-top: 10px;
        }

        .btn-logout {
            background-color: #dc3545;
            margin-top: 5px;
        }

        @media (max-width: 480px) {
            .input-group {
                flex-direction: column;
                align-items: flex-start;
            }

            .input-group label {
                margin-bottom: 5px;
            }

            .input-group input {
                width: 100%;
            }
        }

        #toast {
            visibility: hidden;
            min-width: 250px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 5px;
            padding: 16px;
            position: fixed;
            z-index: 99;
            right: 20px;
            top: 20px;
            font-size: 17px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            opacity: 0;
            transition: opacity 0.5s, top 0.5s;
            border-left: 5px solid #34c759;
        }

        #toast.show {
            visibility: visible;
            opacity: 1;
            top: 30px;
        }
    </style>
</head>
<body>

<div id="toast">✅ Veriler Güncellendi!</div>

<div class="main-content">

    <div class="container">
        <div class="header">
            <h1>Hedef:</h1>
            <h2>Toyota Corolla</h2>
            <img src="https://i.ibb.co/Kps17MRj/5fd44cf0-7a61-4832-94a0-7939862ec185.png"
                 alt="Toyota Corolla" class="car-image">
        </div>

        <div class="progress-container">
            <div class="progress-bar" id="progressBar" style="width: 0%;"></div>
            <div class="progress-text-overlay" id="progressText">%0</div>
        </div>
        <div style="text-align: center; margin-bottom: 10px;">Hedef:
            <b><?php echo number_format($hedef_tutar, 0, ',', '.'); ?> TL</b></div>

        <div class="total-display">
            <span>Toplam Net Varlık</span>
            <h3 id="totalWealth">0 TL</h3>
            <div class="timer-badge">
                Sonraki güncelleme: <span id="countdown">30</span> sn
            </div>
        </div>

        <div class="asset-grid">
            <div class="asset-card">
                <img src="https://cryptologos.cc/logos/bitcoin-btc-logo.png?v=026" class="asset-icon" alt="BTC">
                <strong style="color: #F7931A;">Bitcoin</strong>
                <span id="displayBTC">0</span> Adet
                <div class="total-asset-value" id="totalValueBTC">0 TL</div>
                <div class="price-tag" id="priceBTC">Kur: --</div>
            </div>
            <div class="asset-card">
                <img src="https://cryptologos.cc/logos/ethereum-eth-logo.png?v=026" class="asset-icon" alt="ETH">
                <strong style="color: #627EEA;">Ethereum</strong>
                <span id="displayETH">0</span> Adet
                <div class="total-asset-value" id="totalValueETH">0 TL</div>
                <div class="price-tag" id="priceETH">Kur: --</div>
            </div>
            <div class="asset-card">
                <img src="https://cryptologos.cc/logos/solana-sol-logo.png?v=026" class="asset-icon" alt="SOL">
                <strong style="color: #9945FF;">Solana</strong>
                <span id="displaySOL">0</span> Adet
                <div class="total-asset-value" id="totalValueSOL">0 TL</div>
                <div class="price-tag" id="priceSOL">Kur: --</div>
            </div>
            <div class="asset-card">
                <img src="https://cryptologos.cc/logos/bnb-bnb-logo.png?v=026" class="asset-icon" alt="BNB">
                <strong style="color: #F3BA2F;">BNB</strong>
                <span id="displayBNB">0</span> Adet
                <div class="total-asset-value" id="totalValueBNB">0 TL</div>
                <div class="price-tag" id="priceBNB">Kur: --</div>
            </div>
            <div class="asset-card">
                <img src="https://cryptologos.cc/logos/pax-gold-paxg-logo.png?v=026" class="asset-icon" alt="Gold">
                <strong style="color: #d4b106;">Tam Altın</strong>
                <span id="displayGOLDTam">0</span> Adet
                <div class="total-asset-value" id="totalValueGOLDTam">0 TL</div>
                <div class="price-tag" id="priceGOLDTam">Kur: --</div>
            </div>
            <div class="asset-card">
                <img src="https://cryptologos.cc/logos/pax-gold-paxg-logo.png?v=026" class="asset-icon" alt="Gold">
                <strong style="color: #d4b106;">Yarım Altın</strong>
                <span id="displayGOLDYarim">0</span> Adet
                <div class="total-asset-value" id="totalValueGOLDYarim">0 TL</div>
                <div class="price-tag" id="priceGOLDYarim">Kur: --</div>
            </div>
            <div class="asset-card">
                <img src="https://cryptologos.cc/logos/pax-gold-paxg-logo.png?v=026" class="asset-icon" alt="Gold">
                <strong style="color: #d4b106;">Gram Altın</strong>
                <span id="displayGOLDGram">0</span> Adet
                <div class="total-asset-value" id="totalValueGOLDGram">0 TL</div>
                <div class="price-tag" id="priceGOLDGram">Kur: --</div>
            </div>
            <div class="asset-card">
                <img src="https://cryptologos.cc/logos/pax-gold-paxg-logo.png?v=026" class="asset-icon" alt="Gumus">
                <strong style="color: #d4b106;">Gumus</strong>
                <span id="displayGOLDGumus">0</span> Adet
                <div class="total-asset-value" id="totalValueGOLDGumus">0 TL</div>
                <div class="price-tag" id="priceGOLDGumus">Kur: --</div>
            </div>
        </div>

        <?php if ($is_admin): ?>
            <div class="admin-panel">
                <h3>⚙️ Yönetici Paneli</h3>
                <div class="input-group"><label>Bitcoin Adet</label><input type="number" step="0.000001" id="in_btc"
                                                                           value="<?php echo $kayitli_veriler['btc']; ?>">
                </div>
                <div class="input-group"><label>Ethereum Adet</label><input type="number" step="0.00001" id="in_eth"
                                                                            value="<?php echo $kayitli_veriler['eth']; ?>">
                </div>
                <div class="input-group"><label>Solana Adet</label><input type="number" step="0.01" id="in_sol"
                                                                          value="<?php echo $kayitli_veriler['sol']; ?>">
                </div>
                <div class="input-group"><label>BNB Adet</label><input type="number" step="0.01" id="in_bnb"
                                                                       value="<?php echo $kayitli_veriler['bnb']; ?>">
                </div>
                <div class="input-group"><label>Tam Altın Adet</label><input type="number" step="1" id="in_gold_tam"
                                                                             value="<?php echo $kayitli_veriler['goldTam']; ?>">
                </div>
                <div class="input-group"><label>Yarım Altın Adet</label><input type="number" step="1" id="in_gold_yarim"
                                                                               value="<?php echo $kayitli_veriler['goldYarim']; ?>">
                </div>
                <div class="input-group"><label>Gram Altın Adet</label><input type="number" step="1" id="in_gold_gram"
                                                                              value="<?php echo $kayitli_veriler['goldGram']; ?>">
                </div>
                <div class="input-group"><label>Gumus Adet</label><input type="number" step="1" id="in_gold_gumus"
                                                                         value="<?php echo $kayitli_veriler['goldGumus']; ?>">
                </div>

                <button class="btn" onclick="saveAdminData()">💾 Varlıkları Kaydet</button>
                <a href="?logout=1">
                    <button class="btn btn-logout">Çıkış Yap</button>
                </a>
            </div>
        <?php else: ?>
            <details style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 10px;">
                <summary style="cursor: pointer; color: #ccc; font-size: 0.8rem; list-style: none; text-align: center;">
                    🔒 Yönetici Girişi
                </summary>
                <form method="POST" style="margin-top: 10px; display:flex; gap:10px;">
                    <?php if ($login_error) echo "<p style='color:red; width:100%; text-align:center;'>$login_error</p>"; ?>
                    <input type="password" name="password" placeholder="Şifre"
                           style="padding: 10px; width: 70%; border:1px solid #ddd; border-radius:5px;">
                    <button type="submit"
                            style="padding: 10px; width: 30%; background:#333; color:white; border:none; border-radius:5px;">
                        Gir
                    </button>
                </form>
            </details>
        <?php endif; ?>
    </div>

    <div class="sidebar">
        <h3>📊 Market Performansı (7 Gün)</h3>
        <small style="display: block; margin-bottom: 10px; color: #666; font-size: 0.8rem;">* Hesaplama: (Eski Fiyatlar
            x GÜNCEL Adet).<br>Yeni eklediğiniz varlıklar kar olarak görünmez, sadece piyasa artışı görünür.</small>
        <table class="daily-pl-table">
            <thead>
            <tr>
                <th>Tarih</th>
                <th>Değişim</th>
            </tr>
            </thead>
            <tbody id="dailyPLTableBody">
            </tbody>
        </table>
    </div>

</div>

<script>
    const TARGET = <?php echo $hedef_tutar; ?>;
    const UPDATE_INTERVAL = 5;

    let assets = {btc: 0, eth: 0, sol: 0, bnb: 0, goldTam: 0, goldYarim: 0, goldGram: 0, goldGumus: 0};
    let prices = {btc: 0, eth: 0, sol: 0, bnb: 0, goldTam: 0, goldYarim: 0, goldGram: 0, goldGumus: 0};
    let timerValue = UPDATE_INTERVAL;
    let timerInterval;

    let totalWealthToday = 0;

    window.onload = function () {
        fetchAssets();
        updateSystem();
        // startTimer() updateSystem içinde çağrılacak veri gelince
    };

    // --- Kar/Zarar Fonksiyonları ---

    function loadDailyPL() {
        fetch('index.php?action=get_daily_data')
            .then(r => r.json())
            .then(dailyPrices => {
                renderDailyPL(dailyPrices);        // mevcut 7 günlük gösterim (kapanış bazlı)
            })
            .catch(err => {
                console.error("Günlük veri yükleme hatası:", err);
            });
    }

    function renderDailyPL(dailyPrices) {
        const tbody = document.getElementById('dailyPLTableBody');
        tbody.innerHTML = '';

        if (!dailyPrices || Object.keys(dailyPrices).length === 0) {
            const row = tbody.insertRow();
            row.innerHTML = `<td colspan="2" style="text-align:center;color:#666;">Kayıt bulunamadı</td>`;
            return;
        }

        // Tarihleri sırala ve son 7 günü al
        const dates = Object.keys(dailyPrices).sort();
        const lastSevenDates = dates.slice(-7);

        // Her tarih için "günün kapanış (son) fiyatını" seç
        const dailyClosing = []; // { date, time, prices }
        lastSevenDates.forEach(date => {
            const entry = dailyPrices[date];

            if (Array.isArray(entry)) {
                // snapshot array -> en son snapshot (en büyük time) seç
                let lastSnap = null;
                entry.forEach(s => {
                    // eski format kontrolü: s.prices yoksa s doğrudan prices olabilir
                    if (s && s.prices) {
                        if (!lastSnap || (s.time || 0) > (lastSnap.time || 0)) lastSnap = {
                            time: s.time || 0,
                            prices: s.prices
                        };
                    } else if (typeof s === 'object' && !s.prices) {
                        // fallback: eski single-price-per-day format inside array
                        if (!lastSnap) lastSnap = {time: 0, prices: s};
                    }
                });
                if (lastSnap) dailyClosing.push({date, time: lastSnap.time || 0, prices: lastSnap.prices || {}});
            } else if (entry && typeof entry === 'object') {
                // tek obje formatı (eski) -> kullan
                dailyClosing.push({date, time: 0, prices: entry});
            }
        });

        console.log(dailyClosing);

        if (dailyClosing.length === 0) {
            const row = tbody.insertRow();
            row.innerHTML = `<td colspan="2" style="text-align:center;color:#666;">Yeterli veri yok</td>`;
            return;
        }

        // Günlük toplam varlık değerlerini hesapla (her gün için: o günün fiyatları × GÜNCEL adetler)
        const dayWealths = dailyClosing.map(d => {
            const p = d.prices || {};
            const calculatedWealth =
                (assets.btc * (p.btc || 0)) +
                (assets.eth * (p.eth || 0)) +
                (assets.sol * (p.sol || 0)) +
                (assets.bnb * (p.bnb || 0)) +
                (assets.goldTam * (p.goldTam || 0)) +
                (assets.goldYarim * (p.goldYarim || 0)) +
                (assets.goldGram * (p.goldGram || 0)) +
                (assets.goldGumus * (p.goldGumus || 0));
            return {date: d.date, time: d.time, wealth: calculatedWealth};
        });

        // Art arda günler arasındaki farkı hesapla ve tabloya yaz
        for (let i = 0; i < dayWealths.length; i++) {
            const cur = dayWealths[i];
            const prev = i > 0 ? dayWealths[i - 1] : null;

            let differenceText = 'Başlangıç';
            let differenceClass = 'neutral';

            if (prev) {
                const diff = cur.wealth - prev.wealth;
                if (diff > 0) {
                    differenceClass = 'profit';
                    differenceText = '+' + formatMoney(diff) + ' TL';
                } else if (diff < 0) {
                    differenceClass = 'loss';
                    differenceText = formatMoney(diff) + ' TL';
                } else {
                    differenceText = '0 TL';
                }
            }

            // Tarih gösterimi: eğer zaman damgası varsa tarih+saati, yoksa sadece tarih
            let displayLabel = '';
            if (cur.time && cur.time > 0) {
                const dt = new Date(cur.time * 1000);
                const day = dt.getDate().toString().padStart(2, '0');
                const month = getMonthName((dt.getMonth() + 1).toString().padStart(2, '0'));
                const hh = dt.getHours().toString().padStart(2, '0');
                const mm = dt.getMinutes().toString().padStart(2, '0');
                displayLabel = `${day} ${month} ${hh}:${mm}`;
            } else {
                const parts = cur.date.split('-');
                displayLabel = parts[2] + ' ' + getMonthName(parts[1]);
            }

            const row = tbody.insertRow();
            row.innerHTML = `
            <td>${displayLabel}</td>
            <td class="${differenceClass}">${differenceText}</td>
        `;
        }
    }


    function getMonthName(monthNumber) {
        const names = ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
        return names[parseInt(monthNumber)];
    }

    function recordDailyPrices() {
        // Fiyatlar yüklendiyse ve 0 değilse kaydet
        if (prices.btc > 0) {
            fetch('index.php?action=record_daily_prices', {
                method: 'POST',
                body: JSON.stringify(prices) // Sadece fiyatları gönderiyoruz
            })
                .then(r => r.json())
                .then(res => {
                    if (res.status === 'record_saved') {
                        loadDailyPL();
                    }
                }).catch(err => console.log(err));
        }
    }

    // --- Ana Güncelleme Fonksiyonları ---

    function startTimer() {
        clearInterval(timerInterval);
        timerValue = UPDATE_INTERVAL;
        document.getElementById('countdown').innerText = timerValue;

        timerInterval = setInterval(() => {
            timerValue--;
            document.getElementById('countdown').innerText = timerValue;

            if (timerValue <= 0) {
                updateSystem();
                timerValue = UPDATE_INTERVAL;
            }
        }, 1000);
    }

    function showToast() {
        const toast = document.getElementById("toast");
        toast.className = "show";
        setTimeout(function () {
            toast.className = toast.className.replace("show", "");
        }, 3000);
    }

    function fetchAssets() {
        fetch('index.php?action=get_assets')
            .then(r => r.json())
            .then(data => {
                assets = data;
                document.getElementById('displayBTC').innerText = assets.btc;
                document.getElementById('displayETH').innerText = assets.eth;
                document.getElementById('displaySOL').innerText = assets.sol;
                document.getElementById('displayBNB').innerText = assets.bnb;
                document.getElementById('displayGOLDTam').innerText = assets.goldTam;
                document.getElementById('displayGOLDYarim').innerText = assets.goldYarim;
                document.getElementById('displayGOLDGram').innerText = assets.goldGram;
                document.getElementById('displayGOLDGumus').innerText = assets.goldGumus;
                // Varlıklar yüklendiğinde fiyatları da çek
                // (Senkronizasyon sorunu olmaması için burada çağırmak daha iyi)
                // updateSystem() onload'da çağrıldığı için burada tekrar çağırmaya gerek yok ama
                // assets değişkeninin dolu olduğundan emin olmalıyız.
            });
    }

    function updateSystem() {
        fetch('index.php?action=get_prices')
            .then(r => r.json())
            .then(data => {
                prices = data;

                document.getElementById('priceBTC').innerText = formatCurrency(prices.btc);
                document.getElementById('priceETH').innerText = formatCurrency(prices.eth);
                document.getElementById('priceSOL').innerText = formatCurrency(prices.sol);
                document.getElementById('priceBNB').innerText = formatCurrency(prices.bnb);
                document.getElementById('priceGOLDTam').innerText = formatCurrency(prices.goldTam);
                document.getElementById('priceGOLDYarim').innerText = formatCurrency(prices.goldYarim);
                document.getElementById('priceGOLDGram').innerText = formatCurrency(prices.goldGram);
                document.getElementById('priceGOLDGumus').innerText = formatCurrency(prices.goldGumus);

                calculateTotal();
                showToast();
                recordDailyPrices(); // Yeni kayıt fonksiyonu
                startTimer(); // Timer'ı her şey bittikten sonra başlat
            })
            .catch(err => {
                console.error("Fiyat çekme hatası:", err);
                startTimer(); // Hata olsa bile sayacı devam ettir
            });
    }

    function calculateTotal() {
        // Her bir varlığın toplam değeri
        let valBTC = assets.btc * prices.btc;
        let valETH = assets.eth * prices.eth;
        let valSOL = assets.sol * prices.sol;
        let valBNB = assets.bnb * prices.bnb;
        let valGOLDTam = assets.goldTam * prices.goldTam;
        let valGOLDYarim = assets.goldYarim * prices.goldYarim;
        let valGOLDGram = assets.goldGram * prices.goldGram;
        let valGOLDGumus = assets.goldGumus * prices.goldGumus;

        let total = valBTC + valETH + valSOL + valBNB + valGOLDTam + valGOLDYarim + valGOLDGram + valGOLDGumus;

        // Bireysel değerleri ekrana yaz
        document.getElementById('totalValueBTC').innerText = formatCurrency(valBTC);
        document.getElementById('totalValueETH').innerText = formatCurrency(valETH);
        document.getElementById('totalValueSOL').innerText = formatCurrency(valSOL);
        document.getElementById('totalValueBNB').innerText = formatCurrency(valBNB);
        document.getElementById('totalValueGOLDTam').innerText = formatCurrency(valGOLDTam);
        document.getElementById('totalValueGOLDYarim').innerText = formatCurrency(valGOLDYarim);
        document.getElementById('totalValueGOLDGram').innerText = formatCurrency(valGOLDGram);
        document.getElementById('totalValueGOLDGumus').innerText = formatCurrency(valGOLDGumus);


        totalWealthToday = total;

        document.getElementById('totalWealth').innerText = formatMoney(total) + " TL";

        let percentage = (total / TARGET) * 100;
        if (percentage > 100) percentage = 100;

        document.getElementById('progressBar').style.width = percentage + "%";
        document.getElementById('progressText').innerText = "%" + percentage.toFixed(2);

        if (percentage >= 100) {
            document.querySelector('.progress-container').style.backgroundColor = "#34c759";
        }
    }

    function saveAdminData() {
        const newAssets = {
            btc: parseFloat(document.getElementById('in_btc').value) || 0,
            eth: parseFloat(document.getElementById('in_eth').value) || 0,
            sol: parseFloat(document.getElementById('in_sol').value) || 0,
            bnb: parseFloat(document.getElementById('in_bnb').value) || 0,
            goldTam: parseInt(document.getElementById('in_gold_tam').value) || 0,
            goldYarim: parseInt(document.getElementById('in_gold_yarim').value) || 0,
            goldGram: parseInt(document.getElementById('in_gold_gram').value) || 0,
            goldGumus: parseFloat(document.getElementById('in_gold_gumus').value) || 0
        };

        fetch('index.php?action=save_assets', {
            method: 'POST',
            body: JSON.stringify(newAssets)
        })
            .then(r => r.json())
            .then(res => {
                if (res.status === 'success') {
                    alert("Varlıklar kaydedildi!");
                    // Sayfayı yenilemeden verileri güncelle
                    assets = newAssets;
                    document.getElementById('displayBTC').innerText = assets.btc;
                    document.getElementById('displayETH').innerText = assets.eth;
                    document.getElementById('displaySOL').innerText = assets.sol;
                    document.getElementById('displayBNB').innerText = assets.bnb;
                    document.getElementById('displayGOLDTam').innerText = assets.goldTam;
                    document.getElementById('displayGOLDYarim').innerText = assets.goldYarim;
                    document.getElementById('displayGOLDGram').innerText = assets.goldGram;
                    document.getElementById('displayGOLDGumus').innerText = assets.goldGumus;

                    calculateTotal();
                    // Tabloyu da yeni adetlere göre tekrar render et
                    loadDailyPL();
                }
            });
    }

    function formatMoney(amount) {
        return amount.toLocaleString('tr-TR', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }

    function formatCurrency(amount) {
        return amount.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " TL";
    }
</script>

</body>
</html>