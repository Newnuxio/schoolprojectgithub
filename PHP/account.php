
<?php
// Sessie starten
session_start();

require '../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable("../");
$dotenv->load();

// Ingelogd?
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    // Zo niet, naar login
    header("Location: login.php");
    exit();
}

$naam = isset($_SESSION['naam']) ? htmlspecialchars($_SESSION['naam']) : '';
$success = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error = isset($_SESSION['error']) ? $_SESSION['error'] : '';
unset($_SESSION['success'], $_SESSION['error']);

$conn = new mysqli("localhost", "root", "root", "eindproduct");
if ($conn->connect_error) {
    $error = "Databaseverbinding mislukt.";
}

// Naam wijzigen
if (isset($_POST['change_name'])) {
    $new_naam = htmlspecialchars($_POST['new_naam'], ENT_QUOTES, 'UTF-8');
    $email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
    if (!empty($new_naam)) {
        $stmt = $conn->prepare("UPDATE klanten SET naam = ? WHERE naam = ?");
        $stmt->bind_param("ss", $new_naam, $_SESSION['naam']);
        if ($stmt->execute()) {
            $_SESSION['naam'] = $new_naam;
            $naam = $new_naam;
            $success = "Naam succesvol gewijzigd.";
        } else {
            $error = "Naam wijzigen mislukt.";
        }
        $stmt->close();
    } else {
        $error = "Vul een nieuwe naam in.";
    }
}

// Wachtwoord wijzigen
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $repeat = $_POST['repeat_password'];
    if ($new !== $repeat) {
        $error = "Nieuwe wachtwoorden komen niet overeen.";
    } else {
        $stmt = $conn->prepare("SELECT wachtwoord FROM klanten WHERE naam = ?");
        $stmt->bind_param("s", $_SESSION['naam']);
        $stmt->execute();
        $stmt->bind_result($hash);
        if ($stmt->fetch() && password_verify($current, $hash)) {
            $stmt->close();
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("UPDATE klanten SET wachtwoord = ? WHERE naam = ?");
            $stmt2->bind_param("ss", $new_hash, $_SESSION['naam']);
            if ($stmt2->execute()) {
                $success = "Wachtwoord succesvol gewijzigd.";
            } else {
                $error = "Wachtwoord wijzigen mislukt.";
            }
            $stmt2->close();
        } else {
            $error = "Huidig wachtwoord is onjuist.";
        }
        // $stmt->close();
    }
}

// Account verwijderen
if (isset($_POST['delete_account'])) {
    $stmt = $conn->prepare("DELETE FROM klanten WHERE naam = ?");
    $stmt->bind_param("s", $_SESSION['naam']);
    if ($stmt->execute()) {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit;
    } else {
        $error = "Account verwijderen mislukt.";
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mijn Account</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../CSS/Toets.css">
    <link rel="stylesheet" href="../CSS/custom.css">
    <link rel="stylesheet" href="../CSS/style_create.css">
    <style>
        .account-form-container {
            border-radius: 1rem;
        }
        .submit-btn, .btn-primary, .btn-primary:visited, .btn-primary:active, .btn-primary:focus {
            background: #218838 !important;
            border-color: #218838 !important;
            color: #fff !important;
        }
        .submit-btn:hover, .btn-primary:hover {
            background: #218838 !important;
            border-color: #218838 !important;
            color: #fff !important;
        }
        .btn-info {
            background: #17a2b8 !important;
            border-color: #17a2b8 !important;
            color: #fff !important;
        }
        .btn-info:hover {
            background: #138496 !important;
            border-color: #138496 !important;
            color: #fff !important;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="account-form-container card shadow-lg border-0">
                <div class="card-body">
                    <h2 class="text-center mb-4">Mijn Account</h2>
                    <?php if ($success): ?>
                        <div class="alert alert-success"> <?= $success ?> </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"> <?= $error ?> </div>
                    <?php endif; ?>
                    <div class="mb-3 text-center">
                        <strong>Welkom, <?php echo $naam; ?>!</strong>
                    </div>
                    <form method="post" action="change_name.php" class="mb-3">
                        <h5>Naam wijzigen</h5>
                        <div class="form-group mb-2">
                            <input type="text" name="new_naam" class="form-control" placeholder="Nieuwe naam" required>
                        </div>
                        <div class="d-grid mb-3">
                            <button type="submit" name="change_name" class="btn btn-primary">Wijzig naam</button>
                        </div>
                    </form>
                    <form method="post" action="change_password.php">
                        <h5>Wachtwoord wijzigen</h5>
                        <div class="form-group mb-2">
                            <input type="password" name="current_password" class="form-control" placeholder="Huidig wachtwoord" required>
                        </div>
                        <div class="form-group mb-2">
                            <input type="password" name="new_password" class="form-control" placeholder="Nieuw wachtwoord" required>
                        </div>
                        <div class="form-group mb-3">
                            <input type="password" name="repeat_password" class="form-control" placeholder="Herhaal nieuw wachtwoord" required>
                        </div>
                        <div class="d-grid mb-2">
                            <button type="submit" name="change_password" class="btn btn-primary">Wijzig wachtwoord</button>
                        </div>
                    </form>
                    <div class="d-grid mb-2">
                        <a href="export_logs.php" class="btn btn-info" target="_blank">
                            📄 Exporteer Activiteiten naar PDF
                        </a>
                    </div>
                    <form method="post" action="delete_account.php" onsubmit="return confirm('Weet je zeker dat je je account wilt verwijderen? Dit kan niet ongedaan worden gemaakt.');">
                        <div class="d-grid mb-2">
                            <button type="submit" name="delete_account" class="btn btn-danger">Verwijder account</button>
                        </div>
                    </form>
                    <div class="d-grid mb-2">
                        <a href="index.php" class="btn btn-outline-secondary">Terug naar Home</a>
                    </div>
                    <div class="d-grid">
                        <a href="logout.php" class="btn btn-danger">Uitloggen</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
