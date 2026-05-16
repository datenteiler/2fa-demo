<?php
require 'vendor/autoload.php';
use OTPHP\TOTP;

session_start();

// Hilfsfunktion zum Zurücksetzen
if (isset($_GET['reset'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$error = "";
$message = "";
$step = 'login'; // Mögliche Schritte: 'login', 'setup', '2fa', 'success'

// 1. Logik: 2FA Einrichtung starten (Nur wenn kein POST-Formular abgeschickt wird)
if (isset($_GET['setup']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Generiert ein kompaktes, gut lesbares 16-Zeichen Secret (Base32-Standard)
    if (!isset($_SESSION['2fa_temp_secret'])) {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $characters[rand(0, strlen($characters) - 1)];
        }
        $_SESSION['2fa_temp_secret'] = $secret;
    }

    $totp = TOTP::create($_SESSION['2fa_temp_secret']);
    $totp->setLabel('Demo-User');
    $totp->setIssuer('Security-Portal');
    $step = 'setup';
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($totp->getProvisioningUri()) . "&size=200x200";
}

// 2. Logik: Erster Login-Versuch (Name & Passwort)
if (isset($_POST['action']) && $_POST['action'] == 'login_submit') {
    if (!empty($_POST['username']) && !empty($_POST['password'])) {
        // Wenn 2FA eingerichtet ist, leite zum Code-Eingabefeld weiter
        if (isset($_SESSION['2fa_secret'])) {
            $step = '2fa';
        } else {
            // Wenn kein 2FA eingerichtet ist, direkt einloggen (mit Warnung)
            $_SESSION['logged_in_without_2fa'] = true;
            $step = 'success';
        }
    } else {
        $error = "Bitte Benutzername und Passwort eingeben.";
        $step = 'login';
    }
}

// 3. Logik: 2FA Verifizierung im Setup-Prozess
if (isset($_POST['action']) && $_POST['action'] == 'verify_setup') {
    $totp = TOTP::create($_SESSION['2fa_temp_secret']);
    if ($totp->verify($_POST['code'])) {
        // Aktivierung erfolgreich: temporäres Secret wird fest übernommen
        $_SESSION['2fa_secret'] = $_SESSION['2fa_temp_secret'];
        unset($_SESSION['2fa_temp_secret']);
        $message = "2FA erfolgreich aktiviert! Bitte logge dich jetzt mit deinem Code ein.";
        $step = 'login';
    } else {
        $error = "Ungültiger Code. Bitte erneut versuchen.";
        $step = 'setup';
        $totp = TOTP::create($_SESSION['2fa_temp_secret']);
        $totp->setLabel('Demo-User');
        $totp->setIssuer('Security-Portal');
        $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?data=" . urlencode($totp->getProvisioningUri()) . "&size=200x200";
    }
}

// 4. Logik: 2FA Code-Prüfung beim Login
if (isset($_POST['action']) && $_POST['action'] == '2fa_submit') {
    $totp = TOTP::create($_SESSION['2fa_secret']);
    if ($totp->verify($_POST['code'])) {
        // Erfolgreicher Login MIT 2FA
        if (isset($_SESSION['logged_in_without_2fa'])) {
            unset($_SESSION['logged_in_without_2fa']);
        }
        $step = 'success';
    } else {
        $error = "Falscher 2FA-Code. Bitte überprüfe deine Authenticator-App.";
        $step = '2fa';
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sicheres Login-Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="bg-white p-8 rounded-lg shadow-xl w-full max-w-md border-t-4 border-blue-600">
    <h2 class="text-2xl font-bold mb-6 text-gray-800 text-center">Security Portal</h2>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-sm"><?= $message ?></div>
    <?php endif; ?>

    <?php if ($step == 'login'): ?>
        <form method="POST">
            <input type="hidden" name="action" value="login_submit">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Benutzername (Fake)</label>
                <input type="text" name="username" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="admin" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Passwort (Fake)</label>
                <input type="password" name="password" class="w-full px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="••••••••" required>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition duration-200">Einloggen</button>
        </form>
        <div class="mt-6 text-center">
            <a href="?setup=1" class="text-blue-500 hover:underline text-sm">2FA für diesen Test-Account einrichten</a>
        </div>

    <?php elseif ($step == 'setup'): ?>
        <div class="text-center">
            <p class="text-sm text-gray-600 mb-4">Scanne den QR-Code mit einer Authenticator-App oder tippe den Code ab.</p>
            <img src="<?= $qrCodeUrl ?>" alt="QR Code" class="mx-auto mb-4 border p-2 bg-white shadow-sm">
            
            <div class="bg-gray-50 border rounded p-3 mb-4 text-center">
                <span class="text-xs text-gray-500 block mb-1 font-sans">Code zum Abschreiben:</span>
                <span class="tracking-widest font-mono font-bold text-lg text-gray-800">
                    <?= chunk_split($_SESSION['2fa_temp_secret'], 4, ' ') ?>
                </span>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="verify_setup">
                <label class="block text-gray-700 text-sm font-bold mb-2">6-stelligen Code aus der App eingeben:</label>
                <input type="text" name="code" class="w-full text-center text-2xl tracking-widest px-3 py-2 border rounded-md mb-4 focus:ring-2 focus:ring-green-500 outline-none font-mono" placeholder="000 000" maxlength="6" required>
                <button type="submit" class="w-full bg-green-600 text-white font-bold py-2 px-4 rounded hover:bg-green-700 transition duration-200">Aktivierung bestätigen</button>
            </form>
            <div class="mt-4">
                <a href="index.php" class="text-sm text-gray-500 hover:underline">Abbrechen</a>
            </div>
        </div>

    <?php elseif ($step == '2fa'): ?>
        <form method="POST">
            <input type="hidden" name="action" value="2fa_submit">
            <p class="text-center mb-2 text-gray-700 font-semibold">Zwei-Faktor-Authentifizierung</p>
            <p class="text-xs text-center text-gray-500 mb-4">Bitte gib den aktuellen Code aus deiner App ein.</p>
            <input type="text" name="code" class="w-full text-center text-2xl tracking-widest px-3 py-2 border rounded-md mb-4 focus:ring-2 focus:ring-blue-500 outline-none font-mono" placeholder="000 000" maxlength="6" autofocus required>
            <button type="submit" class="w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition duration-200">Code verifizieren</button>
        </form>
        <div class="mt-4 text-center">
            <a href="?reset=1" class="text-sm text-gray-500 hover:underline">Zurück zum Start (Reset)</a>
        </div>

    <?php elseif ($step == 'success'): ?>
        <div class="text-center">
            <div class="text-5xl mb-4">
                <?= isset($_SESSION['logged_in_without_2fa']) ? '⚠️' : '🛡️' ?>
            </div>
            
            <h3 class="text-xl font-bold text-gray-800">
                <?= isset($_SESSION['logged_in_without_2fa']) ? 'Einfacher Login erfolgreich' : 'Sicherer Login erfolgreich' ?>
            </h3>

            <?php if (isset($_SESSION['logged_in_without_2fa'])): ?>
                <div class="mt-6 p-4 bg-orange-50 border-l-4 border-orange-500 text-left text-sm text-orange-800 rounded-r shadow-sm">
                    <p class="font-bold mb-1">Gefahrenhinweis:</p>
                    <p>Ein Dieb hätte jetzt nur deinen <strong>Benutzernamen</strong> und dein <strong>Passwort</strong> stehlen müssen (z.B. durch Datenlecks oder Phishing), um die volle Kontrolle über diesen Account zu übernehmen.</p>
                </div>
                <?php unset($_SESSION['logged_in_without_2fa']); ?>
            <?php else: ?>
                <div class="mt-6 p-4 bg-green-50 border-l-4 border-green-500 text-left text-sm text-green-800 rounded-r shadow-sm">
                    <p class="font-bold mb-1">Optimal geschützt:</p>
                    <p>Selbst wenn ein Angreifer dein Passwort kennt, kann er sich <strong>nicht</strong> einloggen. Er bräuchte zusätzlich physischen Zugriff auf dein Smartphone, um den zeitbasierten Einmalcode (TOTP) abzulesen.</p>
                </div>
            <?php endif; ?>

            <p class="mt-8 text-gray-500 italic text-xs">Sämtliche Daten existieren nur temporär in der PHP-Session und werden beim Schließen des Browsers oder Zurücksetzen gelöscht.</p>
            
            <div class="mt-6 space-y-2">
                <a href="index.php" class="block w-full bg-blue-600 text-white font-bold py-2 px-4 rounded hover:bg-blue-700 transition duration-200">Nochmal testen</a>
                <a href="?reset=1" class="block text-sm text-gray-500 hover:underline">Session manuell löschen (Reset)</a>
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>

