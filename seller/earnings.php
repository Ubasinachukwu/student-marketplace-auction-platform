<?php
// marketplace/seller/earnings.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and seller role
Session::requireSeller();

$db = db();
$userId = Session::getUserId();

// Get earnings summary
$earnings = $db->fetchOne(
    "SELECT 
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as total_revenue,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN platform_fee ELSE 0 END), 0) as total_fees,
        COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN net_amount ELSE 0 END), 0) as net_earnings,
        COALESCE(SUM(CASE WHEN payment_status = 'pending' THEN total_amount ELSE 0 END), 0) as pending_amount,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_orders,
        COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_orders
    FROM orders
    WHERE seller_id = ?",
    [$userId]
);

// Get monthly earnings
$monthlyEarnings = $db->fetchAll(
    "SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as month,
        COUNT(*) as orders,
        COALESCE(SUM(o.total_amount), 0) as revenue,
        COALESCE(SUM(o.platform_fee), 0) as fees,
        COALESCE(SUM(o.net_amount), 0) as net
    FROM orders o
    WHERE o.seller_id = ? AND o.payment_status = 'paid'
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12",
    [$userId]
);

// Get recent payouts
$recentPayouts = $db->fetchAll(
    "SELECT * FROM transactions 
     WHERE user_id = ? AND type = 'payout'
     ORDER BY created_at DESC
     LIMIT 10",
    [$userId]
);

// Get payout settings
$payoutSettings = $db->fetchOne(
    "SELECT payout_method, payout_details FROM seller_profiles WHERE user_id = ?",
    [$userId]
);

$pageJS = 'earnings.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/seller-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-currency-naira"></i> Earnings</h1>
                <div>
                    <a href="<?php echo SITE_URL; ?>/seller/payout-settings.php" class="btn btn-outline-primary">
                        <i class="bi bi-gear"></i> Payout Settings
                    </a>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-primary">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($earnings['total_revenue']); ?></div>
                                <div>Total Revenue</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-currency-naira"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-success">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($earnings['net_earnings']); ?></div>
                                <div>Net Earnings</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-wallet"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo formatNaira($earnings['pending_amount']); ?></div>
                                <div>Pending</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card bg-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="stat-number"><?php echo $earnings['paid_orders']; ?></div>
                                <div>Paid Orders</div>
                            </div>
                            <div class="stat-icon">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fee Breakdown -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-percent"></i> Fee Breakdown</h6>
                </div>
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <?php 
                            $netPercent = ($earnings['total_revenue'] > 0) ? ($earnings['net_earnings'] / $earnings['total_revenue']) * 100 : 0;
                            $feePercent = ($earnings['total_revenue'] > 0) ? ($earnings['total_fees'] / $earnings['total_revenue']) * 100 : 0;
                            ?>
                            <div class="progress" style="height: 30px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $netPercent; ?>%;">
                                    Net: <?php echo formatNaira($earnings['net_earnings']); ?>
                                </div>
                                <div class="progress-bar bg-danger" role="progressbar" 
                                     style="width: <?php echo $feePercent; ?>%;">
                                    Fees: <?php echo formatNaira($earnings['total_fees']); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">
                                <span class="text-success">■ Net Earnings</span>
                                <span class="text-danger ms-2">■ Platform Fees</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Monthly Earnings Chart -->
            <?php if (!empty($monthlyEarnings)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-graph-up"></i> Monthly Earnings</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="earningsChart" height="200"></canvas>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Monthly Breakdown -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-table"></i> Monthly Breakdown</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Orders</th>
                                    <th>Revenue</th>
                                    <th>Fees</th>
                                    <th>Net Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthlyEarnings as $row): ?>
                                    <tr>
                                        <td><?php echo date('F Y', strtotime($row['month'] . '-01')); ?></td>
                                        <td><?php echo $row['orders']; ?></td>
                                        <td><?php echo formatNaira($row['revenue']); ?></td>
                                        <td class="text-danger">-<?php echo formatNaira($row['fees']); ?></td>
                                        <td class="fw-bold text-success"><?php echo formatNaira($row['net']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Payouts -->
            <?php if (!empty($recentPayouts)): ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bi bi-clock-history"></i> Recent Payouts</h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentPayouts as $payout): ?>
                                        <tr>
                                            <td><?php echo formatDateTime($payout['created_at']); ?></td>
                                            <td class="fw-bold text-success"><?php echo formatNaira($payout['amount']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $payout['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($payout['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($payout['description'] ?? 'N/A'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Payout Settings Summary -->
            <?php if ($payoutSettings && !empty($payoutSettings['payout_method'])): ?>
                <div class="card mt-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="bi bi-info-circle"></i> Payout Method</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-0">
                            <strong>Method:</strong> <?php echo ucfirst($payoutSettings['payout_method']); ?>
                            <?php if (!empty($payoutSettings['payout_details'])): ?>
                                <br><strong>Details:</strong> <?php echo htmlspecialchars($payoutSettings['payout_details']); ?>
                            <?php endif; ?>
                        </p>
                        <a href="<?php echo SITE_URL; ?>/seller/payout-settings.php" class="btn btn-sm btn-outline-primary mt-2">
                            <i class="bi bi-pencil"></i> Update Payout Settings
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($monthlyEarnings)): ?>
    const ctx = document.getElementById('earningsChart').getContext('2d');
    const months = <?php echo json_encode(array_reverse(array_column($monthlyEarnings, 'month'))); ?>;
    const net = <?php echo json_encode(array_reverse(array_column($monthlyEarnings, 'net'))); ?>;
    const fees = <?php echo json_encode(array_reverse(array_column($monthlyEarnings, 'fees'))); ?>;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: months.map(m => {
                const parts = m.split('-');
                return new Date(parts[0], parts[1] - 1).toLocaleString('default', { month: 'short', year: 'numeric' });
            }),
            datasets: [
                {
                    label: 'Net Earnings',
                    data: net,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: '#198754',
                    borderWidth: 2,
                    order: 1
                },
                {
                    label: 'Fees',
                    data: fees,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: '#dc3545',
                    borderWidth: 2,
                    order: 2
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₦' + value.toFixed(0);
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>