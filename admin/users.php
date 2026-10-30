<?php
// marketplace/admin/users.php

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/database.php';

// Require login and admin role
Session::requireRole('admin');
$db = db();
$isAdminPage = true;

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$userType = isset($_GET['user_type']) ? sanitize($_GET['user_type']) : '';
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$stateFilter = isset($_GET['state']) ? sanitize($_GET['state']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = ADMIN_ITEMS_PER_PAGE;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = ["1=1"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(email LIKE ? OR full_name LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($userType)) {
    $whereConditions[] = "user_type = ?";
    $params[] = $userType;
}

if (!empty($stateFilter)) {
    $whereConditions[] = "state = ?";
    $params[] = $stateFilter;
}

if (!empty($status)) {
    if ($status === 'active') {
        $whereConditions[] = "is_active = 1";
    } elseif ($status === 'inactive') {
        $whereConditions[] = "is_active = 0";
    } elseif ($status === 'verified') {
        $whereConditions[] = "email_verified = 1";
    } elseif ($status === 'unverified') {
        $whereConditions[] = "email_verified = 0";
    }
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM users $whereClause";
$totalResult = $db->fetchOne($countQuery, $params);
$totalItems = $totalResult['total'] ?? 0;
$totalPages = ceil($totalItems / $perPage);

// Get users
$query = "
    SELECT u.*,
           (SELECT COUNT(*) FROM listings WHERE seller_id = u.id) as total_listings,
           (SELECT COUNT(*) FROM orders WHERE seller_id = u.id) as total_sales,
           (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE seller_id = u.id AND payment_status = 'paid') as total_revenue,
           sp.store_name,
           sp.is_verified as seller_verified,
           sp.rating_avg as seller_rating
    FROM users u
    LEFT JOIN seller_profiles sp ON u.id = sp.user_id
    $whereClause
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";

$queryParams = array_merge($params, [$perPage, $offset]);
$users = $db->fetchAll($query, $queryParams);

// Get user type counts for filters
$typeCounts = $db->fetchAll(
    "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type"
);
$typeCountMap = [];
foreach ($typeCounts as $tc) {
    $typeCountMap[$tc['user_type']] = $tc['count'];
}

// Get status counts
$statusCounts = [
    'active' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'],
    'inactive' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE is_active = 0")['count'],
    'verified' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE email_verified = 1")['count'],
    'unverified' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE email_verified = 0")['count'],
];

// Get states with user counts (for filter)
$statesWithUsers = $db->fetchAll(
    "SELECT state, COUNT(*) as count FROM users 
     WHERE state IS NOT NULL AND state != '' 
     GROUP BY state 
     ORDER BY state"
);

$pageJS = 'admin.js';
include __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">

        <!-- ============================================
             SIDEBAR - Using centralized include file
        ============================================= -->
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>


        <!-- ============================================
             MAIN CONTENT
        ============================================= -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4">

            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="bi bi-people"></i> User Management</h1>
                <div>
                    <span class="text-muted"><?php echo $totalItems; ?> total users</span>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo SITE_URL; ?>/admin/users.php" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Name, email, phone..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">User Type</label>
                            <select class="form-select" name="user_type">
                                <option value="">All Types</option>
                                <option value="buyer" <?php echo $userType == 'buyer' ? 'selected' : ''; ?>>
                                    Buyers (<?php echo $typeCountMap['buyer'] ?? 0; ?>)
                                </option>
                                <option value="seller" <?php echo $userType == 'seller' ? 'selected' : ''; ?>>
                                    Sellers (<?php echo $typeCountMap['seller'] ?? 0; ?>)
                                </option>
                                <option value="admin" <?php echo $userType == 'admin' ? 'selected' : ''; ?>>
                                    Admins (<?php echo $typeCountMap['admin'] ?? 0; ?>)
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">State</label>
                            <select class="form-select" name="state">
                                <option value="">All States</option>
                                <?php foreach ($statesWithUsers as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['state']); ?>" 
                                            <?php echo $stateFilter == $s['state'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['state']); ?> (<?php echo $s['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>
                                    Active (<?php echo $statusCounts['active']; ?>)
                                </option>
                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>
                                    Inactive (<?php echo $statusCounts['inactive']; ?>)
                                </option>
                                <option value="verified" <?php echo $status == 'verified' ? 'selected' : ''; ?>>
                                    Verified (<?php echo $statusCounts['verified']; ?>)
                                </option>
                                <option value="unverified" <?php echo $status == 'unverified' ? 'selected' : ''; ?>>
                                    Unverified (<?php echo $statusCounts['unverified']; ?>)
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search"></i> Filter
                            </button>
                            <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-outline-secondary ms-2">
                                <i class="bi bi-arrow-counterclockwise"></i>
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-people display-1 text-muted"></i>
                    <h3 class="mt-3">No users found</h3>
                    <p class="text-muted">Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50">
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>User</th>
                                <th>Location</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Activity</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="row-select" value="<?php echo $user['id']; ?>">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo getProfileImageUrl($user['profile_pic'] ?? null); ?>" 
                                                 class="rounded-circle" 
                                                 style="width: 40px; height: 40px; object-fit: cover;">
                                            <div class="ms-2">
                                                <div class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($user['email']); ?></div>
                                                <?php if ($user['phone']): ?>
                                                    <div class="text-muted small">
                                                        <i class="bi bi-phone"></i> <?php echo htmlspecialchars($user['phone']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['state']) || !empty($user['lga'])): ?>
                                            <div class="small">
                                                <i class="bi bi-geo-alt text-primary"></i>
                                                <?php if (!empty($user['lga'])): ?>
                                                    <strong><?php echo htmlspecialchars($user['lga']); ?></strong>
                                                <?php endif; ?>
                                                <?php if (!empty($user['state'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($user['state']); ?> State</small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted small">
                                                <i class="bi bi-geo-alt"></i> Not set
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $user['user_type'] == 'admin' ? 'danger' : ($user['user_type'] == 'seller' ? 'warning' : 'info'); ?>">
                                            <?php echo ucfirst($user['user_type']); ?>
                                        </span>
                                        <?php if ($user['user_type'] == 'seller' && $user['seller_verified']): ?>
                                            <br><span class="badge bg-success">Verified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!$user['is_active']): ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php elseif (!$user['email_verified']): ?>
                                            <span class="badge bg-warning">Unverified</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['user_type'] == 'seller'): ?>
                                            <div class="small">
                                                <div><i class="bi bi-grid"></i> <?php echo $user['total_listings']; ?> listings</div>
                                                <div><i class="bi bi-box"></i> <?php echo $user['total_sales']; ?> sales</div>
                                                <div class="text-success fw-bold">
                                                    <?php echo formatNaira($user['total_revenue']); ?>
                                                </div>
                                                <?php if ($user['seller_rating'] > 0): ?>
                                                    <div>
                                                        <i class="bi bi-star-fill text-warning"></i> 
                                                        <?php echo number_format($user['seller_rating'], 1); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo formatDate($user['created_at']); ?></small>
                                        <?php if ($user['last_login']): ?>
                                            <br><small class="text-muted">Last: <?php echo timeAgo($user['last_login']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo SITE_URL; ?>/admin/user-detail.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-primary"
                                               title="View details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($user['user_type'] != 'admin'): ?>
                                                <?php if ($user['is_active']): ?>
                                                    <button class="btn btn-outline-danger user-action-btn" 
                                                            data-user-id="<?php echo $user['id']; ?>" 
                                                            data-action="suspend"
                                                            data-username="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                            title="Suspend user">
                                                        <i class="bi bi-pause"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-success user-action-btn" 
                                                            data-user-id="<?php echo $user['id']; ?>" 
                                                            data-action="activate"
                                                            data-username="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                            title="Activate user">
                                                        <i class="bi bi-play"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!$user['email_verified']): ?>
                                                    <button class="btn btn-outline-warning user-action-btn" 
                                                            data-user-id="<?php echo $user['id']; ?>" 
                                                            data-action="verify"
                                                            data-username="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                            title="Verify email">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Bulk Actions -->
                <div class="row mt-3 align-items-center">
                    <div class="col-md-6">
                        <div class="d-flex gap-2">
                            <select class="form-select form-select-sm" id="bulkAction" style="width: auto;">
                                <option value="">Bulk Actions</option>
                                <option value="suspend">Suspend</option>
                                <option value="activate">Activate</option>
                                <option value="verify">Verify</option>
                                <option value="delete">Delete</option>
                            </select>
                            <button class="btn btn-sm btn-primary" id="applyBulkAction">
                                Apply
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <?php if ($totalPages > 1): ?>
                            <?php 
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $baseUrl = SITE_URL . '/admin/users.php?' . http_build_query($queryParams);
                            echo paginate($page, $totalPages, $baseUrl);
                            ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Select all checkbox
    document.getElementById('selectAll')?.addEventListener('change', function() {
        document.querySelectorAll('.row-select').forEach(cb => {
            cb.checked = this.checked;
        });
    });

    // Bulk action
    document.getElementById('applyBulkAction')?.addEventListener('click', function() {
        const action = document.getElementById('bulkAction').value;
        const selected = document.querySelectorAll('.row-select:checked');
        
        if (!action) {
            alert('Please select an action');
            return;
        }
        
        if (selected.length === 0) {
            alert('Please select at least one user');
            return;
        }
        
        const userIds = Array.from(selected).map(cb => cb.value);
        
        if (!confirm(`Are you sure you want to ${action} ${selected.length} user(s)?`)) {
            return;
        }
        
        fetch('<?php echo SITE_URL; ?>/api/admin/users.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: 'action=' + action + '&user_ids=' + userIds.join(',') + '&csrf_token=<?php echo Session::generateCSRFToken(); ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to perform action');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });

    // Individual user actions
    document.querySelectorAll('.user-action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const action = this.dataset.action;
            const username = this.dataset.username;
            
            const confirmMessage = {
                'suspend': `Are you sure you want to suspend "${username}"?`,
                'activate': `Activate "${username}"?`,
                'verify': `Verify "${username}"?`,
                'delete': `Are you sure you want to permanently delete "${username}"? This cannot be undone!`
            };
            
            if (!confirm(confirmMessage[action])) {
                return;
            }
            
            fetch('<?php echo SITE_URL; ?>/api/admin/users.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=' + action + '&user_id=' + userId + '&csrf_token=<?php echo Session::generateCSRFToken(); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to perform action');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>