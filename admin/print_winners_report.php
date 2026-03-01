<?php
require_once __DIR__ . '/../includes/config.php';

// Ensure only admin can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$db = getDBConnection();

// Fetch school info
$school_info = $db->query("SELECT school_name, logo_path FROM school_info LIMIT 1")->fetch();
$school_name = $school_info['school_name'] ?? APP_NAME;
$school_logo = !empty($school_info['logo_path']) ? '../admin/assets/images/logo_1770187955.png' . $school_info['logo_path'] : '../admin/assets/images/logo_1770187955.png';

// Fetch commissioners (for signatures)
$commissioners = $db->query("SELECT name, commission_type FROM commissioners WHERE is_active = 1 ORDER BY commission_type")->fetchAll(PDO::FETCH_ASSOC);

try {
    // fetch winners by position
    $stmt = $db->query(
        "SELECT c.position, c.name, c.party, c.section, c.photo, COUNT(v.id) as vote_count " .
        "FROM candidates c " .
        "LEFT JOIN votes v ON c.id = v.candidate_id AND v.position = c.position " .
        "WHERE c.is_active = 1 " .
        "GROUP BY c.id, c.position " .
        "ORDER BY c.position ASC, vote_count DESC, c.name ASC"
    );
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $winners = [];
    foreach ($all as $cand) {
        $pos = $cand['position'];
        if (!isset($winners[$pos]) || $cand['vote_count'] > $winners[$pos]['vote_count']) {
            // Prefix photo path with ../ for correct URL from admin folder
            if (!empty($cand['photo'])) {
                $cand['photo'] = '../' . $cand['photo'];
            }
            $winners[$pos] = $cand;
        }
    }
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Winners Report - <?php echo htmlspecialchars($school_name); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; padding: 20px; }
        .report-header { text-align: center; margin-bottom: 30px; }
        .report-header img { height: 70px; object-fit: contain; margin-bottom: 10px; }
        .report-header h1 { font-size: 1.5rem; margin-bottom: 4px; color: #1a1a1a; }
        .report-header h2 { font-size: 1rem; color: #555; font-weight: normal; margin-bottom: 0; }
        .table { font-size: 0.9rem; }
        .table th { background: #f0f0f0; }
        .winner-row td { font-weight: bold; background: #f9fff9; }
        .signatures-section { margin-top: 50px; padding-top: 20px; border-top: 1px solid #ccc; }
        .signatures-row { display: flex; justify-content: space-around; margin-top: 40px; }
        .sig-box { text-align: center; width: 30%; }
        .sig-line { border-bottom: 1px solid #333; margin-bottom: 6px; min-height: 30px; }
        .sig-label { font-size: 0.85rem; color: #333; }
        .sig-role { font-size: 0.75rem; color: #666; }
        .print-btn { position: fixed; top: 20px; right: 20px; }
    </style>
</head>
<body>
    <button onclick="window.print()" class="btn btn-primary no-print print-btn">🖨 Print Report</button>

    <div class="report-header">
        <?php if ($school_logo): ?>
            <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($school_name); ?></h1>
        <h2>SSLG Election Winners Report</h2>
    </div>

    <div class="Print-container">
        <?php if (!empty($winners)): ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Photo</th>
                        <th>Position</th>
                        <th>Winner</th>
                        <th>Party</th>
                        <th>Section</th>
                        <th>Votes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($winners as $pos => $cand): ?>
                        <tr class="winner-row">
                            <td class="text-center">
                                <?php if (!empty($cand['photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($cand['photo']); ?>" alt="" class="candidate-photo" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:1px solid #ddd;">
                                <?php else: ?>
                                    <div style="width:40px;height:40px;background:#eee;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:0.8rem;color:#999;">👤</div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($pos); ?></td>
                            <td><?php echo htmlspecialchars($cand['name']); ?></td>
                            <td><?php echo htmlspecialchars($cand['party'] ?? 'Independent'); ?></td>
                            <td><?php echo htmlspecialchars($cand['section']); ?></td>
                            <td><?php echo $cand['vote_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning">No results available.</div>
        <?php endif; ?>
    </div>

    <!-- Commissioner Signatures -->
    <div class="signatures-section">
        <p class="text-center text-muted small">Certified correct:</p>
        <div class="signatures-row">
            <?php
            // Map commissioner types to display order
            $typeOrder = ['chief' => 0, 'screening' => 1, 'electoral' => 2];
            $sigMap = [0 => '', 1 => '', 2 => ''];
            foreach ($commissioners as $c) {
                $idx = $typeOrder[$c['commission_type']] ?? -1;
                if ($idx >= 0 && $idx < 3) $sigMap[$idx] = $c['name'];
            }
            ?>
            <div class="sig-box">
                <div class="sig-line">&nbsp;</div>
                <div class="sig-label"><?php echo htmlspecialchars($sigMap[0] ?: 'Chief Commissioner'); ?></div>
                <div class="sig-role">Chief Commissioner</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">&nbsp;</div>
                <div class="sig-label"><?php echo htmlspecialchars($sigMap[1] ?: 'Commissioner on Screening & Validation'); ?></div>
                <div class="sig-role">Commissioner</div>
            </div>
            <div class="sig-box">
                <div class="sig-line">&nbsp;</div>
                <div class="sig-label"><?php echo htmlspecialchars($sigMap[2] ?: 'Commissioner of Electoral Board'); ?></div>
                <div class="sig-role">Commissioner</div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
