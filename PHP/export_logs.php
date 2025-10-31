<?php
// Sessie starten
session_start();

require '../vendor/autoload.php';

// Ingelogd?
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header("Location: login.php");
    exit();
}

$naam = $_SESSION['naam'];

// Database connectie
$conn = new mysqli("localhost", "root", "root", "eindproduct");
if ($conn->connect_error) {
    die("Databaseverbinding mislukt: " . $conn->connect_error);
}

// Haal klant ID op
$stmt = $conn->prepare("SELECT id, email FROM klanten WHERE naam = ?");
$stmt->bind_param("s", $naam);
$stmt->execute();
$result = $stmt->get_result();
$klant = $result->fetch_assoc();
$stmt->close();

if (!$klant) {
    die("Gebruiker niet gevonden.");
}

$klant_id = $klant['id'];
$email = $klant['email'];

// Haal logbestanden op (als de tabel bestaat)
// Controleer eerst of de logs tabel bestaat
$table_check = $conn->query("SHOW TABLES LIKE 'logs'");
$logs_exist = $table_check->num_rows > 0;

$logs = [];
if ($logs_exist) {
    $stmt = $conn->prepare("SELECT * FROM logs WHERE klant_id = ? ORDER BY timestamp DESC");
    $stmt->bind_param("i", $klant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();
}

// Haal bestelgeschiedenis op (uit winkelwagen sessies of orders tabel indien aanwezig)
$orders = [];
$order_check = $conn->query("SHOW TABLES LIKE 'orders'");
if ($order_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE klant_id = ? ORDER BY order_date DESC");
    $stmt->bind_param("i", $klant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
}

$conn->close();

// Genereer PDF-achtige output met HTML/CSS die geprint kan worden
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gebruikersrapport - <?= htmlspecialchars($naam) ?></title>
    <style>
        @media print {
            .no-print { display: none; }
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            line-height: 1.5;
        }
        h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        h2 {
            font-size: 18px;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0 20px 0;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background: #f0f0f0;
        }
        .info {
            margin: 10px 0;
        }
        .no-data {
            font-style: italic;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px 5px;
            text-decoration: none;
            border: 1px solid #000;
            background: #f0f0f0;
            color: #000;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>GameStop - Gebruikersrapport</h1>
    <p>Gegenereerd op: <?= date('d-m-Y H:i:s') ?></p>

    <h2>Accountinformatie</h2>
    <div class="info">
        <strong>Naam:</strong> <?= htmlspecialchars($naam) ?><br>
        <strong>Email:</strong> <?= htmlspecialchars($email) ?><br>
        <strong>Klant ID:</strong> <?= htmlspecialchars($klant_id) ?><br>
        <strong>Rapportdatum:</strong> <?= date('d-m-Y') ?>
    </div>

    <?php if ($logs_exist && count($logs) > 0): ?>
    <h2>Activiteitenlogboek</h2>
    <table>
        <thead>
            <tr>
                <th>Datum/Tijd</th>
                <th>Actie</th>
                <th>Details</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= isset($log['timestamp']) ? htmlspecialchars($log['timestamp']) : 'N/A' ?></td>
                <td><?= isset($log['action']) ? htmlspecialchars($log['action']) : 'N/A' ?></td>
                <td><?= isset($log['details']) ? htmlspecialchars($log['details']) : '-' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <h2>Activiteitenlogboek</h2>
    <div class="no-data">Geen activiteiten gevonden.</div>
    <?php endif; ?>

    <?php if (count($orders) > 0): ?>
    <h2>Bestelgeschiedenis</h2>
    <table>
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Datum</th>
                <th>Totaal</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= htmlspecialchars($order['id']) ?></td>
                <td><?= isset($order['order_date']) ? htmlspecialchars($order['order_date']) : 'N/A' ?></td>
                <td>€<?= isset($order['total']) ? number_format($order['total'], 2, ',', '.') : '0,00' ?></td>
                <td><?= isset($order['status']) ? htmlspecialchars($order['status']) : 'N/A' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <h2>Bestelgeschiedenis</h2>
    <div class="no-data">Geen bestellingen gevonden.</div>
    <?php endif; ?>

    <div class="no-print">
        <button onclick="window.print()" class="btn">Print / Opslaan als PDF</button>
        <a href="account.php" class="btn">Terug naar Account</a>
    </div>

</body>
</html>
